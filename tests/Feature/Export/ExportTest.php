<?php

// tests/Feature/Export/ExportTest.php
//
// BR-RPT-02, BR-RPT-03, BR-RPT-07, BR-RPT-08, BR-RPT-10, BR-RPT-12 — ekspor.
//
// Tautan kedaluwarsa dan berkas terhapus sengaja menghasilkan penolakan yang
// berbeda: yang pertama masih bisa diminta tautan baru, yang kedua harus
// diekspor ulang. Menyamakannya membuat Operator menunggu tautan yang tidak
// akan pernah berguna.

use App\Actions\Export\RequestExport;
use App\Data\Reporting\ExportFilters;
use App\Data\Reporting\ExportRequestData;
use App\Enums\ExportFormat;
use App\Jobs\GenerateExport;
use App\Models\AuditEntry;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\Notifications\ExportCompletedNotification;
use App\Notifications\ExportFailedNotification;
use App\Queries\Reporting\ExportHistory;
use App\States\Submission\Approved;
use App\Support\Audit\AuditTrail;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportType;
use App\Support\Reporting\ExportWriter;
use App\Support\Reporting\PrintableDocument;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

function exportOperator(): User
{
    Permission::findOrCreate(SubmissionVisibility::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(SubmissionVisibility::READ_ALL_PERMISSION);

    return $operator;
}

function submissionsFor(User $owner, int $count, array $attributes = []): void
{
    FormSubmission::factory()->count($count)->create([
        'submitted_by' => $owner->id,
        'status' => Approved::class,
        'is_submitted' => true,
        ...$attributes,
    ]);
}

function exportOf(string $type, ExportFormat $format = ExportFormat::Xlsx, ?ExportFilters $filters = null): ExportRequestData
{
    return new ExportRequestData($type, $format, $filters ?? new ExportFilters);
}

/** @return list<list<mixed>> lembar pertama berkas ekspor di disk palsu. */
function exportedSheet(ExportJob $job): array
{
    $sheets = Excel::toArray(new class implements ToArray
    {
        public function array(array $array): void {}
    }, (string) $job->file_path, ExportWriter::DISK);

    return $sheets[0];
}

it('[RPT-EXPORT-01] ekspor kecil selesai langsung', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), ExportThreshold::SYNCHRONOUS_ROWS);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));

    expect($job->row_count)->toBe(ExportThreshold::SYNCHRONOUS_ROWS)
        ->and($job->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($job->file_path)->not->toBeNull();

    Storage::disk(ExportWriter::DISK)->assertExists((string) $job->file_path);
});

it('[RPT-EXPORT-02] ekspor besar masuk background job', function () {
    Queue::fake();
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), ExportThreshold::SYNCHRONOUS_ROWS + 1);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));

    expect($job->status)->toBe(ExportJob::STATUS_QUEUED)
        ->and($job->file_path)->toBeNull();

    Queue::assertPushed(GenerateExport::class);
});

it('[RPT-EXPORT-03] berkas ekspor diakses lewat signed URL 24 jam', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));

    $url = URL::temporarySignedRoute(
        'reporting.exports.download',
        now()->addHours(ExportRetention::SIGNED_URL_HOURS),
        ['export' => $job->id],
    );

    $this->actingAs($operator)->get($url)->assertOk();
});

it('[RPT-EXPORT-04] signed URL kedaluwarsa ditolak', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));

    $url = URL::temporarySignedRoute('reporting.exports.download', now()->addHour(), ['export' => $job->id]);

    $this->travel(2)->hours();

    $this->actingAs($operator)->get($url)->assertForbidden();
});

it('[RPT-EXPORT-05] notifikasi ekspor hanya ke Operator peminta', function () {
    Storage::fake(ExportWriter::DISK);
    $peminta = exportOperator();
    $operatorLain = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    RequestExport::run($peminta, exportOf(ExportType::SUBMISSIONS));

    $hitung = fn (User $u) => $u->notifications()
        ->where('type', ExportCompletedNotification::class)
        ->count();

    expect($hitung($peminta))->toBe(1)
        ->and($hitung($operatorLain))->toBe(0);
});

