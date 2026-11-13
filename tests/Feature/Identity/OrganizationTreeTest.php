<?php

// ADMIN-08-02 — kontrak Struktur Organisasi, wewenang `organizations.manage` (W105), pohon yang memuat
// unit nonaktif dan data bersiklus, dan sensus yang sama dengan penjaga Action (W116).

use App\Actions\Identity\DeactivateOrganization;
use App\Actions\Identity\DeleteOrganization;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Identity\OrganizationSubtree;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function organizationScreenActor(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('organizations.manage');

    return $user;
}

function organizationScreenUnit(string $name, ?Organization $parent = null, bool $active = true, string $type = 'unit'): Organization
{
    return Organization::create(['name' => $name, 'type' => $type, 'parent_id' => $parent?->id, 'is_active' => $active]);
}

function organizationScreenMember(Organization $unit, bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $unit->id]);

    return $user;
}

/** @return array<string, mixed> props halaman */
function organizationScreenProps(array $query = []): array
{
    $props = [];
    test()->actingAs(organizationScreenActor())
        ->get(route('admin.organizations.index', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props;
}

/** Menjalankan Action lalu membatalkannya; true bila Action menolak. */
function organizationScreenRefuses(callable $action): bool
{
    DB::beginTransaction();

    try {
        $action();

        return false;
    } catch (ValidationException) {
        return true;
    } finally {
        DB::rollBack();
    }
}

it('kontrak struktur organisasi', function () {
    $itk = organizationScreenUnit('ITK', type: 'institution');
    organizationScreenUnit('FTI', $itk, type: 'faculty');

    $this->actingAs(organizationScreenActor())
        ->get(route('admin.organizations.index', ['unit' => $itk->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SystemConfig/OrganizationPage')
            ->has('tree', 2)
            ->has('tree.0', fn (AssertableInertia $node) => $node
                ->where('id', $itk->id)
                ->where('name', 'ITK')
                ->where('typeLabel', 'Institusi')
                ->where('isActive', true)
                ->where('parentId', null)
                ->where('depth', 0)
                ->where('hasChildren', true))
            ->has('selected', fn (AssertableInertia $unit) => $unit
                ->where('id', $itk->id)
                ->where('name', 'ITK')
                ->where('type', 'institution')
                ->where('typeLabel', 'Institusi')
                ->where('isActive', true)
                ->where('parentId', null)
                ->has('census', fn (AssertableInertia $census) => $census
                    ->where('directProfiles', 0)
                    ->where('directActiveUsers', 0)
                    ->where('subtreeProfiles', 0)
                    ->where('subtreeActiveUsers', 0)
                    ->where('unitsWithActiveUsers', 0)
                    ->where('pendingInvitations', 0))
                ->where('canDeactivate', true)
                ->where('canDelete', true)
                ->where('canReactivate', false)
                ->where('revivableInvitations', 0))
            ->has('typeOptions', 5)
            ->has('parentOptions', 2));
});

it('wewenang: organizations.manage 200, users.manage saja 403, schemes.manage saja 403, peneliti 403', function () {
    $unit = organizationScreenUnit('Pusat Studi');
    $usersOnly = User::factory()->create();
    $usersOnly->givePermissionTo('users.manage');
    $schemesOnly = User::factory()->create();
    $schemesOnly->givePermissionTo('schemes.manage');
    $researcher = User::factory()->create();
    $researcher->assignRole('researcher');

    $this->actingAs(organizationScreenActor())->get(route('admin.organizations.index'))->assertOk();

    foreach ([$usersOnly, $schemesOnly, $researcher] as $actor) {
        $this->actingAs($actor)->get(route('admin.organizations.index'))->assertForbidden();
        $this->actingAs($actor)->post(route('admin.organizations.store'), ['name' => 'Baru', 'type' => 'unit'])->assertForbidden();
        $this->actingAs($actor)->put(route('admin.organizations.update', $unit), ['name' => 'Lain'])->assertForbidden();
        $this->actingAs($actor)->post(route('admin.organizations.move-user', $unit), ['user_id' => $actor->id])->assertForbidden();
        $this->actingAs($actor)->post(route('admin.organizations.deactivate', $unit))->assertForbidden();
        $this->actingAs($actor)->delete(route('admin.organizations.destroy', $unit))->assertForbidden();
        $this->actingAs($actor)->post(route('admin.organizations.reactivate', $unit))->assertForbidden();
    }

    expect($unit->fresh())->not->toBeNull()
        ->and($unit->fresh()->name)->toBe('Pusat Studi')
        ->and($unit->fresh()->is_active)->toBeTrue();
});

it('wewenang: operator peran seeder mengelola organisasi tanpa users.manage', function () {
    $from = organizationScreenUnit('Teknik Kimia');
    $to = organizationScreenUnit('Teknik Mesin');
    $budi = organizationScreenMember($from);
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)->get(route('admin.organizations.index'))->assertOk();
    $this->actingAs($operator)
        ->post(route('admin.organizations.move-user', $to), ['user_id' => $budi->id])
        ->assertSessionHasNoErrors();
    // Wewenang akun (ubah peran) tetap milik users.manage.
    $this->actingAs($operator)
        ->put(route('admin.users.roles.update', $budi), ['roles' => ['researcher']])
        ->assertForbidden();

    expect($budi->fresh()->userProfile->organization_id)->toBe($to->id);
});

it('pohon memuat unit nonaktif dan urut depth-first', function () {
    $itk = organizationScreenUnit('ITK');
    $teknologi = organizationScreenUnit('Fakultas Teknologi', $itk, active: false);
    organizationScreenUnit('Informatika', $teknologi);
    organizationScreenUnit('Fakultas Sains', $itk);
    organizationScreenUnit('Akademi');

    $tree = organizationScreenProps()['tree'];

    expect(array_map(fn (array $node) => [$node['name'], $node['depth'], $node['hasChildren']], $tree))->toBe([
        ['Akademi', 0, false],
        ['ITK', 0, true],
        ['Fakultas Sains', 1, false],
        ['Fakultas Teknologi', 1, true],
        ['Informatika', 2, false],
    ])->and($tree[3]['isActive'])->toBeFalse();
});

it('pohon menampilkan unit bersiklus sebagai akar', function () {
    $a = organizationScreenUnit('Unit A');
    $b = organizationScreenUnit('Unit B', $a);
    // Data lama bersiklus ditulis lewat query builder, tanpa model event.
    DB::table('organizations')->where('id', $a->id)->update(['parent_id' => $b->id]);

    $tree = organizationScreenProps()['tree'];

    expect(array_map(fn (array $node) => [$node['id'], $node['depth']], $tree))->toBe([
        [$a->id, 0],
        [$b->id, 1],
    ])->and($tree[1]['hasChildren'])->toBeFalse();
});

it('subtree berhenti pada data bersiklus', function () {
    $a = organizationScreenUnit('Unit A');
    $b = organizationScreenUnit('Unit B', $a);
    DB::table('organizations')->where('id', $a->id)->update(['parent_id' => $b->id]);

    $ids = OrganizationSubtree::run($a->id);
    sort($ids);

    expect($ids)->toBe([$a->id, $b->id]);
});

it('sensus unit terpilih sama dengan penjaga Action', function () {
    $itk = organizationScreenUnit('ITK');
    $fti = organizationScreenUnit('FTI', $itk);
    $informatika = organizationScreenUnit('Informatika', $fti);
    $kimia = organizationScreenUnit('Teknik Kimia', $fti, active: false);
    $mitra = organizationScreenUnit('Mitra Industri');
    organizationScreenMember($informatika);
    organizationScreenMember($informatika);
    organizationScreenMember($kimia, active: false);
    InvitationToken::factory()->create(['organization_id' => $kimia->id]);
    InvitationToken::factory()->create(['organization_id' => $mitra->id]);

    $census = organizationScreenProps(['unit' => $fti->id])['selected']['census'];

    expect($census)->toBe([
        'directProfiles' => 0,
        'directActiveUsers' => 0,
        'subtreeProfiles' => 3,
        'subtreeActiveUsers' => 2,
        'unitsWithActiveUsers' => 1,
        'pendingInvitations' => 1,
    ]);

    foreach ([$itk, $fti, $informatika, $kimia, $mitra] as $unit) {
        $selected = organizationScreenProps(['unit' => $unit->id])['selected'];

        expect($selected['canDelete'])->toBe(!organizationScreenRefuses(fn () => DeleteOrganization::run($unit)), $unit->name);
        if ($unit->is_active) {
            expect($selected['canDeactivate'])->toBe(!organizationScreenRefuses(fn () => DeactivateOrganization::run($unit)), $unit->name);
        }
    }
});

it('unit tak dikenal di query tidak memilih apa pun', function (string $unit) {
    organizationScreenUnit('ITK');

    expect(organizationScreenProps(['unit' => $unit])['selected'])->toBeNull();
})->with(['999999', 'abc', '']);

it('unit nonaktif tidak bisa dinonaktifkan lagi', function () {
    $unit = organizationScreenUnit('Lama', active: false);

    expect(organizationScreenProps(['unit' => $unit->id])['selected']['canDeactivate'])->toBeFalse();
});

// ---------- BR-IAM-18: memindahkan user dari panel unit (W120) ----------

/** @return list<array<string, mixed>> */
function organizationScreenCandidates(Organization $unit, string $search = ''): array
{
    $candidates = [];
    test()->actingAs(organizationScreenActor())
        ->get(route('admin.organizations.index', ['unit' => $unit->id, 'member_search' => $search]))
        ->assertInertia(function (AssertableInertia $page) use (&$candidates) {
            $page->reloadOnly('memberCandidates', function (AssertableInertia $reload) use (&$candidates) {
                $candidates = $reload->toArray()['props']['memberCandidates'];
            });
        });

    return $candidates;
}

it('kandidat pindah hanya anggota subtree unit terpilih', function () {
    $fti = organizationScreenUnit('FTI');
    $informatika = organizationScreenUnit('Informatika', $fti);
    $sains = organizationScreenUnit('Fakultas Sains');
    $budi = organizationScreenMember($informatika);
    $budi->update(['name' => 'Budi Santoso']);
    $sari = organizationScreenMember($informatika, active: false);
    $sari->update(['name' => 'Sari Dewi']);
    organizationScreenMember($sains)->update(['name' => 'Rina Wijaya']);

    expect(organizationScreenCandidates($fti))->toBe([
        ['value' => (string) $budi->id, 'label' => 'Budi Santoso', 'hint' => 'Informatika · Aktif', 'organizationId' => $informatika->id],
        ['value' => (string) $sari->id, 'label' => 'Sari Dewi', 'hint' => 'Informatika · Nonaktif', 'organizationId' => $informatika->id],
    ])->and(array_column(organizationScreenCandidates($fti, 'SARI'), 'label'))->toBe(['Sari Dewi'])
        ->and(organizationScreenCandidates($sains, 'budi'))->toBe([]);
});

it('kandidat dihitung hanya saat diminta', function () {
    $unit = organizationScreenUnit('Informatika');
    organizationScreenMember($unit);

    $this->actingAs(organizationScreenActor())
        ->get(route('admin.organizations.index', ['unit' => $unit->id, 'member_search' => '']))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('memberCandidates'));

    $this->actingAs(organizationScreenActor())
        ->get(route('admin.organizations.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->reloadOnly('memberCandidates', fn (AssertableInertia $reload) => $reload->has('memberCandidates', 0)));
});

it('[IAM-ORG-15] memindahkan user lewat layar membuka jalan deaktivasi', function () {
    $kimia = organizationScreenUnit('Teknik Kimia');
    $mesin = organizationScreenUnit('Teknik Mesin');
    $budi = organizationScreenMember($kimia);

    expect(organizationScreenProps(['unit' => $kimia->id])['selected']['canDeactivate'])->toBeFalse();

    $this->actingAs(organizationScreenActor())
        ->from(route('admin.organizations.index', ['unit' => $kimia->id]))
        ->post(route('admin.organizations.move-user', $mesin), ['user_id' => $budi->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.organizations.index', ['unit' => $kimia->id]));

    expect(organizationScreenProps(['unit' => $kimia->id])['selected']['canDeactivate'])->toBeTrue();
});

it('memindahkan user tanpa user_id yang valid ditolak di field', function (mixed $userId) {
    $unit = organizationScreenUnit('Teknik Mesin');

    $this->actingAs(organizationScreenActor())
        ->post(route('admin.organizations.move-user', $unit), ['user_id' => $userId])
        ->assertSessionHasErrors('user_id');
})->with([null, 'abc', 999999]);

it('canReactivate mengikuti status induk', function () {
    $active = organizationScreenUnit('Aktif');
    $root = organizationScreenUnit('Akar Lama', active: false);
    $under = organizationScreenUnit('Cabang Lama', $root, active: false);

    $canReactivate = fn (Organization $unit) => organizationScreenProps(['unit' => $unit->id])['selected']['canReactivate'];

    expect($canReactivate($root))->toBeTrue()
        ->and($canReactivate($under))->toBeFalse()
        ->and($canReactivate($active))->toBeFalse();
});
