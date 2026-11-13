<?php

// Pemilik 2026-11-13 — Jurusan (`department`) mengelompokkan prodi di bawah fakultas. Pencocokan akses
// tetap ancestor-or-self (ADR 0013), jadi yang diuji di sini titik yang dulu menganggap induk prodi = fakultas.

use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\RegistrableStudyPrograms;
use App\Queries\Reporting\UnitFaculty;
use App\Queries\Submission\ResearcherStudyProgram;
use Database\Seeders\OrganizationSeeder;

function departmentTree(): array
{
    $faculty = Organization::create(['name' => 'Fakultas Uji', 'type' => 'faculty']);
    $department = Organization::create(['name' => 'Jurusan Uji', 'type' => 'department', 'parent_id' => $faculty->id]);
    $program = Organization::create(['name' => 'Prodi Uji', 'type' => 'study_program', 'parent_id' => $department->id]);

    return [$faculty, $department, $program];
}

it('menyemai Fakultas → Jurusan → Prodi sesuai daftar pemilik', function () {
    $this->seed(OrganizationSeeder::class);

    $faculties = Organization::query()->where('type', 'faculty')->get();
    $departments = Organization::query()->where('type', 'department')->get();
    $programs = Organization::query()->where('type', 'study_program')->with('parent')->get();

    expect($faculties)->toHaveCount(3)
        ->and($departments)->toHaveCount(6)
        ->and($programs)->toHaveCount(26)
        ->and($departments->every(fn (Organization $d): bool => $faculties->contains('id', $d->parent_id)))->toBeTrue()
        ->and($programs->every(fn (Organization $p): bool => $p->parent?->type === 'department'))->toBeTrue()
        ->and(Organization::query()->where('name', 'Teknik Geomatika')->value('parent_id'))
        ->toBe(Organization::query()->where('name', 'Jurusan Teknik Sipil dan Perencanaan')->value('id'));
});

it('memindahkan prodi lama ke jurusannya tanpa duplikat saat disemai ulang', function () {
    $faculty = Organization::create(['name' => 'Fakultas Sains dan Teknologi Informasi', 'type' => 'faculty']);
    $old = Organization::create(['name' => 'Informatika', 'type' => 'study_program', 'parent_id' => $faculty->id]);

    $this->seed(OrganizationSeeder::class);
    $this->seed(OrganizationSeeder::class);

    expect(Organization::query()->where('name', 'Informatika')->count())->toBe(1)
        ->and($old->fresh()?->parent?->name)->toBe('Jurusan Teknik Elektro, Informatika, dan Bisnis');
});

it('registrasi menawarkan prodi di bawah jurusan, dikelompokkan per jurusan', function () {
    [, , $program] = departmentTree();

    $groups = RegistrableStudyPrograms::run();

    expect($groups)->toHaveCount(1)
        ->and($groups->first()['name'])->toBe('Jurusan Uji (Fakultas Uji)')
        ->and($groups->first()['study_programs'])->toBe([['id' => $program->id, 'name' => 'Prodi Uji']]);
});

it('registrasi tidak menawarkan prodi bila jurusan atau fakultasnya nonaktif', function () {
    [$faculty, $department] = departmentTree();
    $department->update(['is_active' => false]);
    expect(RegistrableStudyPrograms::run())->toHaveCount(0);

    $department->update(['is_active' => true]);
    $faculty->update(['is_active' => false]);
    expect(RegistrableStudyPrograms::run())->toHaveCount(0);
});

it('nama fakultas peneliti diambil dari leluhur bertipe fakultas, bukan induk langsung', function () {
    [, , $program] = departmentTree();
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'organization_id' => $program->id]);

    expect(ResearcherStudyProgram::run($user->fresh())['faculty']['name'] ?? null)->toBe('Fakultas Uji');
});

it('statistik memetakan prodi di bawah jurusan ke fakultasnya (BR-RPT-06)', function () {
    [$faculty, , $program] = departmentTree();

    $row = UnitFaculty::run()->where('unit_id', $program->id)->first();

    expect($row?->faculty_id)->toBe($faculty->id);
});
