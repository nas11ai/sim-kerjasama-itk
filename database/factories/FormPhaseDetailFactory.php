<?php

namespace Database\Factories;

use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
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

        $submissionDate = SubmissionDate::create([
            'type' => DeadlineType::Submit,
            'datetime' => now()->addWeek(),
            'submission_period_id' => $period->id,
        ]);

        return [
            'form_phase_id' => $formPhase->id,
            'form_access_control_id' => $formAccessControl->id,
            'phase_type_id' => $phaseType->id,
            'order' => 1,
            'deadline_type' => DeadlineType::Submit,
            'deadline_cycle' => null,
            'submission_date_id' => $submissionDate->id,
        ];
    }
}
