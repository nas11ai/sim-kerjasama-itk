<?php

// tests/Feature/Identity/ReviewerManagementTest.php
//
// BR-IAM-05, BR-IAM-11, BR-IAM-15, BR-REV-09, BR-RPT-09 — layar Operator mengelola
// reviewer lewat AppointReviewer dan ChangeReviewerTenure, bukan menulis model langsung.

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewerAppointedNotification;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::findOrCreate('users.manage');
    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo('users.manage');
});

function calonReviewer(bool $active = true): User
{
    return User::factory()->create(['is_active' => $active]);
}

function auditDeskripsi(string $deskripsi): int
{
    return Activity::inLog(AuditTrail::LOG_NAME)->where('description', $deskripsi)->count();
}

it('BR-IAM-11 — store memberi role reviewer_internal', function () {
    Notification::fake();
    $user = calonReviewer();

    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), [
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
    ])->assertRedirect(route('admin.reviewers.index'));

    expect($user->fresh()->hasRole('reviewer_internal'))->toBeTrue();
});

it('BR-IAM-11 — store untuk reviewer yang masa tugasnya habis memperbarui record yang sama', function () {
    Notification::fake();
    $user = calonReviewer();
    Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->subYear()->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);

    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), [
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $records = Reviewer::where('user_id', $user->id)->get();
    expect($records)->toHaveCount(1)
        ->and($records->first()->end_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('BR-IAM-05 — store untuk akun nonaktif ditolak', function () {
    $user = calonReviewer(false);

    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), [
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
    ])->assertSessionHasErrors('user_id');

    expect(Reviewer::where('user_id', $user->id)->exists())->toBeFalse();
});

it('BR-IAM-15 dan BR-RPT-09 — store memberi tahu user dan mengaudit penunjukan', function () {
    Notification::fake();
    $user = calonReviewer();

    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), [
        'user_id' => $user->id,
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
    ]);

    Notification::assertSentTo($user, ReviewerAppointedNotification::class);
    expect(auditDeskripsi('Reviewer ditunjuk'))->toBe(1);
});

it('BR-IAM-11 — update tipe internal ke external mencabut role lama dan memberi yang baru', function () {
    Notification::fake();
    $user = calonReviewer();
    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), [
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
    ]);
    $reviewer = Reviewer::where('user_id', $user->id)->firstOrFail();

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $reviewer), [
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonths(2)->toDateString(),
    ])->assertRedirect(route('admin.reviewers.index'));

    $user = $user->fresh();
    expect($user->hasRole('reviewer_external'))->toBeTrue()
        ->and($user->hasRole('reviewer_internal'))->toBeFalse()
        ->and($reviewer->fresh()->reviewer_type)->toBe('external')
        ->and($reviewer->fresh()->end_date->toDateString())->toBe(today()->addMonths(2)->toDateString())
        ->and(auditDeskripsi('Masa tugas reviewer diubah'))->toBe(1);
});

