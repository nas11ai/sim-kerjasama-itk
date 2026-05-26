<?php

use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

it('returns false if field was created after submission', function () {

    $submission = new FormSubmission();
    $submission->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field = new FormField([
        'is_required' => true,
    ]);

    $field->created_at = Carbon::parse('2026-01-02 10:00:00');

    expect($field->isRequiredFor($submission))
        ->toBeFalse();
});

it('returns false if field became required after submission', function () {

    $submission = new FormSubmission();
    $submission->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field = new FormField([
        'is_required' => true,
    ]);

    $field->created_at = Carbon::parse('2025-12-30 10:00:00');

    $field->required_since = Carbon::parse('2026-01-02 10:00:00');

    expect($field->isRequiredFor($submission))
        ->toBeFalse();
});

it('returns true if field is required for submission', function () {

    $submission = new FormSubmission();
    $submission->created_at = Carbon::parse('2026-01-10 10:00:00');

    $field = new FormField([
        'is_required' => true,
    ]);

    $field->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field->required_since = Carbon::parse('2026-01-01 10:00:00');

    expect($field->isRequiredFor($submission))
        ->toBeTrue();
});

it('returns false if field is not required', function () {

    $submission = new FormSubmission();
    $submission->created_at = Carbon::parse('2026-01-10 10:00:00');

    $field = new FormField([
        'is_required' => false,
    ]);

    $field->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field->required_since = null;

    expect($field->isRequiredFor($submission))
        ->toBeFalse();
});
