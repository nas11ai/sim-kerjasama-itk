<?php

// tests/Feature/Export/ExportDefinitionsTest.php
//
// W154 — lima jenis ekspor DDD, masing-masing dengan kolomnya sendiri. Isi berkas
// adalah kolom definisi, bukan `attributesToArray()` tabel (JSON, flag internal, id asing).

use App\Data\Reporting\ExportFilters;
use App\Enums\DeadlineType;
use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchOutput;
use App\Models\Scheme;
use App\Models\User;
use App\Queries\Monev\MonevCycles;
use App\States\Submission\Approved;
use App\Support\Audit\AuditTrail;
use App\Support\Reporting\ExportType;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function definitionsOperator(): User
{
    Permission::findOrCreate(SubmissionVisibility::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(SubmissionVisibility::READ_ALL_PERMISSION);

    return $operator;
}

/** P-001 contoh plan W154: Biochar Kaltim, DIPA, Budi, submit 12 Feb 2026, anggaran Rp 6.500.000. */
function biocharSubmission(): FormSubmission
{
    $budi = User::factory()->create(['name' => 'Budi']);

    $submission = reportedSubmission([
        'form_id' => Form::factory()->create(['title' => 'Biochar Kaltim'])->id,
        'scheme_id' => Scheme::factory()->create(['name' => 'DIPA'])->id,
        'submitted_by' => $budi->id,
        'status' => Approved::class,
    ], '2026-02-12 08:00:00');

    $bahan = BudgetComponent::factory()->create(['name' => 'Bahan']);
    BudgetLineItem::factory()->for($submission)->create([
        'budget_component_id' => $bahan->id, 'item_name' => 'Arang sekam', 'volume' => 10,
        'unit' => 'kg', 'unit_price' => 400_000, 'total' => 4_000_000,
    ]);
    BudgetLineItem::factory()->for($submission)->create([
        'budget_component_id' => $bahan->id, 'item_name' => 'Pupuk', 'volume' => 5,
        'unit' => 'karung', 'unit_price' => 500_000, 'total' => 2_500_000,
    ]);
    // Baris terhapus tidak ikut total maupun Detail Anggaran.
    BudgetLineItem::factory()->for($submission)->create(['budget_component_id' => $bahan->id, 'total' => 999])->delete();

    return $submission;
}

it('kolom tiap jenis ekspor mengikuti W154', function (string $type, array $headings) {
    expect(ExportType::definition($type)->headings())->toBe($headings);
})->with([
    'Rekap Submission' => [ExportType::SUBMISSIONS, ['ID', 'Judul', 'Status', 'Skema', 'Ketua', 'Tanggal Submit', 'Total Anggaran']],
    'Detail Anggaran' => [ExportType::BUDGET, ['ID Pengajuan', 'Judul', 'Komponen', 'Item', 'Volume', 'Satuan', 'Harga Satuan', 'Total']],
    'Daftar Luaran' => [ExportType::RESEARCH_OUTPUTS, ['ID Pengajuan', 'Judul Pengajuan', 'Tipe Luaran', 'Judul Luaran', 'Dibuat']],
    'Rekap Monev' => [ExportType::MONEV, ['ID Pengajuan', 'Judul', 'Siklus', 'Status Siklus']],
    'Log Audit' => [ExportType::AUDIT_LOG, ['Waktu', 'Subjek', 'Aksi', 'Pelaku', 'IP Address']],
]);

it('label kelima jenis ekspor mengikuti tabel DDD', function () {
    expect(array_map(ExportType::label(...), ExportType::all()))
        ->toBe(['Rekap Submission', 'Detail Anggaran', 'Daftar Luaran', 'Rekap Monev', 'Log Audit']);
});

it('baris Rekap Submission', function () {
    $submission = biocharSubmission();

    expect(ExportType::definition(ExportType::SUBMISSIONS)->rows(definitionsOperator(), new ExportFilters))
        ->toBe([[$submission->id, 'Biochar Kaltim', 'Disetujui', 'DIPA', 'Budi', '2026-02-12', 6_500_000]]);
});

it('Rekap Submission tidak memuat Draft dan laporan monev', function () {
    $induk = reportedSubmission();
    FormSubmission::factory()->create(['is_submitted' => false]);
    reportedSubmission(['parent_submission_id' => $induk->id]);

    $definition = ExportType::definition(ExportType::SUBMISSIONS);
    $operator = definitionsOperator();

    expect($definition->rowCount($operator, new ExportFilters))->toBe(1)
        ->and(array_column($definition->rows($operator, new ExportFilters), 0))->toBe([$induk->id]);
});

it('baris Detail Anggaran per item tanpa baris terhapus', function () {
    $submission = biocharSubmission();
    $definition = ExportType::definition(ExportType::BUDGET);

    expect($definition->rows(definitionsOperator(), new ExportFilters))->toBe([
        [$submission->id, 'Biochar Kaltim', 'Bahan', 'Arang sekam', 10, 'kg', 400_000, 4_000_000],
        [$submission->id, 'Biochar Kaltim', 'Bahan', 'Pupuk', 5, 'karung', 500_000, 2_500_000],
    ])->and($definition->rowCount(definitionsOperator(), new ExportFilters))->toBe(2);
});

it('baris Daftar Luaran memakai judul dari spesifikasi tipe', function () {
    $submission = biocharSubmission();
    OutputTypeDefinition::create([
        'code' => 'artikel-jurnal', 'name' => 'Artikel Jurnal', 'is_active' => true, 'requires_files' => false,
        'fields' => [['key' => 'judul', 'label' => 'Judul Artikel', 'type' => 'text', 'required' => true]],
    ]);
    $this->travelTo('2026-09-01 10:00:00');
    ResearchOutput::create([
        'form_submission_id' => $submission->id, 'output_type' => 'artikel-jurnal',
        'metadata' => ['judul' => 'Biochar untuk Lahan Gambut'], 'created_by' => $submission->submitted_by,
    ]);
    $this->travelBack();

    expect(ExportType::definition(ExportType::RESEARCH_OUTPUTS)->rows(definitionsOperator(), new ExportFilters))
        ->toBe([[$submission->id, 'Biochar Kaltim', 'Artikel Jurnal', 'Biochar untuk Lahan Gambut', '2026-09-01']]);
});

it('baris Rekap Monev per siklus dengan statusnya', function () {
    $fx = monevFixture();
    $definition = ExportType::definition(ExportType::MONEV);
    $operator = definitionsOperator();
    $title = $fx['parent']->form->title;

    expect($definition->rows($operator, new ExportFilters))->toBe([
        [$fx['parent']->id, $title, 'Monev I', 'Terbuka'],
        [$fx['parent']->id, $title, 'Monev II', 'Terkunci'],
    ])
        // BR-RPT-02 — ambang dihitung dari baris berkas (siklus), bukan jumlah induk.
        ->and($definition->rowCount($operator, new ExportFilters))->toBe(2);
});

it('membaca kalender periode semua induk Rekap Monev sekali, bukan per induk', function () {
    monevFixture();
    monevFixture();
    monevFixture();
    $operator = definitionsOperator();

    DB::enableQueryLog();
    $rows = ExportType::definition(ExportType::MONEV)->rows($operator, new ExportFilters);
    $reads = fn (string $table): int => collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], "from \"{$table}\""))
        ->count();

    expect($rows)->toHaveCount(6)
        ->and($reads('submission_periods'))->toBe(1)
        ->and($reads('submission_dates'))->toBe(1);
});