it('BR-REV-09 dan BR-RPT-09 — deactivate menyetel end_date kemarin dan mengaudit', function () {
    $reviewer = Reviewer::factory()->create([
        'reviewer_type' => 'internal',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->operator)
        ->patch(route('admin.reviewers.deactivate', $reviewer))
        ->assertRedirect(route('admin.reviewers.index'));

    $reviewer = $reviewer->fresh();
    expect($reviewer->end_date->toDateString())->toBe(today()->subDay()->toDateString())
        ->and($reviewer->isWithinTenure())->toBeFalse()
        ->and(auditDeskripsi('Masa tugas reviewer diubah'))->toBe(1);
});

it('BR-REV-09 dan BR-RPT-09 — activate mengosongkan end_date dan mengaudit', function () {
    $reviewer = Reviewer::factory()->create([
        'reviewer_type' => 'internal',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);

    $this->actingAs($this->operator)
        ->patch(route('admin.reviewers.activate', $reviewer))
        ->assertRedirect(route('admin.reviewers.index'));

    $reviewer = $reviewer->fresh();
    expect($reviewer->end_date)->toBeNull()
        ->and($reviewer->isWithinTenure())->toBeTrue()
        ->and(auditDeskripsi('Masa tugas reviewer diubah'))->toBe(1);
});

it('BR-IAM-11 — update tipe juga mengaudit penunjukan dan memberi tahu user', function () {
    Notification::fake();
    $user = calonReviewer();
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $reviewer), [
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    Notification::assertSentTo($user, ReviewerAppointedNotification::class);
    expect(auditDeskripsi('Reviewer ditunjuk'))->toBe(1);
});

it('BR-REV-09 — update hanya tipe tidak menulis audit masa tugas', function () {
    Notification::fake();
    $reviewer = Reviewer::factory()->create([
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $reviewer), [
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    expect(auditDeskripsi('Masa tugas reviewer diubah'))->toBe(0);
});

it('BR-IAM-11 — update ditolak bila user punya lebih dari satu record reviewer', function () {
    $user = calonReviewer();
    $dates = ['start_date' => today()->toDateString(), 'end_date' => today()->addMonth()->toDateString()];
    $first = Reviewer::factory()->create(['user_id' => $user->id, 'reviewer_type' => 'internal'] + $dates);
    $second = Reviewer::factory()->create(['user_id' => $user->id, 'reviewer_type' => 'internal'] + $dates);

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $second), [
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonths(3)->toDateString(),
    ])->assertSessionHasErrors('user_id');

    expect($first->fresh()->reviewer_type)->toBe('internal')
        ->and($second->fresh()->reviewer_type)->toBe('internal')
        ->and($second->fresh()->end_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('BR-IAM-05 — update tipe untuk akun nonaktif ditolak dan tidak mengubah apa pun', function () {
    $user = calonReviewer(false);
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $reviewer), [
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonths(3)->toDateString(),
    ])->assertSessionHasErrors('user_id');

    $reviewer = $reviewer->fresh();
    expect($reviewer->reviewer_type)->toBe('internal')
        ->and($reviewer->end_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

function penugasanReviewer(Reviewer $reviewer, string $evaluation, string $status = 'active'): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create()->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => $status,
    ]);
}

it('BR-IAM-11 dan BR-RPT-09 — destroy reviewer tanpa penugasan mencabut role dan mengaudit', function () {
    Notification::fake();
    $user = calonReviewer();
    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), [
        'user_id' => $user->id,
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
    ]);
    $reviewer = Reviewer::where('user_id', $user->id)->firstOrFail();

    $this->actingAs($this->operator)->delete(route('admin.reviewers.destroy', $reviewer))
        ->assertRedirect(route('admin.reviewers.index'));

    expect(Reviewer::whereKey($reviewer->id)->exists())->toBeFalse()
        ->and($user->fresh()->hasRole('reviewer_external'))->toBeFalse()
        ->and(auditDeskripsi('Reviewer dihapus'))->toBe(1);
});

it('BR-REV-10 — destroy reviewer berpenugasan, termasuk yang replaced, ditolak', function (string $status) {
    $reviewer = Reviewer::factory()->create();
    penugasanReviewer($reviewer, 'completed', $status);

    $this->actingAs($this->operator)->delete(route('admin.reviewers.destroy', $reviewer))
        ->assertSessionHasErrors(['error' => 'Tidak dapat menghapus reviewer yang masih memiliki riwayat penugasan.']);

    expect(Reviewer::whereKey($reviewer->id)->exists())->toBeTrue()
        ->and(auditDeskripsi('Reviewer dihapus'))->toBe(0);
})->with(['active', 'replaced']);

it('BR-REV-05 — show menghitung total, selesai, dan menunggu dari evaluation_status', function () {
    $reviewer = Reviewer::factory()->create();
    penugasanReviewer($reviewer, 'completed');
    penugasanReviewer($reviewer, 'not_required');
    penugasanReviewer($reviewer, 'pending');
    penugasanReviewer($reviewer, 'pending', 'replaced');

    $this->actingAs($this->operator)->get(route('admin.reviewers.show', $reviewer))
        ->assertInertia(fn ($page) => $page
            ->where('reviewer.total_reviews', 4)
            ->where('reviewer.completed_reviews', 2)
            ->where('reviewer.pending_reviews', 1));
});

it('BR-REV-09 — index memakai masa tugas inklusif: end_date hari ini masih aktif', function () {
    $berakhirHariIni = Reviewer::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);
    Reviewer::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);
    Reviewer::factory()->create([
        'start_date' => today()->addDay()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($this->operator)->get(route('admin.reviewers.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->has('reviewers.data', 1)
            ->where('reviewers.data.0.id', $berakhirHariIni->id)
            ->where('reviewers.data.0.is_active', true));

    $this->actingAs($this->operator)->get(route('admin.reviewers.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page
            ->has('reviewers.data', 2)
            ->where('reviewers.data.0.is_active', false)
            ->where('reviewers.data.1.is_active', false));
});

it('BR-REV-09 — show melaporkan is_active dari definisi masa tugas', function () {
    $reviewer = Reviewer::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    $this->actingAs($this->operator)->get(route('admin.reviewers.show', $reviewer))
        ->assertInertia(fn ($page) => $page->where('reviewer.is_active', true));
});

it('BR-REV-09 — create tidak menawarkan user yang masa tugasnya berakhir hari ini', function () {
    Permission::findOrCreate('users.manage');
    $berakhirHariIni = User::factory()->create(['name' => 'Aktif Hari Ini']);
    $sudahBerakhir = User::factory()->create(['name' => 'Sudah Berakhir']);
    foreach ([$berakhirHariIni, $sudahBerakhir] as $u) {
        $u->assignRole(Role::findOrCreate('Tenaga Kependidikan', 'web'));
    }
    Reviewer::factory()->create(['user_id' => $berakhirHariIni->id,
        'start_date' => today()->subMonth()->toDateString(), 'end_date' => today()->toDateString()]);
    Reviewer::factory()->create(['user_id' => $sudahBerakhir->id,
        'start_date' => today()->subMonth()->toDateString(), 'end_date' => today()->subDay()->toDateString()]);

    $this->actingAs($this->operator)->get(route('admin.reviewers.create'))
        ->assertInertia(fn ($page) => $page
            ->has('users', 1)
            ->where('users.0.id', $sudahBerakhir->id));
});

it('BR-IAM-11 dan BR-REV-11 — destroy duplikat kosong tidak mencabut role record yang tersisa', function () {
    $user = calonReviewer();
    $user->assignRole(Role::findOrCreate('reviewer_internal', 'web'));
    $tersisa = Reviewer::factory()->create(['user_id' => $user->id, 'reviewer_type' => 'internal']);
    $duplikat = Reviewer::factory()->create(['user_id' => $user->id, 'reviewer_type' => 'internal']);

    $this->actingAs($this->operator)->delete(route('admin.reviewers.destroy', $duplikat))
        ->assertRedirect(route('admin.reviewers.index'));

    expect(Reviewer::whereKey($duplikat->id)->exists())->toBeFalse()
        ->and(Reviewer::whereKey($tersisa->id)->exists())->toBeTrue()
        ->and($user->fresh()->hasRole('reviewer_internal'))->toBeTrue();
});

it('BR-REV-09 — index menyebut reviewer yang belum mulai sebagai nonaktif', function () {
    Reviewer::factory()->create(['start_date' => today()->addDay()->toDateString(), 'end_date' => null]);

    $this->actingAs($this->operator)->get(route('admin.reviewers.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page->has('reviewers.data', 1)->where('reviewers.data.0.is_active', false));
});
