<?php

// tests/Feature/Migrations/AddRelationsToFormSubmissionsTest.php

use App\Models\FormSubmission;
use Illuminate\Support\Facades\Schema;

// tests/Pest.php sudah menerapkan TestCase + RefreshDatabase ke seluruh
// folder Feature, jadi tidak perlu memanggil uses() lagi di sini.

it('adds relation columns to form_submissions', function () {
    expect(Schema::hasColumn('form_submissions', 'form_phase_detail_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'submission_period_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'scheme_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'trl_id'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'last_completed_step'))->toBeTrue();
});

it('defaults last_completed_step to zero', function () {
    $submission = FormSubmission::factory()->create();

    expect($submission->fresh()->last_completed_step)->toBe(0);
});