it('[RPT-EXPORT-06] job gagal diulang maksimal tiga kali', function () {
    // Setelah percobaan ketiga sistem berhenti mencoba. Percobaan yang tidak
    // berujung hanya menyembunyikan kegagalan yang perlu dilihat orang.
    expect((new GenerateExport(1))->tries)->toBe(3)
        ->and((new GenerateExport(1))->backoff())->toBe([10, 40]);
});

it('[RPT-EXPORT-07] kegagalan final memberi tahu Operator peminta', function () {
    $operator = exportOperator();

    $job = ExportJob::create([
        'requested_by' => $operator->id,
        'type' => ExportType::SUBMISSIONS,
        'status' => ExportJob::STATUS_PROCESSING,
        'row_count' => 500,
        'attempts' => 3,
    ]);

    (new GenerateExport($job->id))->failed(new RuntimeException('Disk penuh'));

    expect($job->refresh()->status)->toBe(ExportJob::STATUS_FAILED)
        ->and($job->failure_reason)->toBe('Disk penuh')
        ->and($operator->notifications()->where('type', ExportFailedNotification::class)->count())->toBe(1);
});

it('[RPT-EXPORT-08] Researcher mengekspor data miliknya sendiri', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    submissionsFor($budi, 3);

    $job = RequestExport::run($budi, exportOf(ExportType::SUBMISSIONS));

    expect($job->row_count)->toBe(3)
        ->and($job->status)->toBe(ExportJob::STATUS_COMPLETED);
});

it('[RPT-EXPORT-09] ekspor Researcher tidak memuat milik orang lain', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();

    submissionsFor($budi, 3);
    submissionsFor($sari, 7);

    $job = RequestExport::run($budi, exportOf(ExportType::SUBMISSIONS, ExportFormat::Csv));

    // Batasnya sama persis dengan audit (BR-RPT-05) — satu aturan, dua
    // pemakainya.
    expect($job->row_count)->toBe(3);

    $isi = Storage::disk(ExportWriter::DISK)->get((string) $job->file_path);

    expect(substr_count((string) $isi, "\n"))->toBe(4); // header + 3 baris
});

it('[RPT-EXPORT-10] berkas ekspor terhapus setelah 24 jam', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));
    $path = (string) $job->file_path;

    $this->travel(ExportRetention::HOURS + 1)->hours();

    $this->artisan('exports:prune')->assertSuccessful();

    Storage::disk(ExportWriter::DISK)->assertMissing($path);

    expect($job->refresh()->file_deleted_at)->not->toBeNull()
        // Barisnya tetap ada: riwayatnya yang memberi tahu Operator bahwa
        // ekspornya pernah jadi lalu kedaluwarsa.
        ->and(ExportJob::query()->whereKey($job->id)->exists())->toBeTrue();

    $url = URL::temporarySignedRoute('reporting.exports.download', now()->addHour(), ['export' => $job->id]);

    $this->actingAs($operator)->get($url)->assertStatus(410);
});

it('[RPT-EXPORT-11] berkas yang belum 24 jam masih bisa diunduh', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));

    $this->travel(ExportRetention::HOURS - 1)->hours();

    $this->artisan('exports:prune')->assertSuccessful();

    expect($job->refresh()->file_deleted_at)->toBeNull();

    $url = URL::temporarySignedRoute('reporting.exports.download', now()->addHour(), ['export' => $job->id]);

    $this->actingAs($operator)->get($url)->assertOk();
});

it('menolak unduhan berkas milik orang lain meski tautannya sah', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    $orangLain = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));

    $url = URL::temporarySignedRoute('reporting.exports.download', now()->addHour(), ['export' => $job->id]);

    $this->actingAs($orangLain)->get($url)->assertForbidden();
});

it('BR-RPT-03 riwayat ekspor memuat tautan unduh hanya selama berkasnya ada', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $ada = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));
    $hilang = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS));
    $hilang->update(['file_deleted_at' => now()]);

    $this->actingAs($operator)
        ->get(route('reporting.exports.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Reporting/ExportPage')
            ->has('jobs', 2)
            ->where('jobs.1.id', $ada->id)
            ->where('jobs.1.type', ExportType::label($ada->type))
            ->where('jobs.1.type_value', $ada->type)
            ->where('jobs.1.status', ExportJob::STATUS_COMPLETED)
            ->where('jobs.1.file_is_gone', false)
            ->where('jobs.1.download_url', fn ($url) => str_contains((string) $url, '/reporting/export/'.$ada->id.'/') && str_contains((string) $url, 'signature='))
            ->where('jobs.0.id', $hilang->id)
            ->where('jobs.0.file_is_gone', true)
            ->where('jobs.0.download_url', null));
});

