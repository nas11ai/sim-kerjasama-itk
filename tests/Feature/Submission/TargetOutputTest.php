<?php

// tests/Feature/Submission/TargetOutputTest.php
//
// BR-SM-14 — dua penyaring bertingkat. Taksonomi `output_type_definitions`
// berlaku selalu; `scheme.rules.allowed_output_types` mempersempitnya lagi.
// Skema tanpa key itu tidak membatasi apa pun, dan pengajuan tanpa skema
// hanya kena penyaring pertama.
//
// BR-SM-15 — target boleh berubah sampai REJECTED atau WITHDRAWN, termasuk
// setelah APPROVED karena target menyesuaikan jalannya kegiatan. Hanya
// `submitted_by` yang boleh; perubahan pasca-APPROVED wajib tercatat.

use App\Actions\FormSubmission\DeleteTargetOutput;
use App\Actions\FormSubmission\SaveTargetOutput;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\Scheme;
use App\Models\SubmissionTargetOutput;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function targetTaxonomy(): void
{
    foreach ([
        'publikasi' => 'Publikasi Ilmiah',
        'haki' => 'HAKI',
        'buku' => 'Buku',
    ] as $code => $name) {
        OutputTypeDefinition::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
    }
}

/** @return array{0: User, 1: FormSubmission} */
function targetFixture(string $state = Draft::class, bool $withScheme = true): array
{
    targetTaxonomy();

    $owner = User::factory()->create();

    $scheme = $withScheme
        ? Scheme::factory()->create(['rules' => ['allowed_output_types' => ['publikasi', 'haki']]])
        : null;

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => $scheme?->id,
        'status' => $state,
    ]);

    return [$owner, $submission];
}

it('[SM-TARGET-01] memilih tipe luaran yang terdaftar', function () {
    [$owner, $submission] = targetFixture();

    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');

    expect($target->output_type)->toBe('publikasi')
        ->and($submission->targetOutputs()->count())->toBe(1);
});

it('[SM-TARGET-02] tipe di luar daftar definisi ditolak', function () {
    [$owner, $submission] = targetFixture();

    SaveTargetOutput::run($submission, $owner, 'podcast');
})->throws(ValidationException::class);

it('[SM-TARGET-03] tipe di luar izin skema ditolak', function () {
    [$owner, $submission] = targetFixture();

    // "buku" terdaftar di taksonomi, tapi skema ini hanya mengizinkan
    // publikasi dan haki — penyaring kedua yang menolaknya.
    SaveTargetOutput::run($submission, $owner, 'buku');
})->throws(ValidationException::class);

it('[SM-TARGET-04] pengajuan tanpa skema hanya dibatasi daftar definisi', function () {
    [$owner, $submission] = targetFixture(withScheme: false);

    $target = SaveTargetOutput::run($submission, $owner, 'buku');

    expect($target->output_type)->toBe('buku');
});

it('[SM-TARGET-04] skema tanpa allowed_output_types tidak membatasi apa pun', function () {
    targetTaxonomy();

    $owner = User::factory()->create();
    $scheme = Scheme::factory()->create(['rules' => ['min_reviewer_count' => 2]]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => $scheme->id,
        'status' => Draft::class,
    ]);

    expect(SaveTargetOutput::run($submission, $owner, 'buku')->output_type)->toBe('buku');
});

it('[SM-TARGET-05] target bisa diubah saat draft', function () {
    [$owner, $submission] = targetFixture();
    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');

    SaveTargetOutput::run($submission, $owner, 'haki', $target);

    expect($target->fresh()->output_type)->toBe('haki');
});

it('[SM-TARGET-06] target bisa diubah setelah disetujui', function () {
    [$owner, $submission] = targetFixture(Approved::class);
    $target = SubmissionTargetOutput::create([
        'form_submission_id' => $submission->id,
        'output_type' => 'publikasi',
    ]);

    // Target menyesuaikan jalannya kegiatan — APPROVED tidak menguncinya.
    SaveTargetOutput::run($submission, $owner, 'haki', $target);

    expect($target->fresh()->output_type)->toBe('haki');
});

