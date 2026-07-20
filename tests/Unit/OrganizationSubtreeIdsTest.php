<?php

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Organization::subtreeIds() requires PostgreSQL recursive CTE.');
    }

    Cache::flush();
});

it('returns root and all descendants for a three-level hierarchy', function () {
    $institution = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $faculty = Organization::create([
        'name' => 'FSTI',
        'type' => 'faculty',
        'parent_id' => $institution->id,
        'is_active' => true,
    ]);

    $prodi = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => $faculty->id,
        'is_active' => true,
    ]);

    $ids = Organization::subtreeIds($institution->id);

    expect($ids)
        ->toContain($institution->id)
        ->toContain($faculty->id)
        ->toContain($prodi->id)
        ->and(count($ids))->toBe(3);
});

it('excludes inactive descendants but still includes the root node', function () {
    $institution = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $activeFaculty = Organization::create([
        'name' => 'FSTI',
        'type' => 'faculty',
        'parent_id' => $institution->id,
        'is_active' => true,
    ]);

    Organization::create([
        'name' => 'Inactive Faculty',
        'type' => 'faculty',
        'parent_id' => $institution->id,
        'is_active' => false,
    ]);

    $ids = Organization::subtreeIds($institution->id);

    expect($ids)
        ->toContain($institution->id)
        ->toContain($activeFaculty->id)
        ->and(count($ids))->toBe(2);
});

it('caches subtree ids and invalidates after organization change', function () {
    $institution = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $faculty = Organization::create([
        'name' => 'FSTI',
        'type' => 'faculty',
        'parent_id' => $institution->id,
        'is_active' => true,
    ]);

    $first = Organization::subtreeIds($institution->id);
    expect($first)->toContain($faculty->id)->and(count($first))->toBe(2);
    expect(Cache::has("org_subtree_{$institution->id}"))->toBeTrue();

    $prodi = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => $faculty->id,
        'is_active' => true,
    ]);

    $second = Organization::subtreeIds($institution->id);

    expect($second)
        ->toContain($institution->id)
        ->toContain($faculty->id)
        ->toContain($prodi->id)
        ->and(count($second))->toBe(3);
});
