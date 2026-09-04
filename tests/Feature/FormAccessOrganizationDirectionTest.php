<?php

// Skenario [FE-ACCESS-01..06] — docs/requirement-analysis/bdd/generic/form_engine/access_control.feature
// BR-FE-02: Form terlihat oleh organisasi tempat FormAccessControl dipasang DAN semua
// unit di bawahnya. Arah penelusurannya anak-menjangkau-leluhur — lihat ADR 0013.

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\FormAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('BR-FE-02 org traversal requires PostgreSQL recursive CTE.');
    }

    Cache::flush();

    // Background: ITK membawahi FTI, FTI membawahi Informatika.
    $this->itk = Organization::create([
        'name' => 'ITK',
        'type' => 'institution',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $this->fti = Organization::create([
        'name' => 'FTI',
        'type' => 'faculty',
        'parent_id' => $this->itk->id,
        'is_active' => true,
    ]);
    $this->informatika = Organization::create([
        'name' => 'Informatika',
        'type' => 'study_program',
        'parent_id' => $this->fti->id,
        'is_active' => true,
    ]);

    // Background: Form di-scope ke FTI.
    $this->form = Form::factory()->create(['title' => 'Pengajuan Penelitian']);
    FormAccessControl::create([
        'form_id' => $this->form->id,
        'permission' => 'submissions.create',
        'organization_id' => $this->fti->id,
    ]);

    $this->researcher = function (Organization $org, bool $withPermission = true): User {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
        ]);

        if ($withPermission) {
            $role = Role::findOrCreate('researcher');
            $role->givePermissionTo(Permission::findOrCreate('submissions.create'));
            $user->assignRole($role);
        }

        return $user->fresh(['userProfile', 'organization']);
    };

    $this->canSeeForm = fn (User $user): bool => app(FormAccessService::class)
        ->canAccessForm($user, $this->form);
});

it('[FE-ACCESS-01] user dengan permission dan organisasi yang cocok bisa akses', function () {
    $budi = ($this->researcher)($this->fti);

    expect(($this->canSeeForm)($budi))->toBeTrue();
});

it('[FE-ACCESS-02] organisasi anak ikut terjangkau dari form ber-scope induk', function () {
    $sari = ($this->researcher)($this->informatika);

    expect(($this->canSeeForm)($sari))->toBeTrue();
});

it('[FE-ACCESS-03] organisasi induk tidak ikut terjangkau', function () {
    $rina = ($this->researcher)($this->itk);

    expect(($this->canSeeForm)($rina))->toBeFalse();
});

it('[FE-ACCESS-04] permission cocok tapi organisasi di luar cabang yang sama', function () {
    $fsains = Organization::create([
        'name' => 'FSAINS',
        'type' => 'faculty',
        'parent_id' => $this->itk->id,
        'is_active' => true,
    ]);

    $andi = ($this->researcher)($fsains);

    expect(($this->canSeeForm)($andi))->toBeFalse();
});

it('[FE-ACCESS-05] organisasi cocok tapi permission tidak dimiliki', function () {
    $maya = ($this->researcher)($this->fti, withPermission: false);

    expect(($this->canSeeForm)($maya))->toBeFalse();
});

it('[FE-ACCESS-06] direct permission memberi akses tanpa lewat role', function () {
    $hendra = User::factory()->create();
    UserProfile::create([
        'user_id' => $hendra->id,
        'organization_id' => $this->fti->id,
    ]);
    Permission::findOrCreate('submissions.create');
    $hendra->givePermissionTo('submissions.create');
    $hendra = $hendra->fresh(['userProfile', 'organization']);

    expect($hendra->getRoleNames())->toBeEmpty()
        ->and(($this->canSeeForm)($hendra))->toBeTrue();
});
