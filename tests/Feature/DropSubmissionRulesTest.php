<?php

use App\Models\SubmissionPeriod;
use Illuminate\Support\Facades\Schema;

it('drops the submission_rules and submission_period_details tables', function () {
    expect(Schema::hasTable('submission_rules'))->toBeFalse()
        ->and(Schema::hasTable('submission_period_details'))->toBeFalse();
});

it('keeps the rules jsonb column on schemes', function () {
    expect(Schema::hasColumn('schemes', 'rules'))->toBeTrue();
});

it('no longer ships the obsolete rule models', function () {
    expect(file_exists(app_path('Models/SubmissionRule.php')))->toBeFalse()
        ->and(file_exists(app_path('Models/SubmissionPeriodDetail.php')))->toBeFalse();
});

it('creates a submission period without any rule associations', function () {
    $period = SubmissionPeriod::create(['name' => 'Periode 2026']);

    expect($period->exists)->toBeTrue()
        ->and(SubmissionPeriod::find($period->id)?->name)->toBe('Periode 2026');
});
