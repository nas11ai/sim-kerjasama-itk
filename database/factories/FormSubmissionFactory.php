<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'submitted_by' => User::factory(),
            'status' => Draft::class,
            'is_submitted' => false,
            'submission_period_id' => fn (array $attributes) => $this->periodFor($attributes),
        ];
    }

    /**
     * BR-FE-26 — pengajuan yang menunjuk langkah wajib berperiode (check constraint
     * `form_submissions_detail_has_period`). Fixture yang hanya menyebut langkahnya mendapat
     * periode pemilik Batas Submit langkah itu — periode yang kalendernya memuat tanggal yang
     * sama dengan yang dibaca gerbang — atau periode baru bila langkah tak menunjuk tanggal.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function periodFor(array $attributes): ?int
    {
        $stepId = $attributes['form_phase_detail_id'] ?? null;

        if ($stepId === null) {
            return null;
        }

        $step = FormPhaseDetail::query()->with('submissionDate')->find($stepId);

        return $step?->submissionDate?->submission_period_id
            ?? SubmissionPeriod::factory()->create()->id;
    }
}
