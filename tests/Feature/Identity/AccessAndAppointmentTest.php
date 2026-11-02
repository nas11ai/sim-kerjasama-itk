<?php

// tests/Feature/Identity/AccessAndAppointmentTest.php
//
// BR-IAM-05, BR-IAM-09, BR-IAM-11, BR-IAM-15.
//
// Tiga aturan yang tampak administratif tapi menentukan perilaku di M2:
// tipe reviewer yang disetel di sini yang membatasi keterlihatan skor, dan
// jumlah record reviewer per orang yang menentukan penghitungan bebannya.

use App\Actions\FormSubmission\DeactivateUser;
use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\ChangeUserRoles;
use App\Actions\Identity\GrantDirectPermission;
use App\Actions\Identity\RevokeDirectPermission;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\ReviewerAppointedNotification;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use App\Support\Lifecycle\DeletionNotPermitted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function registeredPerson(): User
{
    $user = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id]);

    return $user->refresh();
}

// ---------- BR-IAM-09: hak akses additive, dua arah ----------

it('[IAM-PERM-01] hak akses langsung menambah yang datang dari role', function () {
    Permission::findOrCreate('submissions.view-all', 'web');
    Permission::findOrCreate('reporting.export', 'web');
    $operator = Role::findOrCreate('operator', 'web');
    $operator->givePermissionTo('submissions.view-all');

    $sari = registeredPerson();
    $admin = registeredPerson();
    ChangeUserRoles::run($admin, $sari, ['operator']);
    GrantDirectPermission::run($admin, $sari, 'reporting.export');

    expect($sari->fresh()->can('submissions.view-all'))->toBeTrue()
        ->and($sari->fresh()->can('reporting.export'))->toBeTrue();
});

it('[IAM-PERM-02] mencabut role tidak mencabut hak akses langsung', function () {
    Permission::findOrCreate('submissions.view-all', 'web');
    Permission::findOrCreate('reporting.export', 'web');
    $operator = Role::findOrCreate('operator', 'web');
    $operator->givePermissionTo('submissions.view-all');

    $sari = registeredPerson();
    $admin = registeredPerson();
    ChangeUserRoles::run($admin, $sari, ['operator']);
    GrantDirectPermission::run($admin, $sari, 'reporting.export');

    ChangeUserRoles::run($admin, $sari, []);

    expect($sari->fresh()->can('reporting.export'))->toBeTrue()
        ->and($sari->fresh()->can('submissions.view-all'))->toBeFalse();
});

it('[IAM-PERM-03] dua role memberi gabungan hak akses keduanya', function () {
    Permission::findOrCreate('submissions.view-all', 'web');
    Permission::findOrCreate('reviewers.evaluate', 'web');

    Role::findOrCreate('operator', 'web')->givePermissionTo('submissions.view-all');
    Role::findOrCreate('reviewer_internal', 'web')->givePermissionTo('reviewers.evaluate');

    $sari = registeredPerson();
    $sari->assignRole(['operator', 'reviewer_internal']);

    expect($sari->can('submissions.view-all'))->toBeTrue()
        ->and($sari->can('reviewers.evaluate'))->toBeTrue();
});

it('[IAM-PERM-04] mencabut hak akses langsung tidak mencabut yang dari role', function () {
    Permission::findOrCreate('submissions.view-all', 'web');
    Role::findOrCreate('operator', 'web')->givePermissionTo('submissions.view-all');

    $sari = registeredPerson();
    $admin = registeredPerson();
    ChangeUserRoles::run($admin, $sari, ['operator']);
    // Sumber ganda: satu hak akses datang dari role DAN diberikan langsung.
    GrantDirectPermission::run($admin, $sari, 'submissions.view-all');

    RevokeDirectPermission::run($admin, $sari, 'submissions.view-all');

    // Pembungkus yang meratakan hak akses efektif jadi satu daftar akan
    // mencabut keduanya di sini.
    expect($sari->fresh()->can('submissions.view-all'))->toBeTrue();
});

// ---------- BR-IAM-05: deaktivasi ----------

it('[IAM-DEACT-01] deaktivasi menyisakan data user utuh', function () {
    $sari = registeredPerson();

    DeactivateUser::run($sari);

    expect($sari->fresh()->is_active)->toBeFalse()
        ->and(UserProfile::where('user_id', $sari->id)->exists())->toBeTrue();
});

