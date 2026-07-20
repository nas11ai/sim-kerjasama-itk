<?php

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('facultyOptions returns faculty tree with study_program children', function () {
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

    $options = Organization::facultyOptions();

    expect($options)->toHaveCount(1)
        ->and($options[0]['id'])->toBe($faculty->id)
        ->and($options[0]['name'])->toBe('Fakultas Teknik')
        ->and($options[0]['study_programs'])->toHaveCount(1)
        ->and($options[0]['study_programs'][0]['id'])->toBe($prodi->id)
        ->and($options[0]['study_programs'][0]['faculty_id'])->toBe($faculty->id);
});
