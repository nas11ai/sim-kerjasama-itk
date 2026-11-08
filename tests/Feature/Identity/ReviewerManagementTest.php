<?php

// tests/Feature/Identity/ReviewerManagementTest.php
//
// BR-IAM-05, BR-IAM-11, BR-IAM-15, BR-REV-09, BR-RPT-09 — layar Operator mengelola
// reviewer lewat AppointReviewer dan ChangeReviewerTenure, bukan menulis model langsung.
// W47 — dijaga `reviewers.assign`. W50 — hapus, aktifkan, dan nonaktifkan tidak lagi punya
// jalur; masa tugas yang diakhiri lewat "Edit Periode" menggantikannya.

use App\Actions\FormSubmission\DeactivateUser;
use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\ChangeReviewerTenure;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\ReviewerAppointedNotification;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::findOrCreate('reviewers.assign');
    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo('reviewers.assign');
});

function calonReviewer(bool $active = true): User
{
    return User::factory()->create(['is_active' => $active]);
}

function auditDeskripsi(string $deskripsi): int
{
    return Activity::inLog(AuditTrail::LOG_NAME)->where('description', $deskripsi)->count();
}

/** @return array<string, mixed> */
function penunjukan(User $user, string $type = 'internal', array $override = []): array
{
    return array_merge([
        'user_id' => $user->id,
        'reviewer_type' => $type,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addYear()->toDateString(),
    ], $override);
}

function penugasanReviewer(Reviewer $reviewer, string $evaluation, string $status = 'active'): SubmissionReviewer
{
    return SubmissionReviewer::create([
        'form_submission_id' => FormSubmission::factory()->create()->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => $evaluation,
        'status' => $status,
    ]);
}

function reviewerHabis(User $user): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->subYear()->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);
}

function duplikatReviewer(User $user): void
{
    $dates = ['start_date' => today()->toDateString(), 'end_date' => today()->addMonth()->toDateString()];
    Reviewer::factory()->count(2)->create(['user_id' => $user->id, 'reviewer_type' => 'internal'] + $dates);
}

// ---------- Layar dan wewenang ----------

it('Operator pemegang reviewers.assign membuka manajemen reviewer', function () {
    $user = calonReviewer();
    $user->update(['name' => 'Dr. Rina Wijaya']);
    $reviewer = Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => '2025-09-01',
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Identity/ReviewerIndexPage')
            ->has('reviewers.data', 1)
            ->has('reviewers.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $reviewer->id)
                ->where('userId', $user->id)
                ->where('name', 'Dr. Rina Wijaya')
                ->where('type', ['value' => 'internal', 'label' => 'Internal'])
                ->where('tenureStart', '2025-09-01')
                ->where('tenureEnd', today()->addMonth()->toDateString())
                ->where('runningAssignments', 0)
                ->where('standing', ['value' => 'active', 'label' => 'Aktif'])
                // Operator tanpa users.verify/users.manage tidak bisa membuka detail user.
                ->where('historyUrl', null))
            ->where('filters', ['search' => '', 'type' => '', 'standing' => ''])
            ->where('typeOptions', [
                ['value' => 'internal', 'label' => 'Internal'],
                ['value' => 'external', 'label' => 'Eksternal'],
            ])
            ->has('standingOptions', 4)
            ->where('can.manageInvitations', false)
            ->missing('appointmentCandidates'));
});

it('historyUrl menaut tab review detail user bila penonton boleh melihat user', function () {
    Permission::findOrCreate('users.verify');
    $this->operator->givePermissionTo('users.verify');
    $reviewer = Reviewer::factory()->create();

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviewers.data.0.historyUrl', route('admin.users.show', ['user' => $reviewer->user_id, 'tab' => 'reviews']))
            ->where('can.manageInvitations', true)
            ->etc());
});

it('peneliti 403', function () {
    $this->actingAs(calonReviewer())->get(route('admin.reviewers.index'))->assertForbidden();
    $this->actingAs(calonReviewer())->post(route('admin.reviewers.store'), penunjukan(calonReviewer()))->assertForbidden();
});

it('pemegang users.manage saja 403', function () {
    Permission::findOrCreate('users.manage');
    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    $this->actingAs($admin)->get(route('admin.reviewers.index'))->assertForbidden();
});

it('[IAM-DEACT-04] reviewer akun nonaktif berstanding account_inactive', function () {
    $user = calonReviewer();
    Reviewer::factory()->create([
        'user_id' => $user->id,
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);
    DeactivateUser::run($user);

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index', ['standing' => 'account_inactive']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('reviewers.data', 1)
            ->where('reviewers.data.0.standing', ['value' => 'account_inactive', 'label' => 'Akun Nonaktif'])
            ->etc());

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index', ['standing' => 'active']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('reviewers.data', 0)->etc());
});

