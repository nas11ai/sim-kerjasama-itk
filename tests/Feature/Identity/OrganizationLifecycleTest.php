<?php

// tests/Feature/Identity/OrganizationLifecycleTest.php
//
// BR-IAM-08 dan BR-IAM-14 — dua operasi dengan ambang berbeda, dan itulah
// inti perkaranya (register #31).
//
//   hapus      → ditolak selama ada UserProfile APA PUN di subtree
//   nonaktif   → ditolak selama ada user AKTIF di subtree
//
// Celah di antaranya nyata: unit yang seluruh penghuninya sudah nonaktif boleh
// dipensiunkan, tapi belum boleh dihapus karena jejaknya masih dibaca.

use App\Actions\Identity\DeactivateOrganization;
use App\Actions\Identity\DeleteOrganization;
use App\Actions\Identity\MoveUserToOrganization;
use App\Actions\Identity\ReactivateOrganization;
use App\Actions\Identity\SaveOrganization;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormSubmission;
use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

function orgTree(): array
{
    $itk = Organization::create(['name' => 'ITK '.fake()->unique()->numerify('####'), 'type' => 'institution', 'parent_id' => null, 'is_active' => true]);
    $fti = Organization::create(['name' => 'FTI '.fake()->unique()->numerify('####'), 'type' => 'faculty', 'parent_id' => $itk->id, 'is_active' => true]);
    $prodi = Organization::create(['name' => 'Informatika '.fake()->unique()->numerify('####'), 'type' => 'study_program', 'parent_id' => $fti->id, 'is_active' => true]);

    return ['itk' => $itk, 'fti' => $fti, 'prodi' => $prodi];
}

function userIn(Organization $org, bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);
    UserProfile::factory()->create(['user_id' => $user->id, 'organization_id' => $org->id]);

    return $user;
}

it('[IAM-ORG-02] user di organisasi turunan ikut menghalangi penghapusan', function () {
    $t = orgTree();
    userIn($t['prodi']);

    // Penelusurannya ke seluruh subtree — implementasi yang hanya memeriksa
    // node terpilih akan menghapus fakultas beserta prodi yang masih berisi orang.
    expect(fn () => DeleteOrganization::run($t['fti']))->toThrow(ValidationException::class)
        ->and(Organization::find($t['fti']->id))->not->toBeNull();
});

it('[IAM-ORG-03] organisasi kosong di seluruh subtree bisa dihapus', function () {
    $t = orgTree();

    DeleteOrganization::run($t['fti']);

    expect(Organization::find($t['fti']->id))->toBeNull()
        ->and(Organization::find($t['prodi']->id))->toBeNull()
        // Induknya tidak ikut terhapus.
        ->and(Organization::find($t['itk']->id))->not->toBeNull();
});

it('[IAM-ORG-04] unit dengan user aktif tidak bisa dinonaktifkan', function () {
    $t = orgTree();
    userIn($t['prodi'], active: true);

    expect(fn () => DeactivateOrganization::run($t['prodi']))->toThrow(ValidationException::class)
        ->and($t['prodi']->fresh()->is_active)->toBeTrue();
});

it('[IAM-ORG-05] user aktif di unit turunan ikut menghalangi deaktivasi', function () {
    $t = orgTree();
    userIn($t['prodi'], active: true);

    expect(fn () => DeactivateOrganization::run($t['fti']))->toThrow(ValidationException::class)
        ->and($t['fti']->fresh()->is_active)->toBeTrue();
});

it('[IAM-ORG-06] unit yang seluruh user-nya nonaktif bisa dinonaktifkan', function () {
    $t = orgTree();
    userIn($t['prodi'], active: false);

    DeactivateOrganization::run($t['prodi']);

    expect($t['prodi']->fresh()->is_active)->toBeFalse();

    // Celah antara dua ambang: boleh dinonaktifkan, belum boleh dihapus.
    expect(fn () => DeleteOrganization::run($t['prodi']->fresh()))->toThrow(ValidationException::class);
});