it('menghitung siklus ekspor rekap dari langkah, bukan tanggal', function () {
    // BR-FE-07 (ADR 0019) — langkah menyebut jenis + siklus; tak ada lagi tanggal yang dirujuk.
    $phase = FormPhase::create(['title' => 'Fase Rekap', 'is_active' => true]);
    $pengajuan = scheduleStep($phase, order: 1);
    scheduleStep($phase, cycle: 1, needsReview: false, order: 3);
    scheduleStep($phase, cycle: 2, needsReview: false, order: 5);
    scheduleStep($phase, needsReview: false, order: 7, type: DeadlineType::Output);
    $parent = scheduledSubmission($pengajuan, schedulePeriod($phase, 'Periode Rekap'), [
        'status' => Approved::class, 'is_submitted' => true,
    ]);

    expect(ExportType::definition(ExportType::MONEV)->rowCount(definitionsOperator(), new ExportFilters))
        ->toBe(count(MonevCycles::details($parent)))
        ->and(count(MonevCycles::details($parent)))->toBe(2);
});

it('baris Log Audit', function () {
    $submission = biocharSubmission();
    $this->travelTo('2026-03-01 09:15:00');
    AuditTrail::record('Ekspor diminta', $submission, $submission->submittedBy);
    $this->travelBack();

    $rows = ExportType::definition(ExportType::AUDIT_LOG)->rows(definitionsOperator(), new ExportFilters(yearFrom: 2026, yearTo: 2026));

    // Terbaru dulu; label subjek dan pelaku sama dengan layar Log Audit (W155).
    expect($rows[0])->toBe(['2026-03-01 09:15:00', 'Submission · Biochar Kaltim', 'Ekspor diminta', 'Budi', null])
        ->and(array_column($rows, 2))->toBe(['Ekspor diminta', 'Pengajuan disubmit']);
});

