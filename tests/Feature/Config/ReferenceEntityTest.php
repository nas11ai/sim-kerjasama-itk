<?php

// tests/Feature/Config/ReferenceEntityTest.php
//
// BR-SC-01, BR-SC-02, BR-SC-03 — data acuan.
//
// Mengganti nama berarti "ini hal yang sama dengan sebutan yang diperbaiki":
// tautannya utuh dan laporan lama ikut memakai nama baru. Yang berubah makna
// ditempuh lewat jalan lain — buat baru, nonaktifkan yang lama — dan itulah
// sebabnya nama tidak perlu dibekukan di mana pun (register #38).

use App\Actions\Config\DeleteReferenceEntity;
use App\Actions\Config\SaveReferenceEntity;
use App\Actions\Config\ToggleReferenceEntity;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionType;
use App\Models\User;
use App\Support\Config\ReferenceEntity;
use App\Support\Config\ReferenceEntityRegistry;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function configAdmin(): User
{
    Permission::findOrCreate('users.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('users.manage');

    return $admin;
}

function schemeTypeEntity(): ReferenceEntity
{
    return ReferenceEntityRegistry::get('scheme-types');
}

it('[SC-CONFIG-01] entity acuan yang masih dipakai tidak bisa dihapus', function () {
    $type = SchemeType::factory()->create(['name' => 'Penelitian Dasar']);
    Scheme::factory()->create(['scheme_type_id' => $type->id]);

    expect(fn () => DeleteReferenceEntity::run(schemeTypeEntity(), $type))
        ->toThrow(ValidationException::class);

    expect(SchemeType::query()->whereKey($type->id)->exists())->toBeTrue();
});

it('[SC-CONFIG-01] penolakannya menyebut berapa yang memakainya', function () {
    // Angka itulah yang memberi tahu Admin apakah ia salah memilih baris atau
    // memang harus menonaktifkan alih-alih menghapus.
    $type = SchemeType::factory()->create(['name' => 'Penelitian Dasar']);
    Scheme::factory()->count(3)->create(['scheme_type_id' => $type->id]);

    try {
        DeleteReferenceEntity::run(schemeTypeEntity(), $type);
        $this->fail('seharusnya ditolak');
    } catch (ValidationException $e) {
        expect($e->errors()['id'][0])->toContain('3');
    }
});

it('[SC-CONFIG-02] entity acuan tanpa relasi aktif bisa dihapus', function () {
    $type = SchemeType::factory()->create(['name' => 'Penelitian Terapan']);

    DeleteReferenceEntity::run(schemeTypeEntity(), $type);

    expect(SchemeType::query()->whereKey($type->id)->exists())->toBeFalse();
});

it('[SC-CONFIG-03] SubmissionType tidak bisa diubah lewat antarmuka', function () {
    $entity = ReferenceEntityRegistry::get('submission-types');

    expect(fn () => SaveReferenceEntity::run($entity, ['name' => 'Pengabdian Internal']))
        ->toThrow(ValidationException::class);

    expect(SubmissionType::query()->where('name', 'Pengabdian Internal')->exists())->toBeFalse();
});

it('[SC-CONFIG-03] jalur HTTP juga menolak penambahan SubmissionType', function () {
    $this->actingAs(configAdmin())
        ->post(route('admin.reference.store', 'submission-types'), ['name' => 'Pengabdian Internal'])
        ->assertSessionHasErrors('name');
});

it('[SC-CONFIG-04] mengganti nama tidak memutus tautan historis', function () {
    $type = SchemeType::factory()->create(['name' => 'Penelitian Dasar']);
    $scheme = Scheme::factory()->create(['scheme_type_id' => $type->id]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
    ]);

    SaveReferenceEntity::run(schemeTypeEntity(), ['name' => 'Riset Dasar'], $type);

    $submission->refresh()->load('scheme.schemeType');

    expect($submission->scheme?->scheme_type_id)->toBe($type->id)
        ->and($submission->scheme?->schemeType?->name)->toBe('Riset Dasar');
});

it('[SC-CONFIG-05] kategori yang berubah makna dibuat baru, bukan diganti nama', function () {
    $lama = SchemeType::factory()->create(['name' => 'Penelitian Dasar', 'is_active' => true]);
    $scheme = Scheme::factory()->create(['scheme_type_id' => $lama->id]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => User::factory()->create()->id,
        'scheme_id' => $scheme->id,
    ]);

    SaveReferenceEntity::run(schemeTypeEntity(), ['name' => 'Riset Terapan']);
    ToggleReferenceEntity::run(schemeTypeEntity(), $lama, false);

    $submission->refresh()->load('scheme.schemeType');

    expect($submission->scheme?->schemeType?->name)->toBe('Penelitian Dasar')
        ->and($lama->refresh()->is_active)->toBeFalse()
        ->and(SchemeType::query()->where('name', 'Riset Terapan')->where('is_active', true)->exists())
        ->toBeTrue();
});

it('menampilkan jumlah pemakai sebelum tombol Hapus ditekan', function () {
    $type = SchemeType::factory()->create(['name' => 'Penelitian Dasar']);
    Scheme::factory()->count(2)->create(['scheme_type_id' => $type->id]);

    $this->actingAs(configAdmin())
        ->get(route('admin.reference.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Config/ReferenceEntitiesPage')
            ->where('entities.0.key', 'scheme-types')
            ->where('entities.0.records.0.usage_count', 2)
            ->where('entities.1.editable', false)
            ->etc());
});

it('menolak pengelolaan konfigurasi tanpa permission users.manage', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.reference.index'))
        ->assertForbidden();
});
