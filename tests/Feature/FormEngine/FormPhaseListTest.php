<?php

// tests/Feature/FormEngine/FormPhaseListTest.php
//
// Daftar tahap formulir: filter aktif bertipe boolean di PostgreSQL, parameter urutan dan
// halaman dibatasi whitelist, dan endpoint JSON tanpa pemanggil sudah dicabut.

use App\Models\FormPhase;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
});

/** @return list<string> */
function listedTitles(string $query): array
{
    $titles = [];

    test()->actingAs(test()->admin)
        ->get(route('admin.form-phases.index').$query)
        ->assertOk()
        ->assertInertia(function ($page) use (&$titles) {
            $titles = $page->toArray()['props']['formPhases']['data'] ?? [];
            $titles = array_column($titles, 'title');
        });

    return $titles;
}

it('filters the phase list by active state', function () {
    FormPhase::create(['title' => 'Aktif', 'is_active' => true]);
    FormPhase::create(['title' => 'Nonaktif', 'is_active' => false]);

    expect(listedTitles('?is_active=active'))->toBe(['Aktif'])
        ->and(listedTitles('?is_active=inactive'))->toBe(['Nonaktif'])
        ->and(listedTitles('?is_active=all'))->toHaveCount(2);
});

it('keeps the phase list working for an unknown sort column or direction', function () {
    FormPhase::create(['title' => 'B']);
    FormPhase::create(['title' => 'A']);

    expect(listedTitles('?sort_by=password&sort_order=sideways'))->toHaveCount(2)
        ->and(listedTitles('?sort_by=title&sort_order=asc'))->toBe(['A', 'B']);
});

it('caps the phase list page size', function () {
    FormPhase::create(['title' => 'Satu']);

    $this->actingAs($this->admin)
        ->get(route('admin.form-phases.index').'?per_page=100000')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('formPhases.per_page', 100)
            ->where('filters.per_page', 100));

    $this->actingAs($this->admin)
        ->get(route('admin.form-phases.index').'?per_page=-5')
        ->assertOk();
});

it('no longer exposes the unused form access control JSON endpoint', function () {
    expect(Route::has('admin.api.form-access-controls'))->toBeFalse();
});
