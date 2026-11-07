<?php

// tests/Feature/ResearchOutput/ResearchOutputFormTest.php
//
// RESEARCHER-06-02 (tambah) dan 06-03 (sunting) — halaman isian luaran (W145, W146).

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Data\Request\ResearchOutputInput;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\ResearchOutput;
use App\Models\Scheme;
use App\Models\SubmissionType;
use App\Models\User;
use Database\Seeders\OutputTypeDefinitionSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(PermissionSeeder::class);
    $this->seed(OutputTypeDefinitionSeeder::class);
});

/** @return array<string, mixed> */
function formFixture(): array
{
    $fx = outputUploadFixture();
    $fx['user']->assignRole('researcher');

    return $fx;
}

/** @return array<string, string> */
function formArticle(string $title = 'Optimasi Nutrisi Hidroponik Berbasis IoT'): array
{
    return [
        'title' => $title,
        'journal_name' => 'Jurnal Teknologi Pertanian Indonesia',
        'journal_type' => 'national_accredited',
        'year' => '2026',
    ];
}

function formSavedIp(FormSubmission $submission, User $by): ResearchOutput
{
    return SaveResearchOutput::run($submission, $by, new ResearchOutputInput('ip', [
        'title' => 'Sistem Dosis Nutrisi',
        'ip_type' => 'patent',
    ], [UploadedFile::fake()->create('Sertifikat-HKI.pdf', 240, 'application/pdf')]));
}

it('kontrak halaman tambah', function () {
    $fx = formFixture();

    $this->actingAs($fx['user'])->get(route('research-outputs.create', ['submission' => $fx['submission']->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ResearchOutput/FormPage')
            ->where('submissionId', $fx['submission']->id)
            ->hasAll(['submissionTitle', 'scheme', 'access', 'metadata', 'files'])
            ->has('types.0', fn (AssertableInertia $type) => $type->hasAll(['code', 'name', 'requiresFiles', 'fields']))
            ->where('outputId', null)
            ->where('outputType', null));
});

it('submission bukan angka dijawab 404', function () {
    $fx = formFixture();

    $this->actingAs($fx['user'])->get(route('research-outputs.create', ['submission' => 'abc']))->assertNotFound();
    $this->actingAs($fx['user'])->get(route('research-outputs.create'))->assertNotFound();
});

it('[RO-OUTPUT-03] tipe PKS hanya ditawarkan untuk Pengabdian', function () {
    $fx = formFixture();
    $codes = fn (FormSubmission $s) => collect($this->actingAs($fx['user'])
        ->get(route('research-outputs.create', ['submission' => $s->id]))
        ->viewData('page')['props']['types'])->pluck('code');

    expect($codes($fx['submission']))->not->toContain('pks');

    $type = SubmissionType::firstOrCreate(['name' => 'CommunityService']);
    $fx['submission']->update(['scheme_id' => Scheme::factory()->create(['submission_type_id' => $type->id])->id]);

    expect($codes($fx['submission']->fresh()))->toContain('pks');
});

it('halaman tambah pengajuan orang lain 403', function () {
    $fx = formFixture();
    $stranger = seededRole('researcher');

    $this->actingAs($stranger)
        ->get(route('research-outputs.create', ['submission' => $fx['submission']->id]))
        ->assertForbidden();
    $this->actingAs($stranger)
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'article', 'metadata' => formArticle()])
        ->assertForbidden();

    expect(ResearchOutput::count())->toBe(0);
});

it('anggota tidak bisa membuka halaman sunting', function () {
    $fx = formFixture();
    $output = formSavedIp($fx['submission'], $fx['user']);
    $member = seededRole('researcher');
    ResearchMember::create([
        'form_submission_id' => $fx['submission']->id,
        'user_profile_id' => $member->userProfile->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    $this->actingAs($member)->get(route('research-outputs.edit', $output))->assertForbidden();
});

it('[RO-OUTPUT-14] lewat Batas Akhir halaman sunting 403', function () {
    $fx = formFixture();
    $output = formSavedIp($fx['submission'], $fx['user']);

    $this->travelTo('2026-12-10 09:00:00');

    $this->actingAs($fx['user'])->get(route('research-outputs.edit', $output))->assertForbidden();
});

it('simpan mengarah ke detail luaran', function () {
    $fx = formFixture();

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'article', 'metadata' => formArticle()])
        ->assertRedirect(route('research-outputs.show', ResearchOutput::query()->sole()))
        ->assertSessionHas('success', 'Luaran tersimpan.');

    $output = ResearchOutput::query()->sole();

    $this->actingAs($fx['user'])
        ->put(route('research-outputs.update', $output), ['output_type' => 'article', 'metadata' => formArticle('Judul Baru')])
        ->assertRedirect(route('research-outputs.show', $output))
        ->assertSessionHas('success', 'Luaran diperbarui.');

    expect($output->fresh()->metadata['title'])->toBe('Judul Baru');
});

it('kontrak halaman sunting berisi isian dan berkas lama', function () {
    $fx = formFixture();
    $output = formSavedIp($fx['submission'], $fx['user']);

    $this->actingAs($fx['user'])->get(route('research-outputs.edit', $output))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ResearchOutput/FormPage')
            ->where('outputId', $output->id)
            ->where('outputType', 'ip')
            ->where('metadata.title', 'Sistem Dosis Nutrisi')
            ->where('files.0.name', 'Sertifikat-HKI.pdf')
            ->where('files.0.size', 245760)
            ->where('files.0.url', route('research-outputs.files.show', ['output' => $output->id, 'file' => $output->files()->value('id')])));
});

it('tipe nonaktif tetap ditawarkan saat menyunting luarannya', function () {
    $fx = formFixture();
    $output = formSavedIp($fx['submission'], $fx['user']);
    OutputTypeDefinition::query()->where('code', 'ip')->update(['is_active' => false]);

    $codes = collect($this->actingAs($fx['user'])->get(route('research-outputs.edit', $output))->viewData('page')['props']['types'])->pluck('code');
    $offered = collect($this->actingAs($fx['user'])
        ->get(route('research-outputs.create', ['submission' => $fx['submission']->id]))->viewData('page')['props']['types'])->pluck('code');

    expect($codes)->toContain('ip')->and($offered)->not->toContain('ip');
});
