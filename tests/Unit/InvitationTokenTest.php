<?php

use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('validates a token as valid when active and within max uses limit', function () {
    $token = new InvitationToken([
        'token' => Str::random(64),
        'permissions' => ['users.manage'],
        'max_uses' => 5,
        'used_count' => 2,
        'expires_at' => now()->addDays(2),
    ]);

    expect($token->isValid())->toBeTrue();
});

it('validates a token without max_uses or expires_at as valid', function () {
    $token = new InvitationToken([
        'token' => Str::random(64),
        'permissions' => ['users.manage'],
        'max_uses' => null,
        'used_count' => 10,
        'expires_at' => null,
    ]);

    expect($token->isValid())->toBeTrue();
});

it('invalidates an expired token', function () {
    $token = new InvitationToken([
        'token' => Str::random(64),
        'permissions' => ['users.manage'],
        'max_uses' => 10,
        'used_count' => 1,
        'expires_at' => now()->subDay(),
    ]);

    expect($token->isValid())->toBeFalse();
});

it('invalidates a token that reached max uses', function () {
    $token = new InvitationToken([
        'token' => Str::random(64),
        'permissions' => ['users.manage'],
        'max_uses' => 3,
        'used_count' => 3,
        'expires_at' => now()->addDays(1),
    ]);

    expect($token->isValid())->toBeFalse();
});

it('increments used_count when consume is called', function () {
    $org = Organization::create([
        'name' => 'Fakultas Teknologi Industri',
        'type' => 'faculty',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $token = InvitationToken::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'used_count' => 1,
        'max_uses' => 5,
    ]);

    $token->consume();

    expect($token->fresh()->used_count)->toBe(2);
});
