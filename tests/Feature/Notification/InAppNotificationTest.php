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
use App\Notifications\ReviewerAssignedNotification;
use App\Queries\Notification\UnreadNotificationCount;
use App\States\Submission\Draft;
use App\Support\Notification\Deliver;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

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

it('read pada notifikasi orang lain 404', function () {
    $budi = User::factory()->create();
    $notifikasi = beriNotifikasi(User::factory()->create());

    $this->actingAs($budi)
        ->post(route('notifications.read', $notifikasi->id))
        ->assertNotFound();

    expect($notifikasi->refresh()->read_at)->toBeNull();
});

it('[NOT-INAPP-03] membuka daftar tidak mengisi read_at', function () {
    $budi = User::factory()->create();
    $notifikasi = beriNotifikasi($budi);

    $this->actingAs($budi)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notification/IndexPage')
            ->where('unreadCount', 1)
            ->where('notifications.data.0.isRead', false)
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

it('[NOT-INAPP-04] user menandai semua notifikasinya dibaca', function () {
    $budi = User::factory()->create();
    $dewi = User::factory()->create();
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    Deliver::to($dewi, new FormChangedNotification(Form::factory()->create()));

    $this->actingAs($budi)
        ->post(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('success');

    // BR-NOT-01 — dibaca, bukan dibuang; BR-NOT-04 — hanya kotak masuk Budi.
    expect($budi->notifications()->count())->toBe(2)
        ->and(UnreadNotificationCount::run($budi))->toBe(0)
        ->and(UnreadNotificationCount::run($dewi))->toBe(1);
});

/** @return array{0: User, 1: FormSubmission, 2: SubmissionReviewer} */
function rinaDitugaskan(): array
{
    $rina = User::factory()->create(['name' => 'Rina']);
    $rina->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));
    $reviewer = Reviewer::factory()->create([
        'user_id' => $rina->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
    $p001 = FormSubmission::factory()->create();
    $penugasan = SubmissionReviewer::create([
        'form_submission_id' => $p001->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    Deliver::to($rina, new ReviewerAssignedNotification($p001));

    return [$rina, $p001, $penugasan];
}

it('[NOT-INAPP-05] membuka notifikasi membawa ke halaman yang relevan', function () {
    [$rina, $p001] = rinaDitugaskan();
    $notifikasi = $rina->notifications()->sole();

    $this->actingAs($rina)
        ->post(route('notifications.read', $notifikasi->id))
        ->assertRedirect(route('reviewer.assignments.evaluation', $p001));

    expect($notifikasi->refresh()->read_at)->not->toBeNull();
});

it('[NOT-INAPP-06] notifikasi yang pengajuannya tak lagi bisa diakses tidak membuka datanya', function () {
    // BR-REV-10, BR-SM-07 — penugasan diganti: notifikasi lama bukan lagi pintu ke P-001.
    [$rina, , $penugasan] = rinaDitugaskan();
    $penugasan->update(['status' => 'replaced']);
    $notifikasi = $rina->notifications()->sole();

    $this->actingAs($rina)
        ->post(route('notifications.read', $notifikasi->id))
        ->assertRedirect(route('notifications.index'));

    expect($notifikasi->refresh()->read_at)->not->toBeNull();
});

it('penghitung turun setelah read, read-all, destroy', function () {
    $budi = User::factory()->create();
    $pertama = beriNotifikasi($budi);
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    $badge = fn (int $n) => $this->actingAs($budi)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page->where('navigation.unreadNotifications', $n));

    $badge(3);
    $this->post(route('notifications.read', $pertama->id));
    $badge(2);
    $this->delete(route('notifications.destroy', $budi->unreadNotifications()->first()->id));
    $badge(1);
    $this->post(route('notifications.read-all'));
    $badge(0);
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
