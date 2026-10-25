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

function registrationUnit(string $name, string $type, bool $active, ?Organization $parent = null): Organization
{
    return Organization::create([
        'name' => $name,
        'type' => $type,
        'parent_id' => $parent?->id,
        'is_active' => $active,
    ]);
}

// BR-IAM-01 — RegisterResearcher menolak unit nonaktif, jadi unit itu tidak ditawarkan.
test('menawarkan hanya prodi aktif di bawah fakultas aktif', function () {
    $fakultas = registrationUnit('Fakultas Teknologi Industri', 'faculty', true);
    $mesin = registrationUnit('Teknik Mesin', 'study_program', true, $fakultas);
    registrationUnit('Teknik Kimia', 'study_program', false, $fakultas);
    $pensiun = registrationUnit('Fakultas Lama', 'faculty', false);
    registrationUnit('Teknik Lama', 'study_program', true, $pensiun);
    registrationUnit('Fakultas Kosong', 'faculty', true);

    $this->get(route('register'))->assertInertia(fn ($page) => $page
        ->component('Auth/RegisterPage')
        ->has('faculties', 1)
        ->where('faculties.0.id', $fakultas->id)
        ->where('faculties.0.name', 'Fakultas Teknologi Industri')
        ->where('faculties.0.study_programs', [['id' => $mesin->id, 'name' => 'Teknik Mesin']])
        ->missing('roles')
        ->missing('studyPrograms'));
});

test('mengurutkan fakultas dan prodi menurut nama', function () {
    $sains = registrationUnit('Fakultas Sains', 'faculty', true);
    $industri = registrationUnit('Fakultas Industri', 'faculty', true);
    registrationUnit('Matematika', 'study_program', true, $sains);
    registrationUnit('Fisika', 'study_program', true, $sains);
    registrationUnit('Teknik Mesin', 'study_program', true, $industri);

    $this->get(route('register'))->assertInertia(fn ($page) => $page
        ->where('faculties.0.name', 'Fakultas Industri')
        ->where('faculties.1.study_programs.0.name', 'Fisika')
        ->where('faculties.1.study_programs.1.name', 'Matematika'));
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
