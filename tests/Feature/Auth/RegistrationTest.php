<?php

// tests/Feature/Auth/RegistrationTest.php
//
// BR-IAM-01, BR-IAM-12 — registrasi mandiri.
//
// Versi sebelumnya mendaftar dengan `test@example.com` dan lulus, karena
// controller punya cabang `app()->environment('testing')` yang melewati
// seluruh aturan. Test yang menguji jalur lain tidak membuktikan apa pun
// tentang jalur yang sesungguhnya berjalan; cabangnya sudah dicabut.

use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Role;

function registrationOrganization(): Organization
{
    Role::findOrCreate('researcher', 'web');

    return Organization::create([
        'name' => 'Teknik Mesin '.fake()->unique()->numerify('####'),
        'type' => 'study_program',
        'parent_id' => null,
        'is_active' => true,
    ]);
}

test('registration screen can be rendered', function () {
    $this->get('/register')->assertStatus(200);
});

test('new users can register with an ITK email', function () {
    $organization = registrationOrganization();

    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@itk.ac.id',
        'password' => 'password',
        'password_confirmation' => 'password',
        'nidn' => '0011223344',
        'organization_id' => $organization->id,
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::where('email', 'budi@itk.ac.id')->sole();

    expect($user->hasRole('researcher'))->toBeTrue()
        ->and($user->userProfile->organization_id)->toBe($organization->id)
        ->and($user->userProfile->verification_status)->toBe(VerificationStatus::Pending);
});

test('registration is rejected outside the ITK domain', function () {
    $organization = registrationOrganization();

    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'nidn' => '0011223344',
        'organization_id' => $organization->id,
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::where('email', 'budi@example.com')->exists())->toBeFalse();
});
