<?php

// tests/Feature/ResearchOutput/ResearchOutputFileTest.php
//
// W141 — berkas bukti luaran tinggal di disk privat; satu-satunya jalan baca adalah route unduh
// yang dijaga akses baca pengajuannya (BR-SM-07), seperti berkas jawaban (fe-v2-design W15).

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\ResearchOutput;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 09:00:00');

    $this->fx = outputUploadFixture();
    OutputTypeDefinition::create(['code' => 'ip', 'name' => 'HKI', 'is_active' => true, 'requires_files' => true, 'fields' => []]);
    $this->output = SaveResearchOutput::run($this->fx['submission'], $this->fx['user'], outputInput('ip', files: 1));
    $this->file = $this->output->files()->sole();
});

function outputFileUrl(ResearchOutput $output, int $fileId): string
{
    return route('research-outputs.files.show', [$output, $fileId]);
}

it('[RO-OUTPUT-28] berkas bukti hanya terunduh oleh yang boleh membaca pengajuannya', function () {
    $member = verifiedResearcher();
    ResearchMember::create([
        'form_submission_id' => $this->fx['submission']->id,
        'user_profile_id' => $member->userProfile->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    $reviewerUser = verifiedResearcher();
    SubmissionReviewer::create([
        'form_submission_id' => $this->fx['submission']->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $reviewerUser->id])->id,
        'status' => 'active',
    ]);

    $url = outputFileUrl($this->output, $this->file->id);

    $this->actingAs($this->fx['user'])->get($url)->assertOk()->assertDownload('bukti-1.pdf');
    $this->actingAs($member)->get($url)->assertOk();
    $this->actingAs($reviewerUser)->get($url)->assertOk();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});

it('berkas luaran lain dijawab 404 sesudah otorisasi', function () {
    $other = SaveResearchOutput::run($this->fx['submission'], $this->fx['user'], outputInput('ip', files: 1));

    $this->actingAs($this->fx['user'])
        ->get(outputFileUrl($this->output, $other->files()->sole()->id))
        ->assertNotFound();

    // Orang asing tidak bisa memakai 404 untuk menebak berkas yang ada.
    $this->actingAs(User::factory()->create())
        ->get(outputFileUrl($this->output, 999999))
        ->assertForbidden();
});

it('berkas yang hilang dari disk dijawab 404', function () {
    Storage::disk('local')->delete($this->file->file_path);

    $this->actingAs($this->fx['user'])
        ->get(outputFileUrl($this->output, $this->file->id))
        ->assertNotFound();
});
