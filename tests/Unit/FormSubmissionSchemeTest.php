<?php

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    if (Schema::hasTable('schemes')) {
        return;
    }

    Schema::create('schemes', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('duration_months')->nullable();
        $table->timestamps();
    });
});

it('resolves the scheme from the scheme selector field response', function () {

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
        'code' => 'RG-001',
        'max_budget' => 100_000_000,
        'max_members' => 5,
        'duration_months' => 12,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $formType = FormType::create([
        'name' => 'Proposal',
        'description' => 'Proposal form',
    ]);

    $form = Form::create([
        'title' => 'Research Proposal',
        'description' => null,
        'form_type_id' => $formType->id,
    ]);

    $fieldType = FieldType::create(['name' => 'scheme_selector']);

    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Scheme',
        'is_required' => true,
        'field_type_id' => $fieldType->id,
        'order' => 1,
    ]);

    $user = User::factory()->create();

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
    ]);

    FormFieldResponse::create([
        'form_submission_id' => $submission->id,
        'form_field_id' => $field->id,
        'value' => (string) $schemeId,
    ]);

    expect($submission->scheme())
        ->not->toBeNull()
        ->id->toBe($schemeId);
});

it('returns null when no scheme selector response exists', function () {
    $formType = FormType::create([
        'name' => 'Proposal',
        'description' => 'Proposal form',
    ]);

    $form = Form::create([
        'title' => 'Research Proposal',
        'description' => null,
        'form_type_id' => $formType->id,
    ]);

    $user = User::factory()->create();

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'submitted_by' => $user->id,
    ]);

    expect($submission->scheme())->toBeNull();
});