it('[SM-TARGET-07] target terkunci pada status mati', function (string $state) {
    [$owner, $submission] = targetFixture($state);
    $target = SubmissionTargetOutput::create([
        'form_submission_id' => $submission->id,
        'output_type' => 'publikasi',
    ]);

    SaveTargetOutput::run($submission, $owner, 'haki', $target);
})->with([Rejected::class, Withdrawn::class])->throws(AuthorizationException::class);

it('[SM-TARGET-08] co-investigator tidak bisa mengubah target', function () {
    [, $submission] = targetFixture();

    $coInvestigator = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $coInvestigator->id]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    // Sengaja berbeda dari BR-RO-07, yang justru membolehkan co-investigator
    // mengisi luaran realisasi. Mengubah janji bukan tindakan yang sama
    // dengan mengisi hasil.
    SaveTargetOutput::run($submission, $coInvestigator, 'haki');
})->throws(AuthorizationException::class);

it('[SM-TARGET-09] perubahan pasca-approved tercatat dengan nilai lama dan baru', function () {
    [$owner, $submission] = targetFixture(Approved::class);
    $target = SubmissionTargetOutput::create([
        'form_submission_id' => $submission->id,
        'output_type' => 'publikasi',
    ]);

    SaveTargetOutput::run($submission, $owner, 'haki', $target);

    $entry = Activity::query()
        ->where('subject_type', SubmissionTargetOutput::class)
        ->where('subject_id', $target->id)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties['old']['output_type'])->toBe('publikasi')
        ->and($entry->properties['attributes']['output_type'])->toBe('haki');
});

it('[SM-TARGET-09] mengubah target dicatat di jejak audit pengajuan dengan nilai lama dan baru', function () {
    [$owner, $submission] = targetFixture(Approved::class);
    $target = SubmissionTargetOutput::create([
        'form_submission_id' => $submission->id,
        'output_type' => 'publikasi',
    ]);

    SaveTargetOutput::run($submission, $owner, 'haki', $target);

    $entry = AuditEntry::query()->auditOnly()
        ->where('subject_type', FormSubmission::class)
        ->where('subject_id', $submission->id)
        ->where('description', 'Target luaran diubah')
        ->sole();

    expect($entry->properties['old']['output_type'])->toBe('publikasi')
        ->and($entry->properties['new']['output_type'])->toBe('haki');
});

// ---------- BR-SM-19: menghapus target luaran ----------

it('[SM-TARGET-10] ketua pengaju menghapus target luaran', function () {
    [$owner, $submission] = targetFixture();

    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');

    DeleteTargetOutput::run($submission, $target, $owner);

    expect(SubmissionTargetOutput::query()->whereKey($target->id)->exists())->toBeFalse();
});

it('[SM-TARGET-11] target tetap bisa dihapus setelah pengajuan disetujui', function () {
    [$owner, $submission] = targetFixture(Approved::class);

    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');

    // BR-SM-15 membiarkan target menyesuaikan jalannya kegiatan; pencabutan
    // adalah bentuk penyesuaian yang paling jujur saat luarannya batal.
    DeleteTargetOutput::run($submission, $target, $owner);

    expect(SubmissionTargetOutput::query()->whereKey($target->id)->exists())->toBeFalse();
});

it('[SM-TARGET-12] target tidak bisa dihapus setelah pengajuan mati', function (string $state) {
    [$owner, $submission] = targetFixture();
    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');

    $submission->update(['status' => $state]);

    expect(fn () => DeleteTargetOutput::run($submission->fresh(), $target, $owner))
        ->toThrow(AuthorizationException::class)
        ->and(SubmissionTargetOutput::query()->whereKey($target->id)->exists())->toBeTrue();
})->with([Rejected::class, Withdrawn::class]);

it('[SM-TARGET-13] co-investigator tidak bisa menghapus target', function () {
    [$owner, $submission] = targetFixture();
    $target = SaveTargetOutput::run($submission, $owner, 'publikasi');

    $sari = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $sari->id])->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    // Sengaja berbeda dari BR-RO-07 pada luaran realisasi: mengubah janji
    // bukan tindakan yang sama dengan mengisi hasil.
    expect(fn () => DeleteTargetOutput::run($submission, $target, $sari))
        ->toThrow(AuthorizationException::class)
        ->and(SubmissionTargetOutput::query()->whereKey($target->id)->exists())->toBeTrue();
});
