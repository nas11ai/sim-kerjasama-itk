<?php

// tests/Feature/ResearchOutput/ResearchOutputIndexTest.php
//
// RESEARCHER-06-01 — Daftar Luaran peneliti (W142) dan satu definisi "boleh menulis" (W146).

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Database\Seeders\OutputTypeDefinitionSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(PermissionSeeder::class);
    $this->seed(OutputTypeDefinitionSeeder::class);
});

/** @return array<string, mixed> */
function indexFixture(): array
{
    $fx = outputUploadFixture();
    $fx['user']->assignRole('researcher');

    return $fx;
}

function indexTeamMember(FormSubmission $submission, string $role): User
{
    $user = seededRole('researcher');
    ResearchMember::create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => $user->userProfile->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return $user;
}

function indexArticle(FormSubmission $submission, User $by, int $files = 0): void
{
    SaveResearchOutput::run($submission, $by, outputInput('article', [
        'title' => 'Optimasi Nutrisi Hidroponik Berbasis IoT',
        'journal_name' => 'Nama Jurnal X',
        'journal_type' => 'national_accredited',
        'year' => '2026',
    ], $files));
}

function indexSibling(array $fx, string $status): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => $status,
        'is_submitted' => true,
    ]);
}

it('kontrak daftar luaran', function () {
    $fx = indexFixture();
    indexArticle($fx['submission'], $fx['user']);

    $this->actingAs($fx['user'])->get(route('research-outputs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ResearchOutput/IndexPage')
            ->has('groups', 1)
            ->has('groups.0', fn (AssertableInertia $group) => $group
                ->where('submissionId', $fx['submission']->id)
                ->hasAll(['title', 'scheme', 'status'])
                ->has('access', fn (AssertableInertia $access) => $access
                    ->where('canWrite', true)
                    ->where('blockedReason', null)
                    ->where('deadline', fn (string $deadline) => str_starts_with($deadline, '2026-12-01T23:59:00'))
                    ->where('overrideUntil', null))
                ->has('outputs', 1)
                ->has('outputs.0', fn (AssertableInertia $row) => $row
                    ->hasAll(['id', 'typeName', 'headline', 'meta', 'fileCount']))));
});

it('memuat pengajuan yang diketuai dan diikuti, APPROVED atau ditarik berluaran', function () {
    $fx = indexFixture();
    indexSibling($fx, Draft::class);
    indexSibling($fx, Rejected::class);
    indexSibling($fx, Withdrawn::class);
    $joined = outputUploadFixture()['submission'];
    $member = indexTeamMember($joined, ResearchMember::ROLE_CO_INVESTIGATOR);

    $this->actingAs($fx['user'])->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('groups', 1)
            ->where('groups.0.submissionId', $fx['submission']->id));

    $this->actingAs($member)->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('groups', 1)
            ->where('groups.0.submissionId', $joined->id));
});

it('[RO-OUTPUT-10] luaran tetap terbaca setelah penarikan', function () {
    $fx = indexFixture();
    indexArticle($fx['submission'], $fx['user']);
    $fx['submission']->status->transitionTo(Withdrawn::class);

    $this->actingAs($fx['user'])->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('groups', 1)
            ->where('groups.0.status.key', 'withdrawn')
            ->where('groups.0.access.canWrite', false)
            ->where('groups.0.access.blockedReason', 'Pengajuan ini sudah ditarik. Luarannya tetap tersimpan sebagai arsip baca-saja.')
            ->has('groups.0.outputs', 1));
});

it('[RO-OUTPUT-13] anggota membaca tanpa tombol', function () {
    $fx = indexFixture();
    $member = indexTeamMember($fx['submission'], 'member');

    $this->actingAs($member)->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('groups.0.access.canWrite', false)
            ->where('groups.0.access.blockedReason', 'Anda terdaftar sebagai anggota. Hanya ketua dan co-investigator yang dapat mengubah luaran.'));
});

it('[RO-OUTPUT-15] lewat batas: luaran terbaca, tombol terkunci', function () {
    $fx = indexFixture();
    indexArticle($fx['submission'], $fx['user']);

    $this->travelTo('2026-12-10 09:00:00');

    $this->actingAs($fx['user'])->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('groups.0.access.canWrite', false)
            ->where('groups.0.access.blockedReason', 'Batas unggah luaran sudah lewat. Luaran yang sudah ada tetap dapat dibaca. Hubungi Operator LPPM untuk meminta perpanjangan.')
            ->has('groups.0.outputs', 1));
});

it('judul dan meta baris dari spesifikasi', function () {
    $fx = indexFixture();
    indexArticle($fx['submission'], $fx['user']);
    indexArticle($fx['submission'], $fx['user'], files: 2);

    $this->actingAs($fx['user'])->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('groups.0.outputs.0.typeName', 'Artikel Jurnal')
            ->where('groups.0.outputs.0.headline', 'Optimasi Nutrisi Hidroponik Berbasis IoT')
            ->where('groups.0.outputs.0.meta', 'Nama Jurnal X · Nasional Terakreditasi · 2026')
            ->where('groups.0.outputs.0.fileCount', 0)
            ->where('groups.0.outputs.1.meta', 'Nama Jurnal X · Nasional Terakreditasi · 2026 · 2 file lampiran')
            ->where('groups.0.outputs.1.fileCount', 2));
});

it('flag sidebar mengikuti daftar', function () {
    $fx = indexFixture();
    $co = indexTeamMember($fx['submission'], ResearchMember::ROLE_CO_INVESTIGATOR);
    $stranger = seededRole('researcher');

    $this->actingAs($co)->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('navigation.hasOutputSubmission', true));

    $this->actingAs($stranger)->get(route('research-outputs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('navigation.hasOutputSubmission', false)
            ->has('groups', 0));
});
