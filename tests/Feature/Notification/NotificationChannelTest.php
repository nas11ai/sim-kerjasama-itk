<?php

// tests/Feature/Notification/NotificationChannelTest.php
//
// BR-NOT-01 s/d BR-NOT-06 — notifikasi.
//
// Channel per tipe event adalah konfigurasi, bukan konstanta kode. Yang
// menjadi aturan hanya kewajiban mendeklarasikan: event tanpa deklarasi
// ditolak saat dipublish, bukan diam-diam diberi default — kelalaian yang
// dijawab default menjadi notifikasi yang tidak pernah sampai ke orang yang
// menunggunya.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\NotificationChannelSetting;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use App\Support\Notification\ChannelNotDeclared;
use App\Support\Notification\Deliver;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function setChannels(string $eventType, array $channels): void
{
    NotificationChannelSetting::query()->updateOrCreate(
        ['event_type' => $eventType],
        ['channels' => $channels],
    );

    NotificationChannels::forget();
}

it('[NOT-CHANNEL-01] ProposalSubmitted terkirim in-app dan email', function () {
    $channels = (new ProposalSubmittedNotification(
        FormSubmission::factory()->create(['submitted_by' => User::factory()->create()->id])
    ))->via(User::factory()->create());

    expect($channels)->toBe([NotificationChannels::IN_APP, NotificationChannels::EMAIL]);
});

it('[NOT-CHANNEL-02] FormChanged cukup in-app tanpa email', function () {
    // Peristiwa yang sekadar memberi kabar tidak mengejar peneliti ke kotak
    // masuknya. Email untuk segalanya membuat email berhenti dibaca untuk
    // apa pun.
    $channels = (new FormChangedNotification(Form::factory()->create()))
        ->via(User::factory()->create());

    expect($channels)->toBe([NotificationChannels::IN_APP]);
});

it('[NOT-CHANNEL-03] channel event diubah lewat konfigurasi tanpa deploy', function () {
    setChannels(FormChangedNotification::EVENT_TYPE, [NotificationChannels::IN_APP]);

    expect((new FormChangedNotification(Form::factory()->create()))->via(User::factory()->create()))
        ->toBe([NotificationChannels::IN_APP]);

    setChannels(FormChangedNotification::EVENT_TYPE, [
        NotificationChannels::IN_APP,
        NotificationChannels::EMAIL,
    ]);

    expect((new FormChangedNotification(Form::factory()->create()))->via(User::factory()->create()))
        ->toBe([NotificationChannels::IN_APP, NotificationChannels::EMAIL]);
});

it('[NOT-CHANNEL-03] Admin mengubah channel lewat layar konfigurasi', function () {
    Permission::findOrCreate('users.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    $this->actingAs($admin)
        ->put(route('admin.reference.notification-channels.update', FormChangedNotification::EVENT_TYPE), [
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        ])
        ->assertSessionHasNoErrors();

    // `value()` melewati cast model dan mengembalikan JSON mentah — dibaca
    // lewat modelnya supaya yang diperiksa adalah nilai yang dilihat aplikasi.
    expect(NotificationChannelSetting::query()
        ->where('event_type', FormChangedNotification::EVENT_TYPE)
        ->sole()
        ->channels)
        ->toBe([NotificationChannels::IN_APP, NotificationChannels::EMAIL]);
});

it('[NOT-CHANNEL-04] event tanpa deklarasi channel ditolak saat dipublish', function () {
    NotificationChannelSetting::query()
        ->where('event_type', FormChangedNotification::EVENT_TYPE)
        ->delete();

    NotificationChannels::forget();

    expect(fn () => (new FormChangedNotification(Form::factory()->create()))
        ->via(User::factory()->create()))
        ->toThrow(ChannelNotDeclared::class);
});

it('[NOT-CHANNEL-04] penolakannya menyebut jalan keluarnya', function () {
    // Penolakan yang hanya berbunyi "belum dideklarasikan" membuat Admin tahu
    // ada yang salah tapi tidak tahu di mana memperbaikinya.
    try {
        NotificationChannels::for('ProposalArchived');
        $this->fail('seharusnya ditolak');
    } catch (ChannelNotDeclared $e) {
        expect($e->getMessage())->toContain('Konfigurasi Umum');
    }
});

it('setiap kelas notifikasi punya deklarasi channel default', function () {
    // Kelas baru yang lupa didaftarkan akan gagal di sini, bukan pada
    // pengiriman pertama di produksi.
    $tanpaDefault = array_diff(
        array_keys(NotificationEventCatalog::declaredByClasses()),
        array_keys(NotificationEventCatalog::defaults()),
    );

    expect($tanpaDefault)->toBe([], 'Tipe event tanpa default channel: '.implode(', ', $tanpaDefault));
});

it('[NOT-DELIVERY-01] kegagalan notifikasi tidak menggagalkan aksi bisnis', function () {
    $fx = wizardFixture();

    $draft = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        'is_submitted' => false,
    ]);

    // Kanal email mati.
    Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP tidak bisa dihubungi'));
    Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('SMTP tidak bisa dihubungi'));

    $hasil = SubmitFormSubmission::run($draft, $fx['user']);

    expect($hasil->refresh()->status)->toBeInstanceOf(Submitted::class)
        ->and($hasil->is_submitted)->toBeTrue();
});

it('[NOT-DELIVERY-01] Deliver melaporkan gagal tanpa melempar', function () {
    Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('SMTP mati'));

    $terkirim = Deliver::to(
        User::factory()->create(),
        new FormChangedNotification(Form::factory()->create()),
    );

    expect($terkirim)->toBeFalse();
});

it('[NOT-DELIVERY-01] deklarasi channel yang hilang tetap melempar', function () {
    // Bedanya dengan kanal yang mati: ini kelalaian konfigurasi, dan
    // mendiamkannya membuat notifikasi diam-diam tidak pernah terkirim.
    NotificationChannelSetting::query()
        ->where('event_type', FormChangedNotification::EVENT_TYPE)
        ->delete();

    NotificationChannels::forget();

    expect(fn () => Deliver::to(
        User::factory()->create(),
        new FormChangedNotification(Form::factory()->create()),
    ))->toThrow(ChannelNotDeclared::class);
});