it('[IAM-ORG-07] deaktivasi organisasi tidak menghapus apa pun', function () {
    $t = orgTree();
    $user = userIn($t['prodi'], active: false);

    DeactivateOrganization::run($t['prodi']);

    expect(UserProfile::where('user_id', $user->id)->exists())->toBeTrue()
        ->and(Organization::find($t['prodi']->id))->not->toBeNull()
        ->and(User::find($user->id))->not->toBeNull();
});

it('[IAM-ORG-08] organisasi nonaktif memutus rantai leluhur access control', function () {
    $t = orgTree();

    Permission::findOrCreate('submissions.create', 'web');
    $sari = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $sari->id, 'organization_id' => $t['prodi']->id]);
    $sari->givePermissionTo('submissions.create');

    $form = Form::factory()->create();
    FormAccessControl::create([
        'form_id' => $form->id,
        'permission' => 'submissions.create',
        'organization_id' => $t['itk']->id,
    ]);

    $service = app(FormAccessService::class);

    expect($service->canAccessForm($sari->fresh(), $form))->toBeTrue();

    // FTI di tengah rantai dinonaktifkan — penelusuran ke atas berhenti
    // sebelum sampai ke ITK tempat Form dipasang.
    $t['fti']->update(['is_active' => false]);

    expect($service->canAccessForm($sari->fresh(), $form))->toBeFalse();
});

it('[IAM-ORG-04] jalur HTTP menolak deaktivasi unit berisi user aktif', function () {
    Permission::findOrCreate('organizations.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('organizations.manage');

    $t = orgTree();
    userIn($t['prodi'], active: true);

    $this->actingAs($admin)
        ->post(route('admin.organizations.deactivate', $t['prodi']))
        ->assertSessionHasErrors('organization_id');

    expect($t['prodi']->fresh()->is_active)->toBeTrue();
});

// ---------- BR-IAM-17, BR-IAM-18: menyusun pohon dan memindahkan orangnya ----------

function operatorOrganisasi(): User
{
    Permission::findOrCreate('organizations.manage', 'web');
    $user = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $user->id]);
    $user->givePermissionTo('organizations.manage');

    return $user->fresh();
}

it('[IAM-ORG-09] operator membuat unit induk baru', function () {
    $unit = SaveOrganization::run(operatorOrganisasi(), [
        'name' => 'Pusat Studi Energi',
        'type' => 'research_center',
        'parent_id' => null,
    ]);

    expect($unit->parent_id)->toBeNull()
        ->and($unit->is_active)->toBeTrue();
});

it('[IAM-ORG-09] pemegang users.manage saja tidak bisa menyusun struktur', function () {
    // W105 — struktur organisasi milik organizations.manage, bukan wewenang akun.
    Permission::findOrCreate('users.manage', 'web');
    $usersOnly = User::factory()->create();
    $usersOnly->givePermissionTo('users.manage');

    expect(fn () => SaveOrganization::run($usersOnly, ['name' => 'Pusat Baru', 'type' => 'unit']))
        ->toThrow(AuthorizationException::class);
});

it('[IAM-ORG-10] operator membuat sub-unit di bawah unit yang ada', function () {
    $operator = operatorOrganisasi();
    $fakultas = SaveOrganization::run($operator, ['name' => 'FTI', 'type' => 'faculty']);

    $prodi = SaveOrganization::run($operator, [
        'name' => 'Teknik Mesin',
        'type' => 'study_program',
        'parent_id' => $fakultas->id,
    ]);

    expect($prodi->parent_id)->toBe($fakultas->id)
        ->and(Organization::subtreeIds($fakultas->id))->toContain($prodi->id);
});

it('[IAM-ORG-11] mengganti nama unit tidak memutus keanggotaan', function () {
    $operator = operatorOrganisasi();
    $unit = SaveOrganization::run($operator, ['name' => 'Teknik Informatika', 'type' => 'study_program']);

    $anggota = collect(range(1, 3))->map(function () use ($unit) {
        $u = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $u->id, 'organization_id' => $unit->id]);

        return $u;
    });

    SaveOrganization::run($operator, ['name' => 'Informatika'], $unit);

    expect($unit->fresh()->name)->toBe('Informatika')
        ->and(UserProfile::whereIn('user_id', $anggota->pluck('id'))->where('organization_id', $unit->id)->count())
        ->toBe(3);
});

