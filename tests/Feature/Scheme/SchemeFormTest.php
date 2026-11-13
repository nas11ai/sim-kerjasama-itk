<?php

// ADMIN-08-04 — kontrak form buat/sunting skema, aturan tambahan bertipe (W107), dan W108.

use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\User;
use App\Queries\Submission\TargetOutputTypeChoices;
use App\Support\Review\MinimumReviewers;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function schemeFormActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array<string, mixed> */
function schemeFormPayload(Scheme $scheme, array $override = []): array
{
    return array_merge([
        'name' => $scheme->name,
        'code' => $scheme->code,
        'max_budget' => $scheme->max_budget,
        'max_members' => $scheme->max_members,
        'duration_months' => $scheme->duration_months,
    ], $override);
}

function schemeOutputType(string $code, bool $active = true): OutputTypeDefinition
{
    return OutputTypeDefinition::updateOrCreate(['code' => $code], [
        'name' => strtoupper($code), 'is_active' => $active, 'requires_files' => false,
        'submission_type' => 'Research', 'fields' => [],
    ]);
}

it('kontrak form buat dan sunting', function () {
    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => 3, 'max_student_members' => 'dua', 'allowed_output_types' => ['jurnal']],
    ]);
    $admin = schemeFormActor();

    $this->actingAs($admin)->get(route('admin.schemes.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Scheme/FormPage')
            ->where('scheme', null)
            ->has('options', fn (AssertableInertia $o) => $o
                ->has('schemeTypes')->has('submissionTypes')->has('trls')->has('outputTypes')->has('integerRules')));

    $this->actingAs($admin)->get(route('admin.schemes.edit', $scheme))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Scheme/FormPage')
            ->has('scheme', fn (AssertableInertia $s) => $s
                ->where('id', $scheme->id)
                ->where('name', $scheme->name)
                ->where('code', $scheme->code)
                ->where('isActive', true)
                ->where('schemeTypeId', $scheme->scheme_type_id)
                ->where('submissionTypeId', $scheme->submission_type_id)
                ->where('maxBudget', $scheme->max_budget)
                ->where('maxMembers', $scheme->max_members)
                ->where('durationMonths', $scheme->duration_months)
                ->where('allowedTrlIds', [])
                // Nilai yang bukan bilangan bulat dibaca sebagai kosong, bukan dilempar ke layar.
                ->where('rules', [
                    'min_reviewer_count' => 3,
                    'max_reviewer_workload' => null,
                    'max_student_members' => null,
                    'allowed_output_types' => ['jurnal'],
                ]))
            ->has('options'));
});

it('wewenang: peneliti 403 di create, edit, store, update', function () {
    $scheme = Scheme::factory()->create();
    $researcher = schemeFormActor('researcher');

    $this->actingAs($researcher)->get(route('admin.schemes.create'))->assertForbidden();
    $this->actingAs($researcher)->get(route('admin.schemes.edit', $scheme))->assertForbidden();
    $this->actingAs($researcher)->post(route('admin.schemes.store'), schemeFormPayload($scheme, ['code' => 'BARU']))->assertForbidden();
    $this->actingAs($researcher)->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme))->assertForbidden();
});

it('membuat skema membuka halaman suntingnya', function () {
    $template = Scheme::factory()->make();

    $response = $this->actingAs(schemeFormActor())
        ->post(route('admin.schemes.store'), schemeFormPayload($template, ['code' => 'PEN-BARU']));

    $created = Scheme::where('code', 'PEN-BARU')->sole();
    $response->assertRedirect(route('admin.schemes.edit', $created))->assertSessionHas('success', 'Skema dibuat.');
});

it('[SCH-RULE-17] menyunting skema tanpa mengirim aturan tambahan tidak menghapus aturannya', function () {
    $scheme = Scheme::factory()->create(['rules' => ['min_reviewer_count' => 3, 'legacy_key' => 'x']]);

    $this->actingAs(schemeFormActor())
        ->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme, ['name' => 'Nama Baru']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Skema diperbarui.');

    expect($scheme->fresh()->name)->toBe('Nama Baru')
        // JSONB mengurutkan ulang kuncinya, jadi yang dibandingkan isinya, bukan urutannya.
        ->and($scheme->fresh()->rules)->toEqual(['min_reviewer_count' => 3, 'legacy_key' => 'x']);
});

