<?php

// tests/Feature/Submission/SubmitRejectionMessageTest.php
//
// BR-SM-16 — penolakan submit harus bisa ditindaklanjuti peneliti, dan draft
// yang gagal tidak boleh hilang atau terkunci.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: FormSubmission} */
function rejectionSliceFixture(): array
{
    $owner = verifiedResearcher();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    $aktif = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Submitted::class,
        'is_submitted' => true,
    ]);
    $draft = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    return [$owner, $aktif, $draft];
}

it('[SM-SUBMIT-15] penolakan menyebut pengajuan aktif yang sudah ada', function () {
    [$owner, $aktif, $draft] = rejectionSliceFixture();

    try {
        SubmitFormSubmission::run($draft, $owner);
        $this->fail('Kirim seharusnya ditolak karena sudah ada pengajuan aktif.');
    } catch (ValidationException $e) {
        $pesan = implode(' ', $e->errors()['scheme_id']);

        expect($pesan)->toContain((string) $aktif->id)
            ->and($pesan)->toContain(route('user.submissions.show', $aktif));
    }
});

it('[SM-SUBMIT-16] draft yang gagal disubmit tetap bisa disunting', function () {
    [$owner, , $draft] = rejectionSliceFixture();

    expect(fn () => SubmitFormSubmission::run($draft, $owner))
        ->toThrow(ValidationException::class);

    $segar = $draft->fresh();

    expect($segar->status)->toBeInstanceOf(Draft::class)
        ->and($segar->is_submitted)->toBeFalse()
        ->and($owner->can('update', $segar))->toBeTrue();
});

it('[SM-SUBMIT-17] index database yang menangkap memberi pesan yang sama', function () {
    $owner = verifiedResearcher();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    // Dua DRAFT ke period+scheme yang sama. Status disetel saat pembuatan —
    // bukan lewat update(['status' => ...]), yang dilarang.
    $p001 = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);
    $p002 = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    // "Secara bersamaan" dibuat deterministik tanpa thread: submit P-001
    // disisipkan tepat di jendela lomba-balap milik P-002 — setelah SELECT
    // pemeriksaan awal P-002 selesai, sebelum SAVEPOINT tulisannya dibuka.
    // Itu satu-satunya jendela yang tidak bisa dijaga pemeriksaan awal, dan
    // satu-satunya jendela yang menyerahkan perkara ke index.
    //
    // Jendelanya harus di SINI dan bukan di event `updating`: `updating`
    // menyala di dalam savepoint, sehingga baris pemenang ikut hilang saat
    // ROLLBACK TO SAVEPOINT — dan pemulihannya jadi tidak punya apa pun untuk
    // ditunjuk.
    $balapanBerjalan = false;
    $aktifSaatPemeriksaan = null;

    DB::listen(function (QueryExecuted $query) use (&$balapanBerjalan, &$aktifSaatPemeriksaan, $p001, $owner): void {
        if ($balapanBerjalan || !str_starts_with($query->sql, 'select')) {
            return;
        }

        if (!str_contains($query->sql, 'from "form_submissions"') || !str_contains($query->sql, '"is_submitted"')) {
            return;
        }

        $balapanBerjalan = true;

        // Bukti bahwa pemeriksaan awal P-002 memang lolos: pada detik ia
        // membaca tabel, belum ada satu pun pengajuan aktif. Penolakan yang
        // muncul nanti karena itu tidak mungkin datang dari pemeriksaan awal.
        $aktifSaatPemeriksaan = FormSubmission::query()->where('is_submitted', true)->count();

        SubmitFormSubmission::run($p001->fresh(), $owner);
    });

    $tertangkap = null;

    try {
        SubmitFormSubmission::run($p002, $owner);
    } catch (Throwable $e) {
        $tertangkap = $e;
    }

    expect($balapanBerjalan)->toBeTrue()
        ->and($aktifSaatPemeriksaan)->toBe(0);

    // Then: satu submit berhasil.
    expect($p001->fresh()->status)->toBeInstanceOf(Submitted::class);

    // Then: submit lainnya ditolak karena sudah ada pengajuan aktif pada skema
    // itu, dan penolakannya bukan galat database mentah — yang sampai ke
    // peneliti pesan validasi yang sama persis dengan jalur pemeriksaan awal.
    expect($tertangkap)->toBeInstanceOf(ValidationException::class)
        ->and($tertangkap)->not->toBeInstanceOf(QueryException::class)
        ->and($tertangkap->errors())->toHaveKey('scheme_id');

    $pesan = implode(' ', $tertangkap->errors()['scheme_id']);

    expect($pesan)->toContain((string) $p001->id)
        ->and($pesan)->toContain(route('user.submissions.show', $p001));

    $segar = $p002->fresh();

    expect($segar->status)->toBeInstanceOf(Draft::class)
        ->and($segar->is_submitted)->toBeFalse();
});
