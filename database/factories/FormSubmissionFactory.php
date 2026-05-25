<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\Form;
use App\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */

class FormSubmissionFactory extends Factory
{

    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'submitted_by' => null,
            'status' => SubmissionStatus::DRAFT,
            'is_submitted' => false,
        ];
    }
}
