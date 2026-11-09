<?php

// RT1, BR-IAM-05, BR-IAM-21 — deaktivasi dan "akhiri semua sesi" mendorong `sessions.ended` ke
// tab user itu supaya ia keluar seketika. Penegakan tetap di server; dorongan ini diantrekan dan
// dikirim setelah commit (W169), jadi Reverb yang mati tidak menggagalkan deaktivasi.

use App\Enums\SessionEndReason;
use App\Events\Identity\UserSessionsEnded;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

it('[IAM-DEACT-09] deaktivasi mendorong sessions.ended ke kanal user itu', function () {
    Event::fake([UserSessionsEnded::class]);
    $sari = User::factory()->create(['is_active' => true]);

    $this->actingAs(sessionViewer('users.manage'))
        ->post(route('admin.users.deactivate', $sari))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Event::assertDispatched(UserSessionsEnded::class,
        fn (UserSessionsEnded $e) => $e->userId === $sari->id && $e->reason === SessionEndReason::Deactivated);
});

it('deaktivasi yang ditolak karena pengajuan aktif tidak mendorong apa pun', function () {
    Event::fake([UserSessionsEnded::class]);
    $owner = User::factory()->create(['is_active' => true]);
    FormSubmission::factory()->create([
        'submitted_by' => $owner->id, 'scheme_id' => null, 'status' => UnderReview::class,
    ]);

    $this->actingAs(sessionViewer('users.manage'))
        ->post(route('admin.users.deactivate', $owner))
        ->assertSessionHasErrors();

    Event::assertNotDispatched(UserSessionsEnded::class);
});

it('[IAM-SESSION-01] mengakhiri sesi mendorong sessions.ended dengan alasan diakhiri', function () {
    Event::fake([UserSessionsEnded::class]);
    $sari = User::factory()->create(['is_active' => true]);

    $this->actingAs(sessionViewer('users.manage'))
        ->post(route('admin.users.sessions.end', $sari))
        ->assertRedirect();

    Event::assertDispatched(UserSessionsEnded::class,
        fn (UserSessionsEnded $e) => $e->userId === $sari->id && $e->reason === SessionEndReason::Ended);
});

it('bentuk siaran sessions.ended', function () {
    $e = new UserSessionsEnded(7, SessionEndReason::Ended);

    expect($e->broadcastOn()->name)->toBe('private-App.Models.User.7')
        ->and($e->broadcastAs())->toBe('sessions.ended')
        ->and($e->broadcastWith())->toBe(['reason' => 'diakhiri'])
        ->and($e)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($e)->not->toBeInstanceOf(ShouldBroadcastNow::class)   // W169, Review Focus 1
        ->and($e)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('halaman login menampilkan alasan sesi berakhir dari query', function (string $sesi, ?string $notice) {
    $this->get(route('login', ['sesi' => $sesi]))
        ->assertInertia(fn (Assert $p) => $p->where('notice', $notice));
})->with([
    ['nonaktif', 'Akun Anda dinonaktifkan. Hubungi LPPM bila ini keliru.'],
    ['diakhiri', 'Sesi Anda diakhiri oleh admin. Silakan login lagi.'],
    ['palsu', null],
]);

it('halaman login tanpa query tidak membawa pemberitahuan', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('notice', null));
});

it('[IAM-DEACT-09] sesi yang masih login keluar dengan teks deaktivasi yang sama', function () {
    $sari = User::factory()->create(['is_active' => false]);

    $this->actingAs($sari)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', SessionEndReason::Deactivated->notice());
});
