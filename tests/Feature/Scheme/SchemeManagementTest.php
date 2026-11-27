<?php

// tests/Feature/Scheme/SchemeManagementTest.php
//
// BR-SCH-01, BR-SCH-02, BR-SCH-03 — pengelolaan skema.
//
// Sebelum irisan ini skema hanya punya tabel dan model: nol rute, nol
// controller, nol layar. M1 sudah memakai `max_budget` untuk menolak anggaran,
// tapi satu-satunya cara membuat skemanya adalah seeder.

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Actions\Scheme\DeactivateScheme;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionType;
use App\Models\TechnologyReadinessLevel;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function schemeAdmin(): User
{
    Permission::findOrCreate('schemes.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('schemes.manage');

    return $admin;
}

/**
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function schemePayload(array $override = []): array
{
    return array_merge([
        'name' => 'Penelitian DIPA '.fake()->unique()->numerify('####'),
        'code' => 'DIPA-'.fake()->unique()->numerify('####'),
        'max_budget' => 50_000_000,
        'max_members' => 5,
        'duration_months' => 12,
    ], $override);
}

it('[SCH-RULE-01] kode skema harus unik', function () {
    $admin = schemeAdmin();
    Scheme::factory()->create(['code' => 'DIPA-2026']);

    $this->actingAs($admin)
        ->post(route('admin.schemes.store'), schemePayload(['code' => 'DIPA-2026']))
        ->assertSessionHasErrors('code');

    expect(Scheme::where('code', 'DIPA-2026')->count())->toBe(1);
});

it('[SCH-RULE-02] max_budget dan max_members harus lebih dari nol', function (int $budget, int $members, string $field) {
    $admin = schemeAdmin();

    $this->actingAs($admin)
        ->post(route('admin.schemes.store'), schemePayload([
            'max_budget' => $budget,
            'max_members' => $members,
        ]))
        ->assertSessionHasErrors($field);

    expect(Scheme::count())->toBe(0);
})->with([
    [0, 5, 'max_budget'],
    [-1, 5, 'max_budget'],
    [100000, 0, 'max_members'],
    [100000, -2, 'max_members'],
]);

it('[SCH-RULE-03] skema dengan nilai sah tersimpan dan aktif', function () {
    $admin = schemeAdmin();

    $this->actingAs($admin)
        ->post(route('admin.schemes.store'), schemePayload([
            'max_budget' => 50_000_000,
            'max_members' => 5,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $scheme = Scheme::sole();

    expect($scheme->max_budget)->toBe(50_000_000)
        ->and($scheme->max_members)->toBe(5)
        ->and($scheme->is_active)->toBeTrue();
});

it('[SCH-RULE-04] skema yang dipakai pengajuan aktif tidak bisa dinonaktifkan', function () {
    $scheme = Scheme::factory()->create(['is_active' => true]);

    FormSubmission::factory()->create([
        'scheme_id' => $scheme->id,
        'status' => UnderReview::class,
        'is_submitted' => true,
    ]);

    expect(fn () => DeactivateScheme::run($scheme))->toThrow(ValidationException::class)
        ->and($scheme->fresh()->is_active)->toBeTrue();
});

it('[SCH-RULE-05] skema tanpa pengajuan aktif bisa dinonaktifkan', function () {
    $scheme = Scheme::factory()->create(['is_active' => true]);

    DeactivateScheme::run($scheme);

    expect($scheme->fresh()->is_active)->toBeFalse();
});

it('[SCH-RULE-06] pengajuan yang sudah mati tidak menghalangi penonaktifan', function () {
    $scheme = Scheme::factory()->create(['is_active' => true]);

    foreach ([Rejected::class, Withdrawn::class] as $status) {
        FormSubmission::factory()->create([
            'scheme_id' => $scheme->id,
            'status' => $status,
            'is_submitted' => true,
        ]);
    }

    DeactivateScheme::run($scheme);

    expect($scheme->fresh()->is_active)->toBeFalse();
});

it('[SCH-RULE-15] pengajuan yang disetujui masih menghalangi penonaktifan', function () {
    $scheme = Scheme::factory()->create(['is_active' => true]);

    FormSubmission::factory()->create([
        'scheme_id' => $scheme->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    // APPROVED bukan "selesai": laporan monevnya lahir dari sana, dan skemanya
    // masih memasok max_budget serta duration_months untuk memvalidasinya.
    expect(fn () => DeactivateScheme::run($scheme))->toThrow(ValidationException::class)
        ->and($scheme->fresh()->is_active)->toBeTrue();
});

it('[SCH-RULE-04] jalur HTTP menolak penonaktifan dengan pesan pada field scheme_id', function () {
    $admin = schemeAdmin();
    $scheme = Scheme::factory()->create(['is_active' => true]);

    FormSubmission::factory()->create([
        'scheme_id' => $scheme->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.schemes.deactivate', $scheme))
        ->assertSessionHasErrors('scheme_id');

    expect($scheme->fresh()->is_active)->toBeTrue();
});

it('menolak pengelolaan skema tanpa permission schemes.manage', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.schemes.store'), schemePayload())
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Tiga sumbu yang controller-nya sudah terima sejak awal tapi layarnya tidak
// pernah kirim: jenis skema, jenis pengajuan, dan TRL yang diizinkan. Skema
// tanpa `submission_type_id` membuat BR-RO-02 mustahil terpenuhi — setiap
// luaran PKS ditolak, dan tidak ada layar yang bisa memperbaikinya.

it('menyimpan jenis skema dan jenis pengajuan dari layar', function () {
    $admin = schemeAdmin();
    $submissionType = SubmissionType::firstOrCreate(['name' => 'CommunityService']);
    $schemeType = SchemeType::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.schemes.store'), schemePayload([
            'code' => 'PKM-2026',
            'submission_type_id' => $submissionType->id,
            'scheme_type_id' => $schemeType->id,
        ]))
        ->assertSessionHasNoErrors();

    $scheme = Scheme::where('code', 'PKM-2026')->sole();

    expect($scheme->submission_type_id)->toBe($submissionType->id)
        ->and($scheme->scheme_type_id)->toBe($schemeType->id);
});

it('menyimpan dan mengganti daftar TRL yang diizinkan', function () {
    $admin = schemeAdmin();
    $trls = collect(range(1, 3))->map(fn (int $level) => TechnologyReadinessLevel::create([
        'name' => "TRL {$level}",
        'level' => $level,
    ]));

    $this->actingAs($admin)
        ->post(route('admin.schemes.store'), schemePayload([
            'code' => 'TRL-2026',
            'allowed_trl_ids' => [$trls[0]->id, $trls[1]->id],
        ]))
        ->assertSessionHasNoErrors();

    $scheme = Scheme::where('code', 'TRL-2026')->sole();

    expect($scheme->allowedTrls->pluck('id')->sort()->values()->all())
        ->toBe([$trls[0]->id, $trls[1]->id]);

    $this->actingAs($admin)
        ->put(route('admin.schemes.update', $scheme->id), schemePayload([
            'code' => 'TRL-2026',
            'allowed_trl_ids' => [$trls[2]->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($scheme->refresh()->allowedTrls->pluck('id')->all())->toBe([$trls[2]->id]);
});

it('skema yang dibuat lewat layar memenuhi BR-RO-02 untuk luaran PKS', function () {
    $admin = schemeAdmin();
    $communityService = SubmissionType::firstOrCreate(['name' => 'Pengabdian kepada Masyarakat']);

    OutputTypeDefinition::updateOrCreate(['code' => 'pks'], [
        'name' => 'PKS',
        'is_active' => true,
        'requires_files' => false,
        'submission_type' => 'Pengabdian kepada Masyarakat',
        'fields' => [],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.schemes.store'), schemePayload([
            'code' => 'PKM-RO-02',
            'submission_type_id' => $communityService->id,
        ]))
        ->assertSessionHasNoErrors();

    $owner = verifiedResearcher();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => Scheme::where('code', 'PKM-RO-02')->sole()->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $output = SaveResearchOutput::run($submission, $owner, outputInput('pks'));

    expect($output->output_type)->toBe('pks');
});
