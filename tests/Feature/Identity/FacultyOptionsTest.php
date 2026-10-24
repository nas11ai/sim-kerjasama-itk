<?php

use App\Models\Organization;
use App\Queries\Identity\FacultyOptions;

test('FacultyOptions returns faculty tree with study_program children', function () {
    $faculty = Organization::create([
        'name' => 'Fakultas Teknik',
        'type' => 'faculty',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $prodi = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => $faculty->id,
        'is_active' => true,
    ]);

    Organization::create([
        'name' => 'Institution Root',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $options = FacultyOptions::run();

    expect($options)->toHaveCount(1)
        ->and($options[0]['id'])->toBe($faculty->id)
        ->and($options[0]['name'])->toBe('Fakultas Teknik')
        ->and($options[0]['study_programs'])->toHaveCount(1)
        ->and($options[0]['study_programs'][0]['id'])->toBe($prodi->id)
        ->and($options[0]['study_programs'][0]['faculty_id'])->toBe($faculty->id);
});

test('FacultyOptions leaves out non-study_program children and sorts by name', function () {
    $faculty = Organization::create(['name' => 'Fakultas Sains', 'type' => 'faculty']);
    Organization::create(['name' => 'Jurusan Kimia', 'type' => 'department', 'parent_id' => $faculty->id]);
    Organization::create(['name' => 'Statistika', 'type' => 'study_program', 'parent_id' => $faculty->id]);
    Organization::create(['name' => 'Fisika', 'type' => 'study_program', 'parent_id' => $faculty->id]);

    $programs = FacultyOptions::run()->firstWhere('id', $faculty->id)['study_programs'] ?? [];

    expect(array_column($programs, 'name'))->toBe(['Fisika', 'Statistika']);
});
