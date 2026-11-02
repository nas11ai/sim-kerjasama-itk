<?php

// BR-IAM-03 — satu definisi kelengkapan profil (telepon, bidang keahlian, foto),
// dibaca gerbang StartSubmission, banner dasbor, dan layar Lengkapi Profil.

use App\Data\View\Identity\OwnProfileData;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AccountStateGuard;

it('missingDetails menyebut kekurangan dalam urutan tetap', function () {
    expect(UserProfile::factory()->incomplete()->create()->missingDetails())->toBe(['phone', 'expertise', 'photo'])
        ->and(UserProfile::factory()->partiallyComplete()->create()->missingDetails())->toBe(['expertise', 'photo'])
        ->and(UserProfile::factory()->create()->missingDetails())->toBe([]);
});

it('isProfileComplete false bagi user tanpa profil', function () {
    $tanpaProfil = User::factory()->create();
    $lengkap = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $lengkap->id]);

    expect(AccountStateGuard::isProfileComplete($tanpaProfil))->toBeFalse()
        ->and(AccountStateGuard::isProfileComplete($lengkap->fresh()))->toBeTrue();
});

it('OwnProfileData melabeli kekurangan dan menyembunyikan path foto', function () {
    $sebagian = User::factory()->create();
    UserProfile::factory()->partiallyComplete()->create(['user_id' => $sebagian->id]);
    $lengkap = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $lengkap->id]);

    $data = OwnProfileData::of($sebagian->fresh());
    $foto = OwnProfileData::of($lengkap->fresh())->photoUrl;

    expect($data->missing)->toBe(['Bidang Keahlian', 'Foto Profil'])
        ->and($data->hasProfile)->toBeTrue()
        ->and($data->photoUrl)->toBeNull()
        ->and($foto)->toStartWith(route('profile.photo.show'))
        ->and($foto)->not->toContain('profil/uji.jpg');
});

it('OwnProfileData user tanpa profil tidak punya data tambahan', function () {
    $data = OwnProfileData::of(User::factory()->create());

    expect($data->hasProfile)->toBeFalse()
        ->and($data->missing)->toBe([])
        ->and($data->nidn)->toBeNull();
});