it('beban tugas menghitung penugasan aktif yang belum beres', function () {
    $reviewer = Reviewer::factory()->create();
    penugasanReviewer($reviewer, 'pending');
    penugasanReviewer($reviewer, 'pending');
    penugasanReviewer($reviewer, 'completed');
    penugasanReviewer($reviewer, 'not_required');
    penugasanReviewer($reviewer, 'pending', 'replaced');

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviewers.data.0.runningAssignments', 2)
            ->etc());
});

it('pencarian nama memperlakukan % dan _ sebagai teks', function () {
    Reviewer::factory()->create(['user_id' => User::factory()->create(['name' => 'Dr. Rina Wijaya'])->id]);
    Reviewer::factory()->create(['user_id' => User::factory()->create(['name' => 'Budi 100% Hadir'])->id]);

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index', ['search' => '100%']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('reviewers.data', 1)
            ->where('reviewers.data.0.name', 'Budi 100% Hadir')
            ->etc());

    $this->actingAs($this->operator)
        ->get(route('admin.reviewers.index', ['search' => 'rina']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('reviewers.data', 1)->etc());
});

it('route destroy, activate, deactivate, appoint-reviewer tidak ada lagi', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.reviewers.destroy',
    'admin.reviewers.activate',
    'admin.reviewers.deactivate',
    'admin.reviewers.show',
    'admin.reviewers.create',
    'admin.reviewers.edit',
    'admin.users.appoint-reviewer',
]);

it('detail user mengirim pilihan tipe reviewer untuk "Tunjuk sebagai Reviewer"', function () {
    Permission::findOrCreate('users.verify');
    $this->operator->givePermissionTo('users.verify');

    $this->actingAs($this->operator)
        ->get(route('admin.users.show', calonReviewer()))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviewerTypeOptions', [
                ['value' => 'internal', 'label' => 'Internal'],
                ['value' => 'external', 'label' => 'Eksternal'],
            ])
            ->where('can.appointReviewer', true)
            ->etc());
});

// ---------- Penunjukan (store) ----------

it('BR-IAM-11 — store memberi role reviewer_internal', function () {
    Notification::fake();
    $user = calonReviewer();

    $this->actingAs($this->operator)
        ->post(route('admin.reviewers.store'), penunjukan($user))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasRole('reviewer_internal'))->toBeTrue();
});

it('BR-IAM-11 — store untuk reviewer yang masa tugasnya habis memperbarui record yang sama', function () {
    Notification::fake();
    $user = calonReviewer();
    reviewerHabis($user);

    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), penunjukan($user, 'internal', [
        'end_date' => today()->addMonth()->toDateString(),
    ]));

    $records = Reviewer::where('user_id', $user->id)->get();
    expect($records)->toHaveCount(1)
        ->and($records->first()->end_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('BR-IAM-05 — store untuk akun nonaktif ditolak', function () {
    $user = calonReviewer(false);

    $this->actingAs($this->operator)
        ->post(route('admin.reviewers.store'), penunjukan($user))
        ->assertSessionHasErrors('user_id');

    expect(Reviewer::where('user_id', $user->id)->exists())->toBeFalse();
});

it('BR-IAM-15 dan BR-RPT-09 — store memberi tahu user dan mengaudit penunjukan', function () {
    Notification::fake();
    $user = calonReviewer();

    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), penunjukan($user, 'external'));

    Notification::assertSentTo($user, ReviewerAppointedNotification::class);
    expect(auditDeskripsi('Reviewer ditunjuk'))->toBe(1);
});

it('BR-IAM-11 — store ditolak bila user punya lebih dari satu record reviewer', function () {
    Notification::fake();
    $user = calonReviewer();
    duplikatReviewer($user);

    $this->actingAs($this->operator)
        ->post(route('admin.reviewers.store'), penunjukan($user, 'external'))
        ->assertSessionHasErrors('user_id');

    expect(Reviewer::where('user_id', $user->id)->where('reviewer_type', 'external')->exists())->toBeFalse()
        ->and(auditDeskripsi('Reviewer ditunjuk'))->toBe(0);
});

it('store menolak masa tugas yang berakhir sebelum mulai', function () {
    $user = calonReviewer();

    $this->actingAs($this->operator)
        ->post(route('admin.reviewers.store'), penunjukan($user, 'internal', ['end_date' => today()->subDay()->toDateString()]))
        ->assertSessionHasErrors('end_date');
});

