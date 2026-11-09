<?php

// RT1 — otorisasi kanal broadcast. Dorongan realtime hanya mempercepat apa yang dilihat user;
// siapa yang boleh mendengar tetap ditentukan di server ([IAM-SESSION-03], W168).

use App\Broadcasting\IdentityActivityChannel;
use App\Broadcasting\UserChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

it('kanal privat user hanya untuk pemiliknya', function () {
    $sari = User::factory()->create();
    $budi = User::factory()->create();

    expect((new UserChannel)->join($sari, $sari->id))->toBeTrue()
        ->and((new UserChannel)->join($sari, $budi->id))->toBeFalse();
});

it('[IAM-SESSION-03] kanal aktivitas sesi hanya untuk users.verify atau users.manage', function () {
    expect((new IdentityActivityChannel)->join(sessionViewer('users.verify')))->toBeTrue()
        ->and((new IdentityActivityChannel)->join(sessionViewer('users.manage')))->toBeTrue()
        ->and((new IdentityActivityChannel)->join(User::factory()->create()))->toBeFalse();
});

it('routes/channels.php mendaftarkan kedua kanal', function () {
    expect(Broadcast::driver()->getChannels()->keys()->all())
        ->toContain('App.Models.User.{id}', 'identity.activity');
});
