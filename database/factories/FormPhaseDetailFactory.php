<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormPhaseDetail>
 */
class FormPhaseDetailFactory extends Factory
{
    protected $model = FormPhaseDetail::class;

    public function definition(): array
    {
        $organization = Organization::create([
            'name' => 'Organisasi Uji Factory',
            'type' => 'faculty',
        ]);

        $formAccessControl = FormAccessControl::create([
            'form_id' => Form::factory()->create()->id,
            'permission' => 'submissions.create',
            'organization_id' => $organization->id,
        ]);

        $phaseType = PhaseType::create([
            'name' => 'Tipe Fase '.fake()->unique()->numerify('####-####'),
        ]);

        $formPhase = FormPhase::create([
            'title' => 'Fase Uji Factory',
            'is_active' => true,
        ]);

        $period = SubmissionPeriod::create([
            'name' => 'Periode Uji Factory '.fake()->unique()->numerify('####-####'),
        ]);

        $dateLabel = SubmissionDateLabel::create([
            'name' => 'Label Tanggal '.fake()->unique()->numerify('####-####'),
        ]);

        $submissionDate = SubmissionDate::create([
            'submission_date_label_id' => $dateLabel->id,
            'datetime' => now()->addWeek(),
            'submission_period_id' => $period->id,
        ]);

        return [
            'form_phase_id' => $formPhase->id,
            'form_access_control_id' => $formAccessControl->id,
            'phase_type_id' => $phaseType->id,
            'order' => 1,
            'submission_date_id' => $submissionDate->id,
        ];
    }
}