it('field aturan kosong membuang kuncinya saja', function () {
    $scheme = Scheme::factory()->create(['rules' => ['min_reviewer_count' => 3, 'legacy_key' => 'x']]);

    $this->actingAs(schemeFormActor())
        ->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme, [
            'rules' => ['min_reviewer_count' => null],
        ]))
        ->assertSessionHasNoErrors();

    expect($scheme->fresh()->rules)->toBe(['legacy_key' => 'x']);
});

it('aturan angka tersimpan sebagai bilangan bulat', function () {
    $scheme = Scheme::factory()->create(['rules' => null]);

    $this->actingAs(schemeFormActor())
        ->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme, [
            'rules' => ['min_reviewer_count' => '3', 'max_reviewer_workload' => '', 'allowed_output_types' => []],
        ]))
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::factory()->create(['scheme_id' => $scheme->id]);

    expect(Scheme::find($scheme->id)->rules)->toBe(['min_reviewer_count' => 3])
        ->and(MinimumReviewers::for($submission))->toBe(3);
});

it('aturan bertipe ditolak bila bukan bilangan bulat positif', function (mixed $value) {
    $scheme = Scheme::factory()->create(['rules' => ['min_reviewer_count' => 2]]);

    $this->actingAs(schemeFormActor())
        ->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme, [
            'rules' => ['min_reviewer_count' => $value],
        ]))
        ->assertSessionHasErrors('rules.min_reviewer_count');

    expect($scheme->fresh()->rules)->toBe(['min_reviewer_count' => 2]);
})->with(['tiga', 0, -1]);

it('kunci aturan di luar katalog ditolak', function () {
    $scheme = Scheme::factory()->create(['rules' => null]);

    $this->actingAs(schemeFormActor())
        ->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme, [
            'rules' => ['kunci_bebas' => 'apa saja'],
        ]))
        ->assertSessionHasErrors('rules');

    expect($scheme->fresh()->rules)->toBeNull();
});

it('[SC-CONFIG-05] tipe skema nonaktif tidak ditawarkan di skema baru, tetap tampil di skema yang memakainya', function () {
    $retired = SchemeType::factory()->create(['name' => 'Hibah Lama', 'is_active' => false]);
    $scheme = Scheme::factory()->create(['scheme_type_id' => $retired->id]);
    $admin = schemeFormActor();

    $values = function (string $url) use ($admin): array {
        $options = [];
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(function (AssertableInertia $page) use (&$options) {
            $options = $page->toArray()['props']['options']['schemeTypes'];
        });

        return array_column($options, 'value');
    };

    expect($values(route('admin.schemes.create')))->not->toContain((string) $retired->id)
        ->and($values(route('admin.schemes.edit', $scheme)))->toContain((string) $retired->id);
});

it('tipe luaran yang diizinkan tersimpan dan dibaca TargetOutputTypeChoices', function () {
    schemeOutputType('jurnal');
    schemeOutputType('hki');
    schemeOutputType('arsip', active: false);
    $scheme = Scheme::factory()->create(['rules' => null]);

    $this->actingAs(schemeFormActor())
        ->put(route('admin.schemes.update', $scheme), schemeFormPayload($scheme, [
            'rules' => ['allowed_output_types' => ['jurnal']],
        ]))
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::factory()->create(['scheme_id' => $scheme->id]);

    expect($scheme->fresh()->rules)->toBe(['allowed_output_types' => ['jurnal']])
        ->and(array_column(TargetOutputTypeChoices::run($submission), 'code'))->toBe(['jurnal']);

    $offered = [];
    $this->actingAs(schemeFormActor())->get(route('admin.schemes.edit', $scheme))
        ->assertInertia(function (AssertableInertia $page) use (&$offered) {
            $offered = $page->toArray()['props']['options']['outputTypes'];
        });
    expect(array_column($offered, 'value'))->toContain('jurnal', 'hki')->not->toContain('arsip');
});