// ---------- Edit Periode (update) ----------

it('BR-IAM-11 — update tipe internal ke external mencabut role lama dan memberi yang baru', function () {
    Notification::fake();
    $user = calonReviewer();
    $this->actingAs($this->operator)->post(route('admin.reviewers.store'), penunjukan($user));
    $reviewer = Reviewer::where('user_id', $user->id)->firstOrFail();

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $reviewer), [
        'reviewer_type' => 'external',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addMonths(2)->toDateString(),
    ])->assertRedirect();

    $user = $user->fresh();
    expect($user->hasRole('reviewer_external'))->toBeTrue()
        ->and($user->hasRole('reviewer_internal'))->toBeFalse()
        ->and($reviewer->fresh()->reviewer_type)->toBe('external')
        ->and($reviewer->fresh()->end_date->toDateString())->toBe(today()->addMonths(2)->toDateString())
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

it('BR-REV-09 dan W50 — mengakhiri masa tugas lewat Edit Periode mengaudit dan mengeluarkannya dari masa tugas', function () {
    $reviewer = Reviewer::factory()->create([
        'reviewer_type' => 'internal',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->operator)->put(route('admin.reviewers.update', $reviewer), [
        'reviewer_type' => 'internal',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($reviewer->fresh()->isWithinTenure())->toBeFalse()
        ->and(auditDeskripsi('Masa tugas reviewer diubah'))->toBe(1);
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

// ---------- Masa tugas (BR-REV-09) ----------

it('BR-REV-09 — index memakai masa tugas inklusif: end_date hari ini masih aktif', function () {
    $berakhirHariIni = Reviewer::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);
    $berakhir = Reviewer::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);
    Reviewer::factory()->create(['start_date' => today()->addDay()->toDateString(), 'end_date' => null]);

    $this->actingAs($this->operator)->get(route('admin.reviewers.index', ['standing' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->has('reviewers.data', 1)
            ->where('reviewers.data.0.id', $berakhirHariIni->id)
            ->where('reviewers.data.0.standing.value', 'active'));

    $this->actingAs($this->operator)->get(route('admin.reviewers.index', ['standing' => 'ended']))
        ->assertInertia(fn ($page) => $page
            ->has('reviewers.data', 1)
            ->where('reviewers.data.0.id', $berakhir->id)
            ->where('reviewers.data.0.standing', ['value' => 'ended', 'label' => 'Berakhir']));
});

it('BR-REV-09 — reviewer yang belum mulai berstanding not_started', function () {
    Reviewer::factory()->create(['start_date' => today()->addDay()->toDateString(), 'end_date' => null]);

    $this->actingAs($this->operator)->get(route('admin.reviewers.index', ['standing' => 'not_started']))
        ->assertInertia(fn ($page) => $page
            ->has('reviewers.data', 1)
            ->where('reviewers.data.0.standing', ['value' => 'not_started', 'label' => 'Belum Mulai']));
});

it('BR-IAM-11 dan BR-REV-09 — AppointReviewer tanpa tanggal memberi masa tugas default ke reviewer habis', function () {
    Notification::fake();
    $user = calonReviewer();
    $lama = reviewerHabis($user);

    $reviewer = AppointReviewer::run($user, AppointReviewer::TYPE_INTERNAL);

    expect($reviewer->id)->toBe($lama->id)
        ->and($reviewer->start_date->toDateString())->toBe(today()->toDateString())
        ->and($reviewer->end_date->toDateString())->toBe(today()->addYear()->toDateString());
});

it('BR-IAM-11 — AppointReviewer tanpa tanggal tidak mengubah masa tugas yang sedang berjalan', function () {
    Notification::fake();
    $user = calonReviewer();
    Reviewer::factory()->create([
        'user_id' => $user->id,
        'reviewer_type' => 'internal',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $reviewer = AppointReviewer::run($user, AppointReviewer::TYPE_EXTERNAL);

    expect($reviewer->start_date->toDateString())->toBe(today()->subMonth()->toDateString())
        ->and($reviewer->end_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('BR-REV-09 dan BR-RPT-09 — tanggal sama berformat waktu tidak ditulis dan tidak diaudit', function () {
    $reviewer = Reviewer::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    ChangeReviewerTenure::run(
        $reviewer,
        today()->subMonth()->toDateString().' 08:00',
        today()->addMonth()->toDateString().' 08:00',
    );

    expect(auditDeskripsi('Masa tugas reviewer diubah'))->toBe(0);
});