it('[IAM-DEACT-02] user nonaktif tidak bisa login', function () {
    $sari = registeredPerson();
    DeactivateUser::run($sari);

    $this->post(route('login'), ['email' => $sari->email, 'password' => 'password'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('[IAM-DEACT-03] pengajuan milik user nonaktif tetap terbaca', function () {
    $sari = registeredPerson();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $sari->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $sari->update(['is_active' => false]);

    expect(FormSubmission::find($submission->id))->not->toBeNull()
        ->and($submission->fresh()->submitted_by)->toBe($sari->id);
});

it('BR-IAM-05 — akun nonaktif tidak bisa ditunjuk sebagai reviewer', function () {
    $sari = registeredPerson();
    $sari->update(['is_active' => false]);

    expect(fn () => AppointReviewer::run($sari->fresh(), AppointReviewer::TYPE_INTERNAL))
        ->toThrow(ValidationException::class)
        ->and(Reviewer::where('user_id', $sari->id)->exists())->toBeFalse();
});

// ---------- BR-IAM-11: penunjukan reviewer ----------

it('[IAM-REVIEWER-01] penunjukan reviewer internal memberi role dan record', function () {
    Notification::fake();
    $sari = registeredPerson();

    $reviewer = AppointReviewer::run($sari, AppointReviewer::TYPE_INTERNAL);

    expect($reviewer->reviewer_type)->toBe('internal')
        ->and($sari->fresh()->hasRole('reviewer_internal'))->toBeTrue();

    Notification::assertSentTo($sari, ReviewerAppointedNotification::class);
});

it('[IAM-REVIEWER-02] penunjukan eksternal memberi role pembatas', function () {
    Notification::fake();
    $rina = registeredPerson();

    AppointReviewer::run($rina, AppointReviewer::TYPE_EXTERNAL);

    expect($rina->fresh()->hasRole('reviewer_external'))->toBeTrue()
        ->and($rina->fresh()->hasRole('reviewer_internal'))->toBeFalse();
});

it('[IAM-REVIEWER-03] menunjuk orang tanpa akun ditolak', function () {
    Permission::findOrCreate('users.manage', 'web');
    $operator = registeredPerson();
    $operator->givePermissionTo('users.manage');

    // Tidak ada user dengan id ini — penunjukan memilih dari yang sudah ada,
    // bukan membuat akun baru.
    $this->actingAs($operator)
        ->post(route('admin.users.appoint-reviewer', 999999), [
            'reviewer_type' => 'external',
        ])
        ->assertNotFound();
});

it('[IAM-REVIEWER-04] menunjuk ulang tidak membuat record kedua', function () {
    Notification::fake();
    $sari = registeredPerson();

    $pertama = AppointReviewer::run($sari, AppointReviewer::TYPE_INTERNAL);
    $kedua = AppointReviewer::run($sari->fresh(), AppointReviewer::TYPE_INTERNAL);

    expect($kedua->id)->toBe($pertama->id)
        ->and(Reviewer::where('user_id', $sari->id)->count())->toBe(1);
});

it('[IAM-REVIEWER-05] mengubah tipe mencabut role tipe lama', function () {
    Notification::fake();
    Permission::findOrCreate('reviewers.view-scores-others', 'web');
    Role::findOrCreate('reviewer_internal', 'web')->givePermissionTo('reviewers.view-scores-others');

    $sari = registeredPerson();
    AppointReviewer::run($sari, AppointReviewer::TYPE_INTERNAL);

    expect($sari->fresh()->can('reviewers.view-scores-others'))->toBeTrue();

    AppointReviewer::run($sari->fresh(), AppointReviewer::TYPE_EXTERNAL);

    // Menambah role baru tanpa mencabut yang lama membuat reviewer eksternal
    // tetap bisa melihat skor rekannya — salah di M2, tanpa gagal di sini.
    expect($sari->fresh()->hasRole('reviewer_internal'))->toBeFalse()
        ->and($sari->fresh()->can('reviewers.view-scores-others'))->toBeFalse();
});

it('[IAM-REVIEWER-06] penunjukan menetapkan masa tugas yang ditegakkan BR-REV-09', function () {
    Notification::fake();
    $sari = registeredPerson();

    $reviewer = AppointReviewer::run($sari, AppointReviewer::TYPE_INTERNAL, '2026-01-01', '2026-12-31');

    expect($reviewer->start_date->toDateString())->toBe('2026-01-01')
        ->and($reviewer->end_date->toDateString())->toBe('2026-12-31');
});

// ---------- BR-IAM-19: akun tidak pernah dihapus ----------

it('[IAM-DEACT-05] user tidak bisa dihapus, hanya dinonaktifkan', function () {
    $sari = registeredPerson();

    expect(fn () => $sari->delete())->toThrow(DeletionNotPermitted::class)
        ->and(User::query()->whereKey($sari->id)->exists())->toBeTrue();
});

it('[IAM-DEACT-06] jalur hapus tidak menembus penjagaan lead researcher', function () {
    $sari = registeredPerson();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $sari->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);

    // BR-IAM-10 menolak deaktivasi selama kepemilikan belum dipindah. Kalau
    // penghapusan lolos, penjagaan itu tinggal formalitas — pengajuannya
    // kehilangan pemilik lewat pintu sebelah.
    expect(fn () => DeactivateUser::run($sari))->toThrow(ValidationException::class)
        ->and(fn () => $sari->delete())->toThrow(DeletionNotPermitted::class)
        ->and($submission->fresh()->submitted_by)->toBe($sari->id);
});

it('[IAM-DEACT-07] tidak ada jalur hapus user di antarmuka mana pun', function () {
    $routes = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();

    expect($routes)->not->toContain('admin.users.delete')
        // Jalur kedua, dan yang lebih longgar dari keduanya: scaffolding Breeze
        // membiarkan pemiliknya sendiri memanggil forceDelete() tanpa penjagaan
        // apa pun, sementara form_submissions.submitted_by ber-cascade. Satu
        // klik di halaman profil memusnahkan seluruh riwayat penelitiannya.
        ->and($routes)->not->toContain('profile.destroy')
        // Yang tersedia sebagai gantinya harus benar-benar ada, bukan sekadar
        // diasumsikan: menghapus satu jalur tanpa memastikan penggantinya hidup
        // meninggalkan Operator tanpa cara mencabut akses sama sekali.
        ->and($routes)->toContain('admin.users.deactivate');
});

it('[IAM-DEACT-08] deaktivasi adalah satu-satunya cara mencabut akses akun', function () {
    $sari = registeredPerson();

    DeactivateUser::run($sari);

    $this->post(route('login'), ['email' => $sari->email, 'password' => 'password'])
        ->assertSessionHasErrors();

    expect($sari->fresh()->is_active)->toBeFalse()
        ->and($sari->fresh()->deleted_at)->toBeNull()
        ->and(UserProfile::where('user_id', $sari->id)->exists())->toBeTrue();
});
