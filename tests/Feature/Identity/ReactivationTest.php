<?php

// BR-IAM-20 — akun nonaktif diaktifkan kembali oleh pemegang wewenang deaktivasi. Yang pulih
// hanya kemampuan login; yang sudah diserahkan selama nonaktif tetap pada tempatnya.

use App\Actions\FormSubmission\DeactivateUser;
use App\Actions\FormSubmission\TransferSubmissionOwnership;
use App\Actions\Identity\ReactivateUser;
use App\Actions\Review\AssignReviewer;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AccountReactivatedNotification;
use App\States\Submission\UnderReview;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function reactivationAdmin(string $permission = 'users.manage'): User
{
    $admin = User::factory()->create();
    Permission::findOrCreate($permission, 'web');
    $admin->givePermissionTo($permission);

    return $admin;
}

function reactivationAudits(User $user): int
{
    return Activity::query()->where('log_name', AuditTrail::LOG_NAME)
        ->where('description', 'Akun diaktifkan kembali')
        ->where('subject_id', $user->id)
        ->count();
}

it('[IAM-REACT-01] akun yang diaktifkan kembali bisa login lagi dengan data utuh', function () {
    Notification::fake();
    Role::findOrCreate('operator', 'web');
    $sari = User::factory()->create(['email' => 'sari@itk.ac.id', 'is_active' => false]);
    $sari->assignRole('operator');
    $profile = UserProfile::factory()->create(['user_id' => $sari->id, 'nidn' => '0987654321']);

    $this->actingAs(reactivationAdmin())->post(route('admin.users.reactivate', $sari))
        ->assertRedirect()
        ->assertSessionHas('success', 'Akun diaktifkan kembali.');

    expect($sari->fresh()->is_active)->toBeTrue()
        ->and($sari->fresh()->hasRole('operator'))->toBeTrue()
        ->and($profile->fresh()->nidn)->toBe('0987654321')
        ->and(reactivationAudits($sari))->toBe(1);
    Notification::assertSentTo($sari, AccountReactivatedNotification::class);

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'sari@itk.ac.id', 'password' => 'password']);
    $this->assertAuthenticatedAs($sari->fresh());
});

it('[IAM-REACT-02] pengaktifan tidak mengembalikan kepemilikan maupun penugasan replaced', function () {
    $budi = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $budi->id]);
    $submission = FormSubmission::factory()->create(['submitted_by' => $budi->id, 'scheme_id' => null, 'status' => UnderReview::class]);
    $sari = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $sari->id])->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);
    $reviewer = Reviewer::factory()->create(['user_id' => $budi->id]);
    $other = FormSubmission::factory()->create(['scheme_id' => null, 'status' => UnderReview::class]);
    $seat = SubmissionReviewer::create(['form_submission_id' => $other->id, 'reviewer_id' => $reviewer->id, 'evaluation_status' => 'pending', 'status' => 'replaced']);
    TransferSubmissionOwnership::run($submission, $sari, 'Ketua cuti panjang');
    DeactivateUser::run($budi);

    ReactivateUser::run(reactivationAdmin(), $budi->fresh());

    expect($submission->fresh()->submitted_by)->toBe($sari->id)
        ->and($seat->fresh()->status)->toBe('replaced');
});

it('[IAM-REACT-03] reviewer yang diaktifkan dan masa tugasnya lewat tetap tidak bisa ditugaskan', function () {
    $andi = User::factory()->create(['is_active' => false]);
    $reviewer = Reviewer::factory()->create([
        'user_id' => $andi->id,
        'start_date' => now()->subYear(),
        'end_date' => now()->subMonth(),
    ]);
    $submission = FormSubmission::factory()->create(['scheme_id' => null, 'status' => UnderReview::class]);

    ReactivateUser::run(reactivationAdmin(), $andi);

    expect(fn () => AssignReviewer::run($submission, $reviewer->fresh()))
        ->toThrow(ValidationException::class, 'Penilai ini berada di luar masa tugasnya, jadi tidak dapat ditugaskan.');
});

it('[IAM-REACT-04] Operator tanpa users.manage tidak bisa mengaktifkan kembali', function () {
    $sari = User::factory()->create(['is_active' => false]);

    $this->actingAs(reactivationAdmin('users.verify'))->post(route('admin.users.reactivate', $sari))->assertForbidden();

    expect($sari->fresh()->is_active)->toBeFalse();
});

it('[IAM-REACT-05] mengaktifkan akun yang sudah aktif tidak mengubah apa pun', function () {
    Notification::fake();
    $budi = User::factory()->create();

    ReactivateUser::run(reactivationAdmin(), $budi);

    expect(reactivationAudits($budi))->toBe(0)
        ->and($budi->fresh()->is_active)->toBeTrue();
    Notification::assertNothingSent();
});

it('kontrak detail memuat can.reactivate', function () {
    $sari = User::factory()->create(['is_active' => false]);
    $admin = reactivationAdmin();

    $this->actingAs($admin)->get(route('admin.users.show', $sari))
        ->assertInertia(fn ($page) => $page->where('can.reactivate', true));
    $this->actingAs(reactivationAdmin('users.verify'))->get(route('admin.users.show', $sari))
        ->assertInertia(fn ($page) => $page->where('can.reactivate', false));
});