it('[IAM-ORG-12] operator memindahkan unit ke induk lain', function () {
    $operator = operatorOrganisasi();
    $sains = SaveOrganization::run($operator, ['name' => 'Fakultas Sains', 'type' => 'faculty']);
    $fti = SaveOrganization::run($operator, ['name' => 'FTI', 'type' => 'faculty']);
    $kimia = SaveOrganization::run($operator, [
        'name' => 'Teknik Kimia', 'type' => 'study_program', 'parent_id' => $sains->id,
    ]);

    SaveOrganization::run($operator, ['parent_id' => $fti->id], $kimia);

    expect($kimia->fresh()->parent_id)->toBe($fti->id)
        ->and(Organization::subtreeIds($fti->id))->toContain($kimia->id)
        ->and(Organization::subtreeIds($sains->id))->not->toContain($kimia->id);
});

it('[IAM-ORG-13] unit tidak bisa dipindah ke bawah keturunannya sendiri', function () {
    $operator = operatorOrganisasi();
    $fti = SaveOrganization::run($operator, ['name' => 'FTI', 'type' => 'faculty']);
    $mesin = SaveOrganization::run($operator, [
        'name' => 'Teknik Mesin', 'type' => 'study_program', 'parent_id' => $fti->id,
    ]);

    // Pohon bersiklus membuat CTE rekursif BR-FE-02 tidak pernah berhenti, dan
    // kegagalannya mendarat sebagai timeout di layar peneliti yang tidak tahu
    // apa-apa.
    expect(fn () => SaveOrganization::run($operator, ['parent_id' => $mesin->id], $fti))
        ->toThrow(ValidationException::class)
        ->and($fti->fresh()->parent_id)->toBeNull();
});

it('[IAM-ORG-13] unit tidak bisa menjadi induk dirinya sendiri', function () {
    $operator = operatorOrganisasi();
    $unit = SaveOrganization::run($operator, ['name' => 'FTI', 'type' => 'faculty']);

    expect(fn () => SaveOrganization::run($operator, ['parent_id' => $unit->id], $unit))
        ->toThrow(ValidationException::class);
});

it('[IAM-ORG-14] operator memindahkan user antar unit', function () {
    $operator = operatorOrganisasi();
    $kimia = SaveOrganization::run($operator, ['name' => 'Teknik Kimia', 'type' => 'study_program']);
    $mesin = SaveOrganization::run($operator, ['name' => 'Teknik Mesin', 'type' => 'study_program']);

    $budi = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $budi->id, 'organization_id' => $kimia->id]);

    $pengajuan = FormSubmission::factory()->create(['submitted_by' => $budi->id]);

    MoveUserToOrganization::run($operator, $budi->fresh(), $mesin);

    expect($budi->fresh()->userProfile->organization_id)->toBe($mesin->id)
        // Perpindahan unit bukan penghapusan riwayat.
        ->and(FormSubmission::query()->whereKey($pengajuan->id)->exists())->toBeTrue();
});

it('[IAM-ORG-15] memindahkan user membuka jalan deaktivasi unit', function () {
    $operator = operatorOrganisasi();
    $kimia = SaveOrganization::run($operator, ['name' => 'Teknik Kimia', 'type' => 'study_program']);
    $mesin = SaveOrganization::run($operator, ['name' => 'Teknik Mesin', 'type' => 'study_program']);

    $budi = User::factory()->create(['is_active' => true]);
    UserProfile::factory()->create(['user_id' => $budi->id, 'organization_id' => $kimia->id]);

    // Inilah resep yang BR-IAM-14 tuliskan sendiri — dan yang sebelumnya tidak
    // punya obatnya.
    expect(fn () => DeactivateOrganization::run($kimia))->toThrow(ValidationException::class);

    MoveUserToOrganization::run($operator, $budi->fresh(), $mesin);
    DeactivateOrganization::run($kimia->fresh());

    expect($kimia->fresh()->is_active)->toBeFalse();
});

