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
use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\ExportCompletedNotification;
use App\Notifications\ExportFailedNotification;
use App\States\Submission\Approved;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportType;
use App\Support\Reporting\ExportWriter;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;

function exportOperator(): User
{
    Permission::findOrCreate(SubmissionVisibility::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(SubmissionVisibility::READ_ALL_PERMISSION);

    return $operator;
}

function submissionsFor(User $owner, int $count): void
{
    FormSubmission::factory()->count($count)->create([
        'submitted_by' => $owner->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);
}

it('[RPT-EXPORT-01] ekspor kecil selesai langsung', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), ExportThreshold::SYNCHRONOUS_ROWS);

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);

    expect($job->row_count)->toBe(ExportThreshold::SYNCHRONOUS_ROWS)
        ->and($job->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($job->file_path)->not->toBeNull();

    Storage::disk(ExportWriter::DISK)->assertExists((string) $job->file_path);
});

it('[RPT-EXPORT-02] ekspor besar masuk background job', function () {
    Queue::fake();
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), ExportThreshold::SYNCHRONOUS_ROWS + 1);

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);

    expect($job->status)->toBe(ExportJob::STATUS_QUEUED)
        ->and($job->file_path)->toBeNull();

    Queue::assertPushed(GenerateExport::class);
});

it('[RPT-EXPORT-03] berkas ekspor diakses lewat signed URL 24 jam', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);

    $url = URL::temporarySignedRoute(
        'exports.download',
        now()->addHours(ExportRetention::SIGNED_URL_HOURS),
        ['export' => $job->id],
    );

    $this->actingAs($operator)->get($url)->assertOk();
});

it('[RPT-EXPORT-04] signed URL kedaluwarsa ditolak', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);

    $url = URL::temporarySignedRoute('exports.download', now()->addHour(), ['export' => $job->id]);

    $this->travel(2)->hours();

    $this->actingAs($operator)->get($url)->assertForbidden();
});

it('[RPT-EXPORT-05] notifikasi ekspor hanya ke Operator peminta', function () {
    Storage::fake(ExportWriter::DISK);
    $peminta = exportOperator();
    $operatorLain = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    RequestExport::run($peminta, ExportType::SUBMISSIONS);

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

    $job = RequestExport::run($budi, ExportType::SUBMISSIONS);

    expect($job->row_count)->toBe(3)
        ->and($job->status)->toBe(ExportJob::STATUS_COMPLETED);
});

it('[RPT-EXPORT-09] ekspor Researcher tidak memuat milik orang lain', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();

    submissionsFor($budi, 3);
    submissionsFor($sari, 7);

    $job = RequestExport::run($budi, ExportType::SUBMISSIONS);

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

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);
    $path = (string) $job->file_path;

    $this->travel(ExportRetention::HOURS + 1)->hours();

    $this->artisan('exports:prune')->assertSuccessful();

    Storage::disk(ExportWriter::DISK)->assertMissing($path);

    expect($job->refresh()->file_deleted_at)->not->toBeNull()
        // Barisnya tetap ada: riwayatnya yang memberi tahu Operator bahwa
        // ekspornya pernah jadi lalu kedaluwarsa.
        ->and(ExportJob::query()->whereKey($job->id)->exists())->toBeTrue();

    $url = URL::temporarySignedRoute('exports.download', now()->addHour(), ['export' => $job->id]);

    $this->actingAs($operator)->get($url)->assertStatus(410);
});

it('[RPT-EXPORT-11] berkas yang belum 24 jam masih bisa diunduh', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);

    $this->travel(ExportRetention::HOURS - 1)->hours();

    $this->artisan('exports:prune')->assertSuccessful();

    expect($job->refresh()->file_deleted_at)->toBeNull();

    $url = URL::temporarySignedRoute('exports.download', now()->addHour(), ['export' => $job->id]);

    $this->actingAs($operator)->get($url)->assertOk();
});

it('menolak unduhan berkas milik orang lain meski tautannya sah', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    $orangLain = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $job = RequestExport::run($operator, ExportType::SUBMISSIONS);

    $url = URL::temporarySignedRoute('exports.download', now()->addHour(), ['export' => $job->id]);

    $this->actingAs($orangLain)->get($url)->assertForbidden();
});

it('BR-RPT-03 riwayat ekspor memuat tautan unduh hanya selama berkasnya ada', function () {
    Storage::fake(ExportWriter::DISK);
    $operator = exportOperator();
    submissionsFor(User::factory()->create(), 2);

    $ada = RequestExport::run($operator, ExportType::SUBMISSIONS);
    $hilang = RequestExport::run($operator, ExportType::SUBMISSIONS);
    $hilang->update(['file_deleted_at' => now()]);

    $this->actingAs($operator)
        ->get(route('exports.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Exports/IndexPage')
            ->has('jobs', 2)
            ->where('jobs.1.id', $ada->id)
            ->where('jobs.1.type', ExportType::label($ada->type))
            ->where('jobs.1.type_value', $ada->type)
            ->where('jobs.1.status', ExportJob::STATUS_COMPLETED)
            ->where('jobs.1.file_is_gone', false)
            ->where('jobs.1.download_url', fn ($url) => str_contains((string) $url, '/exports/'.$ada->id.'/') && str_contains((string) $url, 'signature='))
            ->where('jobs.0.id', $hilang->id)
            ->where('jobs.0.file_is_gone', true)
            ->where('jobs.0.download_url', null));
});