it('[E2E-08] Log Audit menulis nama pelaku yang sudah soft-delete', function () {
    $submission = biocharSubmission();
    AuditTrail::record('Ekspor diminta', $submission, $submission->submittedBy);
    // BR-IAM-19 melarang menghapus akun; baris ber-deleted_at hanya warisan data lama.
    User::query()->whereKey($submission->submitted_by)->update(['deleted_at' => now()]);

    $rows = ExportType::definition(ExportType::AUDIT_LOG)->rows(definitionsOperator(), new ExportFilters);

    expect($rows[0][3])->toBe('Budi');
});

it('Log Audit memuat IP hanya bagi pembaca seluruh sistem', function () {
    $submission = biocharSubmission();
    Context::addHidden(AuditTrail::IP_CONTEXT, '10.10.2.14');
    AuditTrail::record('Ekspor diminta', $submission, $submission->submittedBy);
    Context::forgetHidden(AuditTrail::IP_CONTEXT);
    $definition = ExportType::definition(ExportType::AUDIT_LOG);

    // UX pattern 07 — kolom IP hanya untuk Operator/Admin, sama dengan layar (W155).
    expect($definition->rows(definitionsOperator(), new ExportFilters)[0][4])->toBe('10.10.2.14')
        ->and($definition->rows($submission->submittedBy, new ExportFilters)[0][4])->toBeNull();
});

it('Log Audit tanpa pelaku ditulis Sistem', function () {
    $submission = reportedSubmission();
    AuditTrail::record('Berkas ekspor dihapus', $submission);

    $rows = ExportType::definition(ExportType::AUDIT_LOG)->rows(definitionsOperator(), new ExportFilters);

    expect(array_column($rows, 3))->toContain('Sistem');
});

it('filter rentang tahun, skema, dan status pada jenis berbasis pengajuan', function () {
    $dipa = Scheme::factory()->create();
    $cocok = reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class], '2025-05-01 08:00:00');
    reportedSubmission(['scheme_id' => $dipa->id, 'status' => Approved::class], '2023-05-01 08:00:00');
    reportedSubmission(['status' => Approved::class], '2025-05-01 08:00:00');
    reportedSubmission(['scheme_id' => $dipa->id], '2025-05-01 08:00:00');

    $filters = new ExportFilters(yearFrom: 2024, yearTo: 2026, scheme: $dipa->id, status: 'approved');
    $rows = ExportType::definition(ExportType::SUBMISSIONS)->rows(definitionsOperator(), $filters);

    expect(array_column($rows, 0))->toBe([$cocok->id]);
});

it('filter rentang tahun Log Audit memakai waktu entri', function () {
    $submission = reportedSubmission([], '2024-06-01 08:00:00');
    $this->travelTo('2026-01-05 08:00:00');
    AuditTrail::record('Ekspor diminta', $submission);
    $this->travelBack();

    $rows = ExportType::definition(ExportType::AUDIT_LOG)
        ->rows(definitionsOperator(), new ExportFilters(yearFrom: 2025));

    expect(array_column($rows, 2))->toBe(['Ekspor diminta']);
});

it('jenis Log Audit hanya menerima filter tahun', function () {
    expect(ExportType::definition(ExportType::AUDIT_LOG)->filterKeys())->toBe(['year'])
        ->and(ExportType::definition(ExportType::SUBMISSIONS)->filterKeys())->toBe(['year', 'scheme', 'status']);
});
