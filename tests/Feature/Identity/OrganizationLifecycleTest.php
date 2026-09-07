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
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use Illuminate\Validation\ValidationException;
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
    Permission::findOrCreate('users.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    $t = orgTree();
    userIn($t['prodi'], active: true);

    $this->actingAs($admin)
        ->post(route('admin.organizations.deactivate', $t['prodi']))
        ->assertSessionHasErrors('organization_id');

    expect($t['prodi']->fresh()->is_active)->toBeTrue();
});
