<?php

use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

it('carries min_reviewer_count from submission_rules into schemes.rules on migrate up', function () {
    // Rebuild the pre-migration state: the legacy submission_rules table holding
    // the single global min_reviewer_count value.
    Schema::create('submission_rules', function (Blueprint $table) {
        $table->id();
        $table->text('label');
        $table->integer('value');
        $table->timestamps();
    });

    DB::table('submission_rules')->insert([
        'label' => 'min_reviewer_count',
        'value' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $schemeTypeId = DB::table('scheme_types')->insertGetId([
        'name' => 'Research Grant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $submissionTypeId = DB::table('submission_types')->insertGetId([
        'name' => 'Proposal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $schemeId = DB::table('schemes')->insertGetId([
        'scheme_type_id' => $schemeTypeId,
        'submission_type_id' => $submissionTypeId,
        'name' => 'Research Grant',
        'code' => 'RG-CARRY',
        'max_budget' => 100_000_000,
        'max_members' => 5,
        'duration_months' => 12,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Run the migration under test in isolation.
    $migration = require database_path('migrations/2026_06_26_100000_drop_submission_rules_migrate_to_scheme_rules.php');
    $migration->up();

    $scheme = Scheme::findOrFail($schemeId);

    expect($scheme->getRule('min_reviewer_count'))->toBe(3)
        ->and($scheme->minReviewerCount())->toBe(3)
        ->and(Schema::hasTable('submission_rules'))->toBeFalse();
});
