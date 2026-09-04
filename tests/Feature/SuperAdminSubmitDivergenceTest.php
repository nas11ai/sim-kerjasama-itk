<?php

// tests/Feature/SuperAdminSubmitDivergenceTest.php
//
// Register #1 — TERSELESAIKAN 2026-09-04 (ADR 0015).
//
// Dulu Gate::before() memberi Super Admin setiap ability tanpa syarat, sementara
// SubmitFormSubmission menegakkan BR-SM-04 sendiri. Akibatnya UI menampilkan tombol
// Submit yang dijamin gagal saat ditekan. Sekarang `submit` dan `resubmit` dikecualikan
// dari bypass, jadi Policy dan Action berkata sama.
//
// Test ini kini MENEGAKKAN aturannya, bukan sekadar memotret perilaku.

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

function superAdmin(): User
{
    Role::findOrCreate('Super Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    return $admin;
}

it('denies a non-owner Super Admin the submit ability — BR-SM-04 is a domain invariant', function () {
    $admin = superAdmin();
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    expect($admin->can('submit', $submission))->toBeFalse();
});

it('keeps Policy and Action in agreement for a non-owner Super Admin', function () {
    $admin = superAdmin();
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    expect($admin->can('submit', $submission))->toBeFalse()
        ->and(fn () => SubmitFormSubmission::run($submission, $admin))
        ->toThrow(AuthorizationException::class);
});

it('still lets Super Admin bypass abilities that carry no domain invariant', function () {
    $admin = superAdmin();
    $submission = FormSubmission::factory()->create(['status' => Draft::class]);

    expect($admin->can('view', $submission))->toBeTrue()
        ->and($admin->can('update', $submission))->toBeTrue()
        ->and($admin->can('withdraw', $submission))->toBeTrue();
});

it('lets the owner submit their own draft', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'status' => Draft::class,
        'submitted_by' => $owner->id,
    ]);

    expect($owner->can('submit', $submission))->toBeTrue();
});
