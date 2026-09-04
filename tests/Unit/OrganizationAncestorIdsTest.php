<?php

// ADR 0013 — ancestorIds() menelusuri NAIK, kebalikan dari subtreeIds().

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Organization::ancestorIds() requires PostgreSQL recursive CTE.');
    }

    Cache::flush();

    $this->itk = Organization::create([
        'name' => 'ITK', 'type' => 'institution', 'parent_id' => null, 'is_active' => true,
    ]);
    $this->fti = Organization::create([
        'name' => 'FTI', 'type' => 'faculty', 'parent_id' => $this->itk->id, 'is_active' => true,
    ]);
    $this->informatika = Organization::create([
        'name' => 'Informatika', 'type' => 'study_program', 'parent_id' => $this->fti->id, 'is_active' => true,
    ]);
});

it('returns self and every ancestor for a three-level hierarchy', function () {
    $ids = Organization::ancestorIds($this->informatika->id);

    expect($ids)
        ->toContain($this->informatika->id)
        ->toContain($this->fti->id)
        ->toContain($this->itk->id)
        ->and(count($ids))->toBe(3);
});

it('returns only itself for a root organization', function () {
    expect(Organization::ancestorIds($this->itk->id))->toBe([$this->itk->id]);
});

it('does not walk downward the way subtreeIds does', function () {
    $ancestors = Organization::ancestorIds($this->fti->id);

    expect($ancestors)->toContain($this->itk->id)
        ->and($ancestors)->not->toContain($this->informatika->id);
});

it('stops climbing at an inactive ancestor', function () {
    $this->fti->update(['is_active' => false]);

    $ids = Organization::ancestorIds($this->informatika->id);

    // Node awal selalu ikut, tapi rantai terputus di FTI yang nonaktif — ITK di atasnya
    // tidak lagi terjangkau, sama seperti subtreeIds memangkas cabang nonaktif.
    expect($ids)->toBe([$this->informatika->id]);
});

it('invalidates a descendant cache when its ancestor is moved', function () {
    $sibling = Organization::create([
        'name' => 'FSAINS', 'type' => 'faculty', 'parent_id' => $this->itk->id, 'is_active' => true,
    ]);

    expect(Organization::ancestorIds($this->informatika->id))->toContain($this->fti->id);
    expect(Cache::has("org_ancestors_{$this->informatika->id}"))->toBeTrue();

    // Informatika dipindah ke FSAINS: rantai leluhurnya berubah walau ia sendiri tak disentuh.
    $this->informatika->update(['parent_id' => $sibling->id]);

    $ids = Organization::ancestorIds($this->informatika->id);

    expect($ids)->toContain($sibling->id)
        ->and($ids)->not->toContain($this->fti->id);
});

it('invalidates caches of descendants when a mid-level node is deactivated', function () {
    expect(Organization::ancestorIds($this->informatika->id))->toContain($this->itk->id);

    $this->fti->update(['is_active' => false]);

    expect(Organization::ancestorIds($this->informatika->id))->not->toContain($this->itk->id);
});
