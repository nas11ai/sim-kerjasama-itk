<?php

use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('[FE-TEMPORAL-02] isRequiredFor menolak field yang lahir setelah submission', function () {

    $submission = new FormSubmission;
    $submission->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field = new FormField([
        'is_required' => true,
    ]);

    $field->created_at = Carbon::parse('2026-01-02 10:00:00');

    expect($field->isRequiredFor($submission))
        ->toBeFalse();
});

it('[FE-TEMPORAL-03] isRequiredFor menolak field yang baru jadi wajib', function () {

    $submission = new FormSubmission;
    $submission->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field = new FormField([
        'is_required' => true,
    ]);

    $field->created_at = Carbon::parse('2025-12-30 10:00:00');

    $field->required_since = Carbon::parse('2026-01-02 10:00:00');

    expect($field->isRequiredFor($submission))
        ->toBeFalse();
});

it('[FE-TEMPORAL-01] isRequiredFor mewajibkan field yang lahir sebelum submission', function () {

    $submission = new FormSubmission;
    $submission->created_at = Carbon::parse('2026-01-10 10:00:00');

    $field = new FormField([
        'is_required' => true,
    ]);

    $field->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field->required_since = Carbon::parse('2026-01-01 10:00:00');

    expect($field->isRequiredFor($submission))
        ->toBeTrue();
});

it('[FE-TEMPORAL-06] isRequiredFor menolak field opsional', function () {

    $submission = new FormSubmission;
    $submission->created_at = Carbon::parse('2026-01-10 10:00:00');

    $field = new FormField([
        'is_required' => false,
    ]);

    $field->created_at = Carbon::parse('2026-01-01 10:00:00');

    $field->required_since = null;

    expect($field->isRequiredFor($submission))
        ->toBeFalse();
});
