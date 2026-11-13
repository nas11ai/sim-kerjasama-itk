<?php

// ADMIN-08-03 — kontrak daftar skema, hitungan pengajuan aktif, filter, dan wewenang `schemes.manage` (W105).

use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\SubmissionType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function schemeListActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return list<int> */
function listedSchemeIds(array $query): array
{
    $ids = [];
    test()->actingAs(schemeListActor())
        ->get(route('admin.schemes.index', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['schemes']['data'], 'id');
        });
    sort($ids);

    return $ids;
}

function activeSubmissionCountOf(Scheme $scheme): int
{
    $count = -1;
    test()->actingAs(schemeListActor())
        ->get(route('admin.schemes.index', ['search' => $scheme->code]))
        ->assertInertia(function (AssertableInertia $page) use (&$count) {
            $count = $page->toArray()['props']['schemes']['data'][0]['activeSubmissionCount'];
        });

    return $count;
}

it('kontrak daftar skema', function () {
    $scheme = Scheme::factory()->create([
        'max_budget' => 50_000_000, 'max_members' => 5, 'duration_months' => 12,
    ]);

    $this->actingAs(schemeListActor())
        ->get(route('admin.schemes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Scheme/IndexPage')
            ->has('schemes.data', 1)
            ->has('schemes.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $scheme->id)
                ->where('name', $scheme->name)
                ->where('code', $scheme->code)
                ->where('submissionType', $scheme->submissionType?->name)
                ->where('schemeType', $scheme->schemeType?->name)
                ->where('maxBudget', 50_000_000)
                ->where('maxMembers', 5)
                ->where('durationMonths', 12)
                ->where('activeSubmissionCount', 0)
                ->where('isActive', true))
            ->where('filters', ['search' => '', 'type' => '', 'status' => ''])
            ->has('typeOptions', SubmissionType::count())
            ->where('statusOptions', [
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ]));
});

it('wewenang: admin 200, peneliti 403, tamu ke login', function () {
    $scheme = Scheme::factory()->create(['is_active' => true]);

    $this->actingAs(schemeListActor())->get(route('admin.schemes.index'))->assertOk();

    $researcher = schemeListActor('researcher');
    $this->actingAs($researcher)->get(route('admin.schemes.index'))->assertForbidden();
    $this->actingAs($researcher)->post(route('admin.schemes.deactivate', $scheme))->assertForbidden();
    $this->actingAs($researcher)->post(route('admin.schemes.activate', $scheme))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.schemes.index'))->assertRedirect(route('login'));
    $this->post(route('admin.schemes.activate', $scheme))->assertRedirect(route('login'));
});

it('[SCH-RULE-06] jumlah pengajuan aktif tidak menghitung yang ditolak atau ditarik', function () {
    $scheme = Scheme::factory()->create();
    foreach ([Draft::class, Rejected::class, Withdrawn::class, Withdrawn::class] as $status) {
        FormSubmission::factory()->create(['scheme_id' => $scheme->id, 'status' => $status]);
    }

    expect(activeSubmissionCountOf($scheme))->toBe(1);
});

it('[SCH-RULE-15] pengajuan disetujui ikut dihitung aktif', function () {
    $scheme = Scheme::factory()->create();
    FormSubmission::factory()->create([
        'scheme_id' => $scheme->id, 'status' => Approved::class, 'is_submitted' => true,
    ]);

    expect(activeSubmissionCountOf($scheme))->toBe(1);
});

it('filter tipe, status, dan pencarian nama atau kode tidak peka huruf', function () {
    $type = SubmissionType::factory()->create();
    $a = Scheme::factory()->create(['name' => 'Penelitian DIPA', 'code' => 'PEN-DIPA', 'submission_type_id' => $type->id]);
    $b = Scheme::factory()->create(['name' => 'Pengabdian Masyarakat', 'code' => 'PKM-DIPA', 'is_active' => false]);

    expect(listedSchemeIds([]))->toBe([$a->id, $b->id])
        ->and(listedSchemeIds(['search' => 'penelitian dipa']))->toBe([$a->id])
        ->and(listedSchemeIds(['search' => 'pkm-dipa']))->toBe([$b->id])
        ->and(listedSchemeIds(['search' => '%']))->toBe([])
        ->and(listedSchemeIds(['type' => (string) $type->id]))->toBe([$a->id])
        ->and(listedSchemeIds(['type' => 'all']))->toBe([$a->id, $b->id])
        ->and(listedSchemeIds(['type' => '99999999999']))->toBe([$a->id, $b->id])
        ->and(listedSchemeIds(['status' => 'inactive']))->toBe([$b->id])
        ->and(listedSchemeIds(['status' => 'active']))->toBe([$a->id])
        ->and(listedSchemeIds(['status' => 'nope']))->toBe([$a->id, $b->id]);
});

it('mengaktifkan kembali skema mengaudit', function () {
    $scheme = Scheme::factory()->create(['is_active' => false]);

    $this->actingAs(schemeListActor())
        ->post(route('admin.schemes.activate', $scheme))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $entry = Activity::query()->where('description', 'Skema diaktifkan')->sole();

    expect($scheme->fresh()->is_active)->toBeTrue()
        ->and($entry->subject_id)->toBe($scheme->id)
        ->and($entry->properties['old'])->toBe(['is_active' => false])
        ->and($entry->properties['new'])->toBe(['is_active' => true]);
});

it('mengaktifkan skema yang sudah aktif ditolak tanpa audit', function () {
    $scheme = Scheme::factory()->create(['is_active' => true]);

    $this->actingAs(schemeListActor())
        ->post(route('admin.schemes.activate', $scheme))
        ->assertSessionHasErrors(['scheme_id' => 'Skema ini sudah aktif.']);

    expect(Activity::query()->where('description', 'Skema diaktifkan')->count())->toBe(0);
});
