<?php

// tests/Feature/ResearchOutput/ResearchOutputShowTest.php
//
// RESEARCHER-06-03 — Detail Luaran (W143). Membaca dijaga BR-SM-07, menulis oleh W146.

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SubmissionReviewer;
use App\Models\SubmissionType;
use App\Models\User;
use Database\Seeders\OutputTypeDefinitionSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(PermissionSeeder::class);
    $this->seed(OutputTypeDefinitionSeeder::class);
});

/** @return array{fx: array<string, mixed>, output: ResearchOutput} */
function showFixture(int $files = 0): array
{
    $fx = outputUploadFixture();
    $fx['user']->assignRole('researcher');

    $output = SaveResearchOutput::run($fx['submission'], $fx['user'], outputInput('article', [
        'title' => 'Optimasi Nutrisi Hidroponik Berbasis IoT',
        'journal_name' => 'Jurnal Teknologi Pertanian Indonesia',
        'journal_type' => 'national_accredited',
        'year' => '2026',
    ], $files));

    return ['fx' => $fx, 'output' => $output];
}

function showReviewer(FormSubmission $submission): User
{
    $user = seededRole('researcher');
    $user->assignRole('reviewer_internal');
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $user->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    return $user;
}

it('kontrak detail luaran', function () {
    ['fx' => $fx, 'output' => $output] = showFixture(files: 1);

    $this->actingAs($fx['user'])->get(route('research-outputs.show', $output))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ResearchOutput/ShowPage')
            ->has('output', fn (AssertableInertia $detail) => $detail
                ->where('id', $output->id)
                ->where('submissionId', $fx['submission']->id)
                ->where('typeName', 'Artikel Jurnal')
                ->where('headline', 'Optimasi Nutrisi Hidroponik Berbasis IoT')
                ->where('fields', [
                    ['label' => 'Tipe', 'value' => 'Artikel Jurnal'],
                    ['label' => 'Nama Jurnal', 'value' => 'Jurnal Teknologi Pertanian Indonesia'],
                    ['label' => 'Tipe Jurnal', 'value' => 'Nasional Terakreditasi'],
                    ['label' => 'Tahun Terbit', 'value' => '2026'],
                    ['label' => 'DOI', 'value' => '-'],
                    ['label' => 'URL', 'value' => '-'],
                ])
                ->has('files', 1)
                ->where('files.0.name', 'bukti-1.pdf')
                ->where('access.canWrite', true)
                ->hasAll(['submissionTitle'])));
});

it('[RO-OUTPUT-19] reviewer membaca detail tanpa tombol Edit', function () {
    ['fx' => $fx, 'output' => $output] = showFixture();
    $reviewer = showReviewer($fx['submission']);

    $this->actingAs($reviewer)->get(route('research-outputs.show', $output))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('output.access.canWrite', false)
            ->where('output.access.blockedReason', 'Reviewer dapat membaca luaran, tapi tidak mengubahnya.'));
});

it('orang asing 403', function () {
    ['output' => $output] = showFixture();

    $this->actingAs(seededRole('researcher'))->get(route('research-outputs.show', $output))->assertForbidden();
});

it('[RO-OUTPUT-05] PKS berjalan tampil "Masih berjalan"', function () {
    $fx = outputUploadFixture();
    $type = SubmissionType::firstOrCreate(['name' => 'CommunityService']);
    $fx['submission']->update(['scheme_id' => Scheme::factory()->create(['submission_type_id' => $type->id])->id]);
    $output = SaveResearchOutput::run($fx['submission']->fresh(), $fx['user'], outputInput('pks', [
        'title' => 'Kerja sama pendampingan UMKM',
        'involved_parties' => 'Dinas Koperasi Balikpapan',
        'start_date' => '2026-02-01',
    ]));

    $this->actingAs($fx['user'])->get(route('research-outputs.show', $output))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('output.fields.2', ['label' => 'Tanggal Mulai', 'value' => '01 Feb 2026'])
            ->where('output.fields.3', ['label' => 'Tanggal Berakhir', 'value' => 'Masih berjalan']));
});

it('kunci metadata tanpa spesifikasi tampil dengan kuncinya', function () {
    ['fx' => $fx, 'output' => $output] = showFixture();
    // Baris sebelum W140: kunci di luar spesifikasi tertinggal di JSONB.
    $output->forceFill(['metadata' => [...$output->metadata, 'sinta' => 'Sinta 2']])->saveQuietly();

    $this->actingAs($fx['user'])->get(route('research-outputs.show', $output))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('output.fields.6', ['label' => 'sinta', 'value' => 'Sinta 2']));
});

it('[RO-OUTPUT-23] tidak ada jalur hapus', function () {
    ['fx' => $fx, 'output' => $output] = showFixture();

    expect(Route::has('research-outputs.destroy'))->toBeFalse();

    $this->actingAs($fx['user'])->delete('/research-outputs/'.$output->id)->assertMethodNotAllowed();

    expect(ResearchOutput::find($output->id))->not->toBeNull();
});
