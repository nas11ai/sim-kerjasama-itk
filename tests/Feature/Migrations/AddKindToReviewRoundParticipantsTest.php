<?php

// tests/Feature/Migrations/AddKindToReviewRoundParticipantsTest.php
//
// BR-REV-16 — jenis kursi peserta (penilaian/pemeriksaan) disimpan per peserta. Baris yang ada
// sebelum kolomnya lahir mengikuti jenis putarannya.

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('mengisi jenis peserta lama dari jenis putarannya', function () {
    $migration = require database_path('migrations/2026_11_11_300000_add_kind_to_review_round_participants.php');
    $migration->down();

    $submission = FormSubmission::factory()->create();
    $seat = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => User::factory()->create()->id])->id,
        'evaluation_status' => 'completed',
        'status' => 'active',
    ]);
    $roundId = DB::table('review_rounds')->insertGetId([
        'form_submission_id' => $submission->id, 'number' => 1, 'kind' => 'recheck',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('review_round_participants')->insert([
        'review_round_id' => $roundId, 'submission_reviewer_id' => $seat->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    expect(Schema::hasColumn('review_round_participants', 'kind'))->toBeTrue()
        ->and(DB::table('review_round_participants')->value('kind'))->toBe('recheck');
});
