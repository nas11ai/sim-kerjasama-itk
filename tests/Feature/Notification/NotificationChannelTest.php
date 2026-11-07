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
use App\Actions\Notification\DeclareNotificationChannels;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\NotificationChannelSetting;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\RevisionResolvedNotification;
use App\States\Submission\Draft;
use App\States\Submission\Submitted;
use App\Support\Notification\ChannelNotDeclared;
use App\Support\Notification\Deliver;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
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

it('[NOT-CHANNEL-02] RevisionResolved cukup in-app tanpa email', function () {
    Notification::fake();
    $budi = User::factory()->create();
    $submission = FormSubmission::factory()->create(['submitted_by' => $budi->id]);

    Deliver::to($budi, new RevisionResolvedNotification($submission));

    Notification::assertSentTo(
        $budi,
        RevisionResolvedNotification::class,
        fn (RevisionResolvedNotification $n, array $channels) => $channels === [NotificationChannels::IN_APP],
    );
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
    Permission::findOrCreate('schemes.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('schemes.manage');

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

// ---------- ADMIN-08-10 — channel dari layar Konfigurasi Umum (W113) ----------

function channelAdmin(string $permission = 'schemes.manage'): User
{
    Permission::findOrCreate($permission, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo($permission);

    return $admin;
}

it('[NOT-CHANNEL-07] deklarasi channel tidak boleh kosong', function () {
    setChannels('ProposalSubmitted', [NotificationChannels::IN_APP, NotificationChannels::EMAIL]);

    $this->actingAs(channelAdmin())
        ->put(route('admin.reference.notification-channels.update', 'ProposalSubmitted'), ['channels' => []])
        ->assertSessionHasErrors('channels');

    NotificationChannels::forget();
    expect(NotificationChannels::for('ProposalSubmitted'))->toBe([NotificationChannels::IN_APP, NotificationChannels::EMAIL])
        ->and(Activity::query()->where('description', 'Channel notifikasi diubah')->count())->toBe(0);
});

it('[NOT-CHANNEL-07] Action menolak deklarasi kosong walau tanpa FormRequest', function () {
    setChannels('ProposalSubmitted', [NotificationChannels::IN_APP]);

    expect(fn () => DeclareNotificationChannels::run('ProposalSubmitted', [], channelAdmin()))
        ->toThrow(ValidationException::class, 'Pilih minimal satu channel: in-app, email, atau keduanya.');

    NotificationChannels::forget();
    expect(NotificationChannels::for('ProposalSubmitted'))->toBe([NotificationChannels::IN_APP]);
});

it('[NOT-CHANNEL-04] event kelas notifikasi tanpa deklarasi bisa ditetapkan dari layar', function () {
    NotificationChannelSetting::query()->where('event_type', FormChangedNotification::EVENT_TYPE)->delete();
    $admin = channelAdmin();

    $this->actingAs($admin)
        ->put(route('admin.reference.notification-channels.update', FormChangedNotification::EVENT_TYPE), [
            'channels' => [NotificationChannels::IN_APP],
        ])
        ->assertSessionHasNoErrors();

    NotificationChannels::forget();
    $entry = Activity::query()->where('description', 'Channel notifikasi diubah')->sole();

    expect(NotificationChannels::for(FormChangedNotification::EVENT_TYPE))->toBe([NotificationChannels::IN_APP])
        ->and($entry->causer_id)->toBe($admin->id)
        ->and($entry->properties['old'])->toBe(['channels' => []])
        ->and($entry->properties['new'])->toBe(['channels' => [NotificationChannels::IN_APP]]);
});

it('event di luar kelas notifikasi 404', function () {
    $this->actingAs(channelAdmin())
        ->put(route('admin.reference.notification-channels.update', 'ProposalArchived'), [
            'channels' => [NotificationChannels::IN_APP],
        ])
        ->assertNotFound();

    expect(NotificationChannelSetting::query()->where('event_type', 'ProposalArchived')->exists())->toBeFalse();
});

it('daftar channel memuat event yang belum dideklarasikan', function () {
    NotificationChannelSetting::query()->where('event_type', FormChangedNotification::EVENT_TYPE)->delete();
    setChannels('ProposalSubmitted', [NotificationChannels::IN_APP, NotificationChannels::EMAIL]);

    $props = [];
    $this->actingAs(channelAdmin())
        ->get(route('admin.reference.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$props) {
            $props = $page->toArray()['props'];
        });
    $rows = collect($props['notificationChannels'])->keyBy('eventType')->all();

    expect($props['channelOptions'])->toBe([
        ['value' => NotificationChannels::IN_APP, 'label' => 'In-app'],
        ['value' => NotificationChannels::EMAIL, 'label' => 'Email'],
    ])
        ->and(array_keys($rows))->toEqualCanonicalizing(array_keys(NotificationEventCatalog::declaredByClasses()))
        ->and($rows[FormChangedNotification::EVENT_TYPE])->toBe([
            'eventType' => FormChangedNotification::EVENT_TYPE, 'channels' => [], 'declared' => false,
        ])
        ->and($rows['ProposalSubmitted'])->toBe([
            'eventType' => 'ProposalSubmitted',
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'declared' => true,
        ]);
});

it('wewenang: users.manage saja 403', function () {
    setChannels('ProposalSubmitted', [NotificationChannels::IN_APP]);

    $this->actingAs(channelAdmin('users.manage'))
        ->put(route('admin.reference.notification-channels.update', 'ProposalSubmitted'), [
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        ])
        ->assertForbidden();

    NotificationChannels::forget();
    expect(NotificationChannels::for('ProposalSubmitted'))->toBe([NotificationChannels::IN_APP]);
});