it('isi ekspor tidak memuat kolom di luar definisi', function () {
    Storage::fake(ExportWriter::DISK);
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run(exportOperator(), exportOf(ExportType::SUBMISSIONS));

    expect($job->file_path)->toEndWith('.xlsx')
        ->and(exportedSheet($job)[0])->toBe(ExportType::definition(ExportType::SUBMISSIONS)->headings())
        ->and(exportedSheet($job))->toHaveCount(3);
});

it('format csv menghasilkan berkas .csv', function () {
    Storage::fake(ExportWriter::DISK);
    submissionsFor(User::factory()->create(), 1);

    $job = RequestExport::run(exportOperator(), exportOf(ExportType::SUBMISSIONS, ExportFormat::Csv));

    expect($job->file_path)->toEndWith('.csv')
        ->and($job->format)->toBe('csv')
        ->and(strtok((string) Storage::disk(ExportWriter::DISK)->get((string) $job->file_path), "\n"))
        ->toBe('"ID","Judul","Status","Skema","Ketua","Tanggal Submit","Total Anggaran"');
});

it('[RPT-EXPORT-12] filter ekspor tidak melebarkan scope peneliti', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();
    $skemaSari = Scheme::factory()->create();
    submissionsFor($budi, 1);
    submissionsFor($sari, 1, ['scheme_id' => $skemaSari->id]);
    $p001 = FormSubmission::query()->where('submitted_by', $budi->id)->sole();
    $p007 = FormSubmission::query()->where('submitted_by', $sari->id)->sole();

    $approved = RequestExport::run($budi, exportOf(ExportType::SUBMISSIONS, filters: new ExportFilters(status: 'approved')));
    $ids = array_column(array_slice(exportedSheet($approved), 1), 0);

    expect($ids)->toContain($p001->id)->not->toContain($p007->id);

    $skemaLain = RequestExport::run($budi, exportOf(ExportType::SUBMISSIONS, filters: new ExportFilters(scheme: $skemaSari->id, status: 'approved')));

    expect($skemaLain->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($skemaLain->row_count)->toBe(0)
        ->and(exportedSheet($skemaLain))->toHaveCount(1);
});

it('[RPT-EXPORT-13] ambang dihitung dari baris setelah filter', function () {
    Bus::fake();
    $skemaA = Scheme::factory()->create();
    $pemilik = User::factory()->create();
    submissionsFor($pemilik, 40, ['scheme_id' => $skemaA->id]);
    submissionsFor($pemilik, 61);

    $job = RequestExport::run(exportOperator(), exportOf(ExportType::SUBMISSIONS, filters: new ExportFilters(scheme: $skemaA->id)));

    // Jalur langsung: Job yang sama di-dispatch sinkron, tidak satu pun masuk antrean.
    expect($job->row_count)->toBe(40)
        ->and(Bus::dispatched(GenerateExport::class))->toHaveCount(0);

    Bus::assertDispatchedSync(GenerateExport::class);
});

it('kunci filter di luar jenisnya dibuang', function () {
    Storage::fake(ExportWriter::DISK);

    $job = RequestExport::run(exportOperator(), exportOf(ExportType::AUDIT_LOG, filters: new ExportFilters(yearFrom: 2025, status: 'approved')));

    expect($job->filters)->toBe(['year_from' => 2025]);
});

