<?php

// tests/Feature/Identity/AccountEventNotificationTest.php
//
// BR-IAM-15 — keputusan yang mengubah keadaan akun seseorang memberi tahu
// pihak yang harus bertindak atasnya.
//
// Arahnya sengaja tidak seragam: pendaftaran baru memberi tahu **Operator**
// (dialah yang harus memverifikasi), sedangkan verifikasi, penolakan,
// deaktivasi, dan penunjukan reviewer memberi tahu **user** — kemampuannya
// berubah tanpa ia melakukan apa pun.
//
// Batas atasnya dijaga [IAM-EVENT-05]: gema atas aksi sendiri hanya membuat
// notifikasi lebih cepat diabaikan daripada dibaca.

use App\Actions\FormSubmission\DeactivateUser;
use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\RegisterResearcher;
use App\Actions\Identity\VerifyNidn;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AccountDeactivatedNotification;
use App\Notifications\NewRegistrationAwaitingVerification;
use App\Notifications\NidnVerifiedNotification;
use App\Notifications\ReviewerAppointedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('[IAM-EVENT-01] pendaftaran baru memberi tahu Operator', function () {
    Notification::fake();

    Permission::findOrCreate('users.verify', 'web');
    $operator = User::factory()->create();
    $operator->givePermissionTo('users.verify');

    Role::findOrCreate('researcher', 'web');
    $org = Organization::create([
        'name' => 'Unit '.fake()->unique()->numerify('####'),
        'type' => 'study_program', 'parent_id' => null, 'is_active' => true,
    ]);

    RegisterResearcher::run([
        'name' => 'Budi', 'email' => 'budi@itk.ac.id', 'password' => 'rahasia-panjang',
        'nidn' => '0011223344', 'organization_id' => $org->id,
    ]);

    // Arahnya ke Operator, bukan ke pendaftar: yang harus bertindak adalah
    // yang memverifikasi.
    Notification::assertSentTo($operator, NewRegistrationAwaitingVerification::class);
});

it('[IAM-EVENT-02] verifikasi memberi tahu pendaftar', function () {
    Notification::fake();

    $budi = User::factory()->create();
    $profile = UserProfile::factory()->pending()->create(['user_id' => $budi->id]);

    VerifyNidn::run($profile);

    Notification::assertSentTo($budi, NidnVerifiedNotification::class);
});

it('[IAM-EVENT-03] deaktivasi memberi tahu user yang dinonaktifkan', function () {
    Notification::fake();

    $sari = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $sari->id]);

    DeactivateUser::run($sari);

    Notification::assertSentTo($sari, AccountDeactivatedNotification::class);
});

it('[IAM-EVENT-04] penunjukan reviewer memberi tahu yang ditunjuk', function () {
    Notification::fake();

    $sari = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $sari->id]);

    AppointReviewer::run($sari->fresh(), AppointReviewer::TYPE_INTERNAL);

    Notification::assertSentTo($sari, ReviewerAppointedNotification::class);
});

it('[IAM-EVENT-05] perubahan yang dilakukan user sendiri tidak memberitahunya', function () {
    Notification::fake();

    $budi = User::factory()->create();
    $profile = UserProfile::factory()->incomplete()->create(['user_id' => $budi->id]);

    // Melengkapi profil sendiri: tidak ada keputusan orang lain, jadi tidak
    // ada yang perlu diberitahukan kembali kepadanya.
    $profile->update([
        'phone' => '0813-9876-5432',
        'expertise' => 'Data Science',
        'photo_path' => 'profil/budi.jpg',
    ]);

    Notification::assertNothingSent();
});
