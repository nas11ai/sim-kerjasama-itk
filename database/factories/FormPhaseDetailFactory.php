<?php

namespace Database\Factories;

use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
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

        return [
            'form_phase_id' => $formPhase->id,
            // BR-FE-32 — langkah pabrik masuk versi terbaru tahapnya (Versi 1 bila belum ada).
            'form_phase_version_id' => fn (array $attributes): int => self::versionFor((int) $attributes['form_phase_id'])->id,
            'form_access_control_id' => $formAccessControl->id,
            'phase_type_id' => $phaseType->id,
            'order' => 1,
            'deadline_type' => DeadlineType::Submit,
            'deadline_cycle' => null,
        ];
    }

    /** Versi terbaru `$phaseId`, atau Versi 1 baru tanpa audit (jalur produksinya PublishPhaseVersion). */
    public static function versionFor(int $phaseId): FormPhaseVersion
    {
        return FormPhaseVersion::query()->where('form_phase_id', $phaseId)->orderByDesc('number')->first()
            ?? FormPhaseVersion::create(['form_phase_id' => $phaseId, 'number' => 1, 'published_at' => now()]);
    }

    /**
     * ADR 0019 — langkah membaca tanggalnya dari kalender periode yang menautkan fasenya. Fase
     * yang belum tertaut mendapat periode uji dengan tanggal kerja langkah itu seminggu lagi,
     * supaya langkah pabrik terbuka seperti sebelum tanggal pindah ke periode.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (FormPhaseDetail $step): void {
            if (SubmissionPeriodPhase::query()->where('form_phase_id', $step->form_phase_id)->exists()) {
                return;
            }

            $period = SubmissionPeriod::create(['name' => 'Periode Uji Factory '.fake()->unique()->numerify('####-####')]);
            SubmissionDate::create([
                'type' => $step->deadline_type,
                'cycle' => $step->deadline_cycle,
                'datetime' => now()->addWeek(),
                'submission_period_id' => $period->id,
            ]);
            SubmissionPeriodPhase::create(['submission_period_id' => $period->id, 'form_phase_id' => $step->form_phase_id]);
        });
    }

    /** Fase langkah ditautkan ke `$period`; kalendernya disiapkan pemanggil. */
    public function forPeriod(SubmissionPeriod $period): static
    {
        return $this->afterMaking(function (FormPhaseDetail $step) use ($period): void {
            SubmissionPeriodPhase::query()->firstOrCreate([
                'submission_period_id' => $period->id,
                'form_phase_id' => $step->form_phase_id,
            ]);
        });
    }
}