// ---------- W116: penjaga pohon melihat struktur, bukan rantai akses ----------

it('[IAM-ORG-02] profil di bawah unit nonaktif tetap menghalangi penghapusan', function () {
    $t = orgTree();
    $t['fti']->update(['is_active' => false]);
    userIn($t['prodi'], active: false);

    // Rantai akses berhenti di FTI nonaktif; struktur tidak. Profil di Informatika
    // tetap jejak yang tidak boleh hilang (BR-IAM-08).
    $this->actingAs(operatorOrganisasi())
        ->delete(route('admin.organizations.destroy', $t['itk']))
        ->assertSessionHasErrors('organization_id');

    expect(Organization::find($t['itk']->id))->not->toBeNull()
        ->and($t['fti']->fresh()->parent_id)->toBe($t['itk']->id);
});

it('[IAM-ORG-13] siklus lewat unit nonaktif ditolak', function () {
    $t = orgTree();
    $t['fti']->update(['is_active' => false]);

    expect(fn () => SaveOrganization::run(operatorOrganisasi(), ['parent_id' => $t['prodi']->id], $t['itk']))
        ->toThrow(ValidationException::class)
        ->and($t['itk']->fresh()->parent_id)->toBeNull();
});

// ---------- W117: induk nonaktif dan tipe unit dari katalog ----------

it('[IAM-ORG-16] unit nonaktif tidak bisa menjadi induk unit baru', function () {
    $operator = operatorOrganisasi();
    $lama = Organization::create(['name' => 'Teknik Kimia Lama', 'type' => 'study_program', 'is_active' => false]);
    $lain = Organization::create(['name' => 'Teknik Mesin', 'type' => 'study_program', 'is_active' => true]);

    $this->actingAs($operator)
        ->post(route('admin.organizations.store'), ['name' => 'Lab Katalis', 'type' => 'unit', 'parent_id' => $lama->id])
        ->assertSessionHasErrors(['parent_id' => 'Unit induk sudah nonaktif, jadi tidak menerima sub-unit baru.']);

    // Memindah unit yang ada ke bawahnya sama dengan menambah sub-unit baru di sana.
    $this->actingAs($operator)
        ->put(route('admin.organizations.update', $lain), ['parent_id' => $lama->id])
        ->assertSessionHasErrors('parent_id');

    expect(Organization::query()->where('name', 'Lab Katalis')->exists())->toBeFalse()
        ->and($lain->fresh()->parent_id)->toBeNull();
});

it('mengganti nama unit di bawah induk nonaktif tetap boleh', function () {
    $induk = Organization::create(['name' => 'FSTI', 'type' => 'faculty', 'is_active' => false]);
    $unit = Organization::create(['name' => 'Teknik Kimia', 'type' => 'study_program', 'parent_id' => $induk->id, 'is_active' => false]);

    $this->actingAs(operatorOrganisasi())
        ->put(route('admin.organizations.update', $unit), ['name' => 'Teknik Kimia (2019–2024)', 'parent_id' => $induk->id])
        ->assertSessionHasNoErrors();

    expect($unit->fresh()->name)->toBe('Teknik Kimia (2019–2024)')
        ->and($unit->fresh()->parent_id)->toBe($induk->id);
});

it('tipe di luar katalog ditolak, tipe lama unit itu sendiri diterima', function () {
    $operator = operatorOrganisasi();
    $pusat = Organization::create(['name' => 'Pusat Studi Energi', 'type' => 'research_center', 'is_active' => true]);

    $this->actingAs($operator)
        ->post(route('admin.organizations.store'), ['name' => 'Pusat Baru', 'type' => 'research_center'])
        ->assertSessionHasErrors('type');

    $this->actingAs($operator)
        ->put(route('admin.organizations.update', $pusat), ['name' => 'Pusat Studi Energi Terbarukan', 'type' => 'research_center'])
        ->assertSessionHasNoErrors();

    $this->actingAs($operator)
        ->put(route('admin.organizations.update', $pusat), ['type' => 'laboratory'])
        ->assertSessionHasErrors('type');

    expect(Organization::query()->where('name', 'Pusat Baru')->exists())->toBeFalse()
        ->and($pusat->fresh()->name)->toBe('Pusat Studi Energi Terbarukan')
        ->and($pusat->fresh()->type)->toBe('research_center');
});

