<?php

// ADMIN-08-10 — kontrak Konfigurasi Umum, wewenang `schemes.manage` (W105), jumlah pemakai
// satu definisi dengan penegak hapus (W115), dan ganti nama ke namanya sendiri (W114).

use App\Models\BudgetComponent;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionTargetOutput;
use App\Models\TechnologyReadinessLevel;
use App\Models\User;
use App\Queries\Config\ReferenceEntityUsage;
use App\Support\Config\ReferenceEntityRegistry;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function generalConfigActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return list<array<string, mixed>> */
function generalCategories(): array
{
    $categories = [];
    test()->actingAs(generalConfigActor())
        ->get(route('admin.reference.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$categories) {
            $categories = $page->toArray()['props']['categories'];
        });

    return $categories;
}

it('kontrak konfigurasi umum', function () {
    $type = SchemeType::factory()->create(['name' => 'Penelitian Dasar']);
    Scheme::factory()->count(2)->create(['scheme_type_id' => $type->id]);

    $this->actingAs(generalConfigActor())
        ->get(route('admin.reference.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SystemConfig/GeneralPage')
            ->has('categories', count(ReferenceEntityRegistry::all()))
            ->has('categories.0', fn (AssertableInertia $category) => $category
                ->where('key', 'scheme-types')
                ->where('label', 'Tipe Skema')
                ->where('group', 'scheme')
                ->where('usedBy', 'Skema')
                ->where('editable', true)
                ->where('deactivatable', true)
                ->has('items.0', fn (AssertableInertia $item) => $item
                    ->where('id', $type->id)
                    ->where('name', 'Penelitian Dasar')
                    ->where('isActive', true)
                    ->where('usageCount', 2)))
            ->etc());
});

it('wewenang: pemegang schemes.manage 200, pemegang users.manage saja 403, peneliti 403', function () {
    $type = SchemeType::factory()->create();
    Permission::findOrCreate('users.manage', 'web');
    $usersOnly = User::factory()->create();
    $usersOnly->givePermissionTo('users.manage');

    $this->actingAs(generalConfigActor())->get(route('admin.reference.index'))->assertOk();

    foreach ([$usersOnly, generalConfigActor('researcher')] as $actor) {
        $this->actingAs($actor)->get(route('admin.reference.index'))->assertForbidden();
        $this->actingAs($actor)->post(route('admin.reference.store', 'scheme-types'), ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($actor)->put(route('admin.reference.update', ['scheme-types', $type->id]), ['name' => 'Lain'])->assertForbidden();
        $this->actingAs($actor)->post(route('admin.reference.toggle', ['scheme-types', $type->id]), ['is_active' => false])->assertForbidden();
        $this->actingAs($actor)->delete(route('admin.reference.destroy', ['scheme-types', $type->id]))->assertForbidden();
    }

    expect($type->fresh())->not->toBeNull()->and($type->fresh()->is_active)->toBeTrue();
});

it('jumlah pemakai sama dengan penegak hapus', function () {
    $schemeType = SchemeType::factory()->create();
    $scheme = Scheme::factory()->create(['scheme_type_id' => $schemeType->id]);
    $trl = TechnologyReadinessLevel::create(['name' => 'TRL 9', 'level' => 9]);
    $scheme->allowedTrls()->attach($trl->id);
    BudgetComponent::query()->create(['name' => 'Sewa Peralatan', 'is_active' => true]);
    OutputTypeDefinition::create(['code' => 'buku', 'name' => 'Buku', 'is_active' => true, 'requires_files' => false]);
    // SC-CONFIG-07 — target luaran ikut dihitung sebagai pemakai.
    SubmissionTargetOutput::create(['form_submission_id' => FormSubmission::factory()->create()->id, 'output_type' => 'buku']);

    $nonZero = 0;
    foreach (generalCategories() as $category) {
        $entity = ReferenceEntityRegistry::get($category['key']);
        foreach ($category['items'] as $item) {
            $record = $entity->model::query()->findOrFail($item['id']);
            expect($item['usageCount'])->toBe(ReferenceEntityUsage::of($entity, $record), "{$category['key']} #{$item['id']}");
            $nonZero += $item['usageCount'] > 0 ? 1 : 0;
        }
    }

    // Tipe skema, tipe submission, TRL, dan tipe luaran di atas masing-masing terpakai.
    expect($nonZero)->toBeGreaterThanOrEqual(4);
});

it('menyimpan data acuan dengan namanya sendiri tidak ditolak', function () {
    $type = SchemeType::factory()->create(['name' => 'Penelitian Dasar']);
    SchemeType::factory()->create(['name' => 'Riset Terapan']);
    $admin = generalConfigActor();

    $this->actingAs($admin)
        ->put(route('admin.reference.update', ['scheme-types', $type->id]), ['name' => 'Penelitian Dasar'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->put(route('admin.reference.update', ['scheme-types', $type->id]), ['name' => 'Riset Terapan'])
        ->assertSessionHasErrors(['name' => 'Nama itu sudah dipakai.']);

    expect($type->fresh()->name)->toBe('Penelitian Dasar');
});
