<?php

use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});

// BR-RPT-09 — ganti kata sandi tercatat; BR-IAM-15 — perubahan oleh diri sendiri tanpa notifikasi.
test('audit ganti kata sandi tanpa properti', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    $entry = Activity::query()->where('log_name', AuditTrail::LOG_NAME)->where('description', 'Kata sandi diganti')->sole();

    expect($entry->causer_id)->toBe($user->id)
        ->and($entry->properties->all())->toBe([])
        ->and((string) json_encode($entry->getAttributes()))->not->toContain('new-password')->not->toContain('$2y$');
});

test('ganti kata sandi tidak mengirim notifikasi', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    Notification::assertNothingSent();
});