it('ekspor audit peneliti ter-scope AuditScope', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();
    submissionsFor($budi, 1);
    submissionsFor($sari, 1);
    foreach (FormSubmission::all() as $submission) {
        AuditTrail::record('Pengajuan disubmit', $submission, $submission->submittedBy);
    }
    $p001 = FormSubmission::query()->where('submitted_by', $budi->id)->sole();
    $p007 = FormSubmission::query()->where('submitted_by', $sari->id)->sole();
    // Subjek ditulis lewat judul form (W155); factory memberi semua form judul yang sama.
    $p001->form->update(['title' => 'Biochar Kaltim']);
    $p007->form->update(['title' => 'Smart Farming']);

    $job = RequestExport::run($budi, exportOf(ExportType::AUDIT_LOG));
    $subjects = array_column(array_slice(exportedSheet($job), 1), 1);

    expect($subjects)->toContain('Submission — Biochar Kaltim')
        ->not->toContain('Submission — Smart Farming')
        ->and(AuditEntry::query()->where('description', 'Ekspor diminta')->count())->toBe(1);
});

it('permintaan ekspor divalidasi', function (array $payload, string $field) {
    $this->actingAs(exportOperator())
        ->post(route('reporting.exports.store'), ['type' => ExportType::SUBMISSIONS, 'format' => 'xlsx', ...$payload])
        ->assertSessionHasErrors($field);
})->with([
    'jenis tak dikenal' => [['type' => 'semua-tabel'], 'type'],
    'jenis cetak' => [['type' => 'lembar-penilaian'], 'type'],
    'format tak dikenal' => [['format' => 'pdf'], 'format'],
    'tahun terbalik' => [['year_from' => 2026, 'year_to' => 2025], 'year_to'],
    'status draft' => [['status' => 'draft'], 'status'],
    'skema tak ada' => [['scheme' => 999999], 'scheme'],
]);

it('permintaan ekspor lewat route menyimpan filter dan format', function () {
    Storage::fake(ExportWriter::DISK);

    $this->actingAs(exportOperator())
        ->post(route('reporting.exports.store'), ['type' => ExportType::SUBMISSIONS, 'format' => 'csv', 'year_to' => 2026, 'status' => 'approved'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $job = ExportJob::query()->sole();

    expect($job->format)->toBe('csv')
        ->and($job->filters)->toEqual(['year_to' => 2026, 'status' => 'approved']);
});

it('riwayat ekspor membawa format, filter, parameter, dan batas tautan', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    $dipa = Scheme::factory()->create(['name' => 'DIPA']);
    $this->travelTo('2026-07-02 08:10:00');
    $job = RequestExport::run($operator, exportOf(ExportType::SUBMISSIONS, ExportFormat::Csv, new ExportFilters(2025, 2026, $dipa->id, 'approved')));

    $row = ExportHistory::run($operator)[0];

    expect($row['kind'])->toBe('export')
        ->and($row['format'])->toBe('csv')
        ->and($row['filters'])->toEqual(['year_from' => 2025, 'year_to' => 2026, 'scheme' => $dipa->id, 'status' => 'approved'])
        ->and($row['parameters'])->toBe('2025 – 2026 · DIPA · Approved')
        ->and($row['requested_at'])->toBe($job->created_at?->toIso8601String())
        ->and($row['link_expires_at'])->toBe(now()->addHours(ExportRetention::SIGNED_URL_HOURS)->toIso8601String())
        ->and($row['submission_href'])->toBeNull();
});

it('parameter riwayat menyebut semua tahun dan semua skema bila tanpa filter', function (string $type, string $parameters) {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    RequestExport::run($operator, exportOf($type));

    expect(ExportHistory::run($operator)[0]['parameters'])->toBe($parameters);
})->with([
    'pengajuan' => [ExportType::SUBMISSIONS, 'Semua tahun · Semua skema'],
    'audit' => [ExportType::AUDIT_LOG, 'Semua tahun'],
]);

it('baris cetak di riwayat ditandai print dan menaut ke pengajuannya', function () {
    $operator = exportOperator();
    $submission = reportedSubmission();
    ExportJob::create([
        'requested_by' => $operator->id, 'type' => PrintableDocument::ACCEPTANCE_LETTER, 'status' => ExportJob::STATUS_FAILED,
        'row_count' => 1, 'filters' => ['form_submission_id' => $submission->id],
    ]);

    $row = ExportHistory::run($operator)[0];

    expect($row['kind'])->toBe('print')
        ->and($row['type'])->toBe('Surat Keterangan Diterima')
        ->and($row['parameters'])->toBe('Pengajuan #'.$submission->id)
        ->and($row['submission_href'])->toBe(route('admin.submissions.show', $submission->id));
});