// ---------- BR-IAM-22 (W119): mengaktifkan kembali unit ----------

function organizationReactivationAudits(Organization $unit): int
{
    return Activity::query()
        ->whereMorphedTo('subject', $unit)
        ->where('description', 'Organisasi diaktifkan kembali')
        ->count();
}

it('[IAM-ORG-17] mengaktifkan kembali unit memulihkan rantai akses Form', function () {
    $t = orgTree();
    Permission::findOrCreate('submissions.create', 'web');
    $sari = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $sari->id, 'organization_id' => $t['prodi']->id]);
    $sari->givePermissionTo('submissions.create');
    $form = Form::factory()->create();
    FormAccessControl::create(['form_id' => $form->id, 'permission' => 'submissions.create', 'organization_id' => $t['itk']->id]);
    $service = app(FormAccessService::class);

    $t['fti']->update(['is_active' => false]);
    // Rantai leluhur yang terputus kini ada di cache (30 menit).
    expect($service->canAccessForm($sari->fresh(), $form))->toBeFalse();

    ReactivateOrganization::run($t['fti']->fresh());

    expect($t['fti']->fresh()->is_active)->toBeTrue()
        ->and($service->canAccessForm($sari->fresh(), $form))->toBeTrue()
        ->and(organizationReactivationAudits($t['fti']))->toBe(1);
});

it('[IAM-ORG-18] unit di bawah induk nonaktif tidak bisa diaktifkan kembali', function () {
    $t = orgTree();
    $t['itk']->update(['is_active' => false]);
    $t['fti']->update(['is_active' => false]);

    $this->actingAs(operatorOrganisasi())
        ->post(route('admin.organizations.reactivate', $t['fti']))
        ->assertSessionHasErrors(['organization_id' => 'Induk unit ini masih nonaktif. Aktifkan induknya lebih dulu.']);

    expect($t['fti']->fresh()->is_active)->toBeFalse()
        ->and(organizationReactivationAudits($t['fti']))->toBe(0);
});

it('[IAM-ORG-19] pengaktifan hanya untuk unit itu, turunannya tetap nonaktif', function () {
    $t = orgTree();
    DeactivateOrganization::run($t['fti']);

    $this->actingAs(operatorOrganisasi())
        ->post(route('admin.organizations.reactivate', $t['fti']))
        ->assertSessionHasNoErrors();

    expect($t['fti']->fresh()->is_active)->toBeTrue()
        ->and($t['prodi']->fresh()->is_active)->toBeFalse();
});

it('[IAM-ORG-20] undangan yang masih sah berlaku lagi dan disebut sebelum konfirmasi', function () {
    $unit = Organization::create(['name' => 'Mitra Lama', 'type' => 'unit', 'is_active' => false]);
    $valid = InvitationToken::factory()->create(['organization_id' => $unit->id]);
    InvitationToken::factory()->create(['organization_id' => $unit->id, 'revoked_at' => now()]);
    InvitationToken::factory()->create(['organization_id' => $unit->id, 'expires_at' => now()->subDay()]);
    InvitationToken::factory()->create(['organization_id' => $unit->id, 'max_uses' => 2, 'used_count' => 2]);

    $this->actingAs(operatorOrganisasi())
        ->get(route('admin.organizations.index', ['unit' => $unit->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selected.revivableInvitations', 1)
            ->where('selected.canReactivate', true));

    expect($valid->fresh()->isValid())->toBeFalse()
        ->and(ReactivateOrganization::run($unit))->toBe(1)
        ->and($valid->fresh()->isValid())->toBeTrue();
});

it('mengaktifkan unit yang sudah aktif tidak mengubah apa pun', function () {
    $t = orgTree();

    expect(ReactivateOrganization::run($t['fti']))->toBe(0)
        ->and(organizationReactivationAudits($t['fti']))->toBe(0);
});
