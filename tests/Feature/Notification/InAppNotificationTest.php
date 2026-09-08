<?php

// tests/Feature/Notification/InAppNotificationTest.php
//
// BR-NOT-01, BR-NOT-02, BR-NOT-04 — notifikasi in-app.
//
// Membuka daftar bukan membaca isinya. Kalau `read_at` terisi saat halaman
// dimuat, penghitung "belum dibaca" langsung nol pada kunjungan pertama dan
// notifikasi yang belum sempat dilihat ikut terkubur — persis kebalikan dari
// gunanya penghitung itu.

use App\Actions\Monev\OpenMonevCycle;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use App\Notifications\MonevStageOpenedNotification;
use App\States\Submission\Draft;
use App\Support\Notification\Deliver;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

function beriNotifikasi(User $user): DatabaseNotification
{
    Deliver::to($user, new FormChangedNotification(Form::factory()->create()));

    return $user->notifications()->latest()->sole();
}

it('[NOT-INAPP-01] notifikasi in-app tersimpan permanen', function () {
    $budi = User::factory()->create();
    $notifikasi = beriNotifikasi($budi);

    $this->actingAs($budi)
        ->post(route('notifications.read', $notifikasi->id))
        ->assertRedirect();

    // Dibaca, bukan hilang: tidak ada pembersihan otomatis, karena yang
    // dianggap sudah tidak perlu oleh sistem sering justru yang sedang dicari
    // orangnya.
    expect($budi->notifications()->count())->toBe(1)
        ->and($notifikasi->refresh()->read_at)->not->toBeNull();
});

it('[NOT-INAPP-02] user menghapus notifikasinya sendiri', function () {
    $budi = User::factory()->create();
    $notifikasi = beriNotifikasi($budi);

    $this->actingAs($budi)
        ->delete(route('notifications.destroy', $notifikasi->id))
        ->assertRedirect();

    expect($budi->notifications()->count())->toBe(0);
});

it('[NOT-INAPP-02] notifikasi orang lain tidak bisa disentuh', function () {
    $budi = User::factory()->create();
    $sari = User::factory()->create();
    $notifikasi = beriNotifikasi($sari);

    // 404, bukan 403: memberitahu bahwa notifikasi orang lain "ada tapi bukan
    // milikmu" sudah membocorkan keberadaannya.
    $this->actingAs($budi)
        ->delete(route('notifications.destroy', $notifikasi->id))
        ->assertNotFound();

    expect($sari->notifications()->count())->toBe(1);
});

it('[NOT-INAPP-03] membuka daftar tidak mengisi read_at', function () {
    $budi = User::factory()->create();
    $notifikasi = beriNotifikasi($budi);

    $this->actingAs($budi)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/IndexPage')
            ->where('unreadCount', 1)
            ->where('notifications.data.0.read_at', null)
        );

    expect($notifikasi->refresh()->read_at)->toBeNull()
        ->and($budi->unreadNotifications()->count())->toBe(1);
});

it('[NOT-INAPP-03] read_at terisi saat notifikasinya diklik', function () {
    $budi = User::factory()->create();
    $notifikasi = beriNotifikasi($budi);

    $this->actingAs($budi)->post(route('notifications.read', $notifikasi->id));

    expect($notifikasi->refresh()->read_at)->not->toBeNull()
        ->and($budi->unreadNotifications()->count())->toBe(0);
});

it('[NOT-CHANNEL-05] satu event bisa punya lebih dari satu penerima', function () {
    // Penerima adalah pihak yang harus bertindak, bukan pihak yang kebetulan
    // disebut: siklus monev yang dibuka menunggu laporan peneliti sekaligus
    // penilaian reviewer siklus itu (BR-MON-10).
    $fx = monevFixture();

    $laporan = FormSubmission::factory()->create([
        'parent_submission_id' => $fx['parent']->id,
        'form_phase_detail_id' => $fx['monev1']->id,
        'submitted_by' => $fx['owner']->id,
        'status' => Draft::class,
    ]);

    $reviewerUser = User::factory()->create();
    $reviewer = Reviewer::factory()->create([
        'user_id' => $reviewerUser->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    SubmissionReviewer::create([
        'form_submission_id' => $laporan->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    OpenMonevCycle::run($fx['parent'], $fx['monev1']);

    $terkirim = fn (User $u) => $u->notifications()
        ->where('type', MonevStageOpenedNotification::class)
        ->count();

    expect($terkirim($fx['owner']))->toBe(1)
        ->and($terkirim($reviewerUser))->toBe(1);
});

it('[NOT-CHANNEL-06] penerima yang tidak berkepentingan tidak dikirimi', function () {
    $fx = monevFixture();
    $orangLain = User::factory()->create();

    OpenMonevCycle::run($fx['parent'], $fx['monev1']);

    expect($orangLain->notifications()->count())->toBe(0);
});
