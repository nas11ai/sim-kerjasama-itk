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
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** @return array{0: User, 1: FormSubmission, 2: FormSubmission} */
function rejectionSliceFixture(): array
{
    $owner = User::factory()->create();
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
        $this->fail('Submit seharusnya ditolak karena sudah ada pengajuan aktif.');
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

it('[SM-SUBMIT-17] submit kedua ditolak dengan pesan yang sama, bukan galat mentah', function () {
    $owner = User::factory()->create();
    $period = SubmissionPeriod::factory()->create();
    $scheme = Scheme::factory()->create();

    // Dua DRAFT ke period+scheme yang sama. Status disetel saat pembuatan —
    // bukan lewat update(['status' => ...]), yang dilarang.
    $pertama = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);
    $kedua = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'submission_period_id' => $period->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    SubmitFormSubmission::run($pertama, $owner);
    expect($pertama->fresh()->status)->toBeInstanceOf(Submitted::class);

    $tertangkap = null;

    try {
        SubmitFormSubmission::run($kedua->fresh(), $owner);
    } catch (Throwable $e) {
        $tertangkap = $e;
    }

    // Inti BR-SM-16: yang sampai ke peneliti adalah pesan validasi yang bisa
    // ditindaklanjuti — BUKAN QueryException dari index.
    expect($tertangkap)->toBeInstanceOf(ValidationException::class)
        ->and($tertangkap)->not->toBeInstanceOf(QueryException::class)
        ->and($tertangkap->errors())->toHaveKey('scheme_id');

    expect($kedua->fresh()->status)->toBeInstanceOf(Draft::class);
});
