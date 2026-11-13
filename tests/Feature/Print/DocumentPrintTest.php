<?php

// tests/Feature/Print/DocumentPrintTest.php
//
// BR-RPT-11 — cetak dokumen PDF.
//
// Hak mencetak mengikuti hak membaca pengajuannya, bukan hak tersendiri.
// Kalau cetak punya pintunya sendiri, satu tombol unduh cukup untuk melewati
// seluruh aturan keterlihatan yang dibangun di M2 — dan itu tidak ketahuan
// dari layar mana pun, karena PDF-nya tampak wajar.

use App\Actions\Print\PrintDocument;
use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\PrintableDocumentChoices;
use App\States\Submission\Approved;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportWriter;
use App\Support\Reporting\PrintableDocument;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function printOperator(): User
{
    Permission::findOrCreate(SubmissionVisibility::READ_ALL_PERMISSION, 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo(SubmissionVisibility::READ_ALL_PERMISSION);

    return $operator;
}

function printableSubmission(User $owner, string $status = Approved::class): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $status,
        'is_submitted' => true,
    ]);
}

it('[RPT-PRINT-01] dokumen sederhana dihasilkan langsung', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi);

    $job = PrintDocument::run(printOperator(), PrintableDocument::ACCEPTANCE_LETTER, $submission);

    expect($job->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($job->file_path)->toEndWith('.pdf');

    Storage::disk(ExportWriter::DISK)->assertExists((string) $job->file_path);
});

it('[RPT-PRINT-02] dokumen kompleks masuk background job', function () {
    Queue::fake();
    $budi = verifiedResearcher();

    // Ambangnya sama persis dengan ekspor tabular (BR-RPT-11).
    FormSubmission::factory()->count(ExportThreshold::SYNCHRONOUS_ROWS + 1)->create([
        'submitted_by' => $budi->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $submission = FormSubmission::query()->where('submitted_by', $budi->id)->firstOrFail();

    $job = PrintDocument::run(printOperator(), PrintableDocument::RESEARCHER_RECAP, $submission);

    expect($job->status)->toBe(ExportJob::STATUS_QUEUED)
        ->and($job->row_count)->toBeGreaterThan(ExportThreshold::SYNCHRONOUS_ROWS);

    Queue::assertPushed(GenerateExport::class);
});

it('[RPT-PRINT-02] notifikasi selesai cetak dikirim ke yang meminta', function () {
    Storage::fake(ExportWriter::DISK);
    $peminta = printOperator();
    $operatorLain = printOperator();
    $submission = printableSubmission(verifiedResearcher());

    PrintDocument::run($peminta, PrintableDocument::ACCEPTANCE_LETTER, $submission);

    expect($peminta->notifications()->count())->toBe(1)
        ->and($operatorLain->notifications()->count())->toBe(0);
});

it('[RPT-PRINT-03] cetak tidak membuka skor yang masih ditahan', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi, UnderReview::class);

    expect(fn () => PrintDocument::run($budi, PrintableDocument::SCORE_SHEET, $submission))
        ->toThrow(AuthorizationException::class);

    expect(ExportJob::query()->count())->toBe(0);
});

it('[RPT-PRINT-04] Lembar Penilaian terbuka setelah keputusan akhir', function () {
    Storage::fake(ExportWriter::DISK);
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi, Approved::class);

    $job = PrintDocument::run($budi, PrintableDocument::SCORE_SHEET, $submission);

    expect($job->status)->toBe(ExportJob::STATUS_COMPLETED);
});

it('[RPT-PRINT-05] cetak pengajuan orang lain ditolak', function () {
    $budi = verifiedResearcher();
    $sari = verifiedResearcher();
    $milikSari = printableSubmission($sari);

    expect(fn () => PrintDocument::run($budi, PrintableDocument::ACCEPTANCE_LETTER, $milikSari))
        ->toThrow(AuthorizationException::class);
});

it('[RPT-PRINT-05] jalur HTTP juga menolak dengan 403', function () {
    $budi = verifiedResearcher();
    $milikSari = printableSubmission(verifiedResearcher());

    $this->actingAs($budi)
        ->post(route('submissions.print', [$milikSari->id, PrintableDocument::ACCEPTANCE_LETTER]))
        ->assertForbidden();
});

it('[RPT-PRINT-06] Laporan Monev bisa dicetak begitu evaluasinya masuk', function () {
    Storage::fake(ExportWriter::DISK);
    $fx = monevFixture();

    $job = PrintDocument::run($fx['owner'], PrintableDocument::MONEV_REPORT, $fx['parent']);

    expect($job->status)->toBe(ExportJob::STATUS_COMPLETED)
        ->and($job->file_path)->toEndWith('.pdf');
});

it('menolak dokumen yang tidak dikenal dengan 404', function () {
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi);

    $this->actingAs($budi)
        ->post(route('submissions.print', [$submission->id, 'sertifikat-palsu']))
        ->assertNotFound();
});

it('berkas cetak ikut retensi 24 jam yang sama dengan ekspor', function () {
    Storage::fake(ExportWriter::DISK);
    $submission = printableSubmission(verifiedResearcher());

    $job = PrintDocument::run(printOperator(), PrintableDocument::ACCEPTANCE_LETTER, $submission);
    $path = (string) $job->file_path;

    $this->travel(25)->hours();
    $this->artisan('exports:prune')->assertSuccessful();

    Storage::disk(ExportWriter::DISK)->assertMissing($path);
    expect($job->refresh()->file_deleted_at)->not->toBeNull();
});

it('menawarkan hanya dokumen yang boleh dicetak orang itu', function () {
    // Menawarkan semuanya lalu menolak saat ditekan membocorkan bahwa
    // dokumennya ada — itu sudah setengah jawaban yang ditahan BR-REV-12.
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi, UnderReview::class);

    $this->actingAs($budi)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where(
                'printableDocuments',
                fn ($docs) => collect($docs)->pluck('value')->doesntContain(PrintableDocument::SCORE_SHEET)
            )
            ->etc());
});

it('menawarkan Lembar Penilaian setelah keputusan akhir', function () {
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi, Approved::class);

    $this->actingAs($budi)
        ->get(route('user.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where(
                'printableDocuments',
                fn ($docs) => collect($docs)->pluck('value')->contains(PrintableDocument::SCORE_SHEET)
            )
            ->etc());
});

it('[RPT-PRINT-07] Surat Keterangan Diterima ditolak sebelum pengajuan disetujui', function () {
    $submission = printableSubmission(verifiedResearcher(), UnderReview::class);

    expect(fn () => PrintDocument::run(printOperator(), PrintableDocument::ACCEPTANCE_LETTER, $submission))
        ->toThrow(AuthorizationException::class, 'Surat Keterangan Diterima dapat dicetak setelah pengajuan disetujui.');

    $this->actingAs(printOperator())
        ->post(route('submissions.print', [$submission->id, PrintableDocument::ACCEPTANCE_LETTER]))
        ->assertForbidden();
});

it('[RPT-PRINT-08] Surat Keterangan Diterima hanya ditawarkan pada pengajuan APPROVED', function () {
    $budi = verifiedResearcher();
    $submission = printableSubmission($budi, Submitted::class);
    $offered = fn (): bool => collect(PrintableDocumentChoices::run($budi, $submission->fresh()))
        ->pluck('value')
        ->contains(PrintableDocument::ACCEPTANCE_LETTER);

    expect($offered())->toBeFalse();

    $submission->status->transitionTo(UnderReview::class);
    expect($offered())->toBeFalse();

    $submission->refresh()->status->transitionTo(Approved::class);
    expect($offered())->toBeTrue();
});
