<?php

// BR-IAM-05 (W59) — deaktivasi berlaku seketika: sesi yang sedang login berakhir di request
// berikutnya, akun nonaktif tidak menerima penugasan, pengganti, kepemilikan, maupun
// keanggotaan baru — sementara penugasan yang sudah berjalan tetap ada sampai diganti.

use App\Actions\FormSubmission\AddResearchMember;
use App\Actions\FormSubmission\DeactivateUser;
use App\Actions\FormSubmission\TransferSubmissionOwnership;
use App\Actions\Review\AssignReviewer;
use App\Actions\Review\ReplaceReviewer;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\DeactivationBlockers;
use App\Queries\Review\ManagedReviewers;
use App\Queries\Submission\OwnershipCandidates;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function effectSubmission(): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => null,
        'status' => UnderReview::class,
    ]);
}

function reviewerInTenure(bool $active = true): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create(['is_active' => $active])->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
}

function seatFor(FormSubmission $submission, Reviewer $reviewer): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
}

function sessionRow(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp,
    ]);
}

it('[IAM-DEACT-09] permintaan berikutnya dari user yang dinonaktifkan ditolak', function () {
    $sari = User::factory()->create(['is_active' => true]);
    $this->actingAs($sari);

    DeactivateUser::run($sari);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Hubungi LPPM bila ini keliru.');
    $this->assertGuest();
});

it('[IAM-DEACT-09] cookie ingat-saya lama tidak lagi memasukkan user', function () {
    $sari = User::factory()->create(['is_active' => true]);
    $oldToken = $sari->remember_token;
    $recaller = $sari->id.'|'.$oldToken.'|'.$sari->getAuthPassword();

    DeactivateUser::run($sari);

    expect($sari->fresh()->remember_token)->not->toBe($oldToken);
    $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

it('deaktivasi mengakhiri sesi database user itu saja', function () {
    $sari = User::factory()->create(['is_active' => true]);
    $budi = User::factory()->create(['is_active' => true]);
    sessionRow($sari, 'sari-laptop');
    sessionRow($sari, 'sari-hp');
    sessionRow($budi, 'budi-laptop');

    DeactivateUser::run($sari);

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['budi-laptop']);
    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('description', 'Akun dinonaktifkan')->sole();
    expect($entry->properties['new'])->toBe(['sessions_ended' => 2]);
});

it('[IAM-DEACT-04] reviewer dalam masa tugas yang akunnya nonaktif tidak bisa ditugaskan', function () {
    $submission = effectSubmission();
    $reviewer = reviewerInTenure(active: false);

    expect(fn () => AssignReviewer::run($submission, $reviewer))
        ->toThrow(ValidationException::class, 'Akun penilai ini nonaktif, jadi tidak dapat ditugaskan.');
    expect(SubmissionReviewer::query()->where('reviewer_id', $reviewer->id)->exists())->toBeFalse();
});

it('[IAM-DEACT-10] reviewer yang akunnya nonaktif tidak bisa menjadi pengganti', function () {
    $submission = effectSubmission();
    $outgoing = reviewerInTenure();
    $seat = seatFor($submission, $outgoing);

    try {
        ReplaceReviewer::run($submission, $outgoing, reviewerInTenure(active: false), null);
        $this->fail('Penggantian seharusnya ditolak.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('reviewer_id');
    }

    expect($seat->fresh()->status)->toBe('active');
});

it('[IAM-DEACT-11] penugasan berjalan milik reviewer yang dinonaktifkan tetap aktif sampai diganti', function () {
    $submission = effectSubmission();
    $reviewer = reviewerInTenure();
    $seat = seatFor($submission, $reviewer);

    DeactivateUser::run($reviewer->user);

    expect($seat->fresh()->status)->toBe('active')
        ->and($seat->fresh()->evaluation_status)->toBe('pending');
    $rows = ManagedReviewers::run($submission);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->accountActive)->toBeFalse()
        ->and($rows[0]->replaced)->toBeFalse();
});

it('[IAM-DEACT-12] user nonaktif tidak bisa ditambahkan sebagai anggota penelitian', function () {
    $submission = effectSubmission();
    $joko = User::factory()->create(['is_active' => false]);
    UserProfile::factory()->create(['user_id' => $joko->id]);

    expect(fn () => AddResearchMember::run($submission, $joko, ResearchMember::ROLE_MEMBER))
        ->toThrow(ValidationException::class, 'Akun pengguna ini nonaktif, jadi tidak dapat ditambahkan sebagai anggota.');
    expect($submission->researchMembers()->exists())->toBeFalse();
});

it('[SM-OWNER-07] kepemilikan tidak bisa dialihkan ke co-investigator yang akunnya nonaktif', function () {
    $submission = effectSubmission();
    $sari = User::factory()->create(['is_active' => false]);
    $profile = UserProfile::factory()->create(['user_id' => $sari->id]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $profile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    expect(OwnershipCandidates::run($submission))->toBe([])
        ->and(fn () => TransferSubmissionOwnership::run($submission, $sari, 'Ketua pindah institusi'))
        ->toThrow(ValidationException::class);
    expect(DeactivationBlockers::run($submission->submittedBy)->sole()->getAttribute('has_active_co_investigator'))->toBeFalse()
        ->and($submission->fresh()->submitted_by)->not->toBe($sari->id);
});
