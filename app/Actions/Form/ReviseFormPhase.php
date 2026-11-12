<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
use App\Data\Request\PhaseStructureInput;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Merevisi tahap formulir. BR-FE-32 (G3-W4, sementara sampai draf tahap) — langkahnya terbit sebagai
 * versi berikutnya; langkah ber-`id` membawa Form Evaluasi terpasangnya. Versi lama tidak disentuh,
 * jadi pengajuan, penugasan, dan angkatan terpaku tetap pada langkah yang persis sama.
 *
 * @method static void run(FormPhase $phase, FormPhaseInput $input, User $actor)
 */
class ReviseFormPhase
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhase $phase, FormPhaseInput $input, User $actor): void
    {
        $existing = $phase->currentDetails()->with('stepEvaluationForms')->get()->keyBy('id');

        $this->assertCurrent($phase, $existing, $input->details);

        DB::transaction(function () use ($phase, $input, $actor, $existing): void {
            $old = $this->snapshot($phase, $existing->modelKeys());

            $phase->update([
                'title' => $input->title,
                'description' => $input->description,
                'is_active' => $input->isActive,
            ]);

            $version = PublishPhaseVersion::run($phase, $this->structure($existing, $input->details), $actor);

            // BR-RPT-09 — di dalam transaksi: revisi yang ditolak tidak meninggalkan jejak.
            AuditTrail::record(
                'Tahap formulir direvisi',
                $phase,
                $actor,
                new AuditChange(
                    old: $old,
                    new: $this->snapshot($phase, $version->formPhaseDetails()->get()->map(fn (FormPhaseDetail $d): int => $d->id)->all()),
                ),
            );
        });
    }

    /**
     * Id milik tahap lain ditolak: menyalinnya memindahkan Form Evaluasi langkah tahap lain ke
     * tahap ini tanpa ada yang memutuskannya. Id versi lama tahap ini berarti layar sudah usang.
     *
     * @param  Collection<int, FormPhaseDetail>  $existing
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    private function assertCurrent(FormPhase $phase, Collection $existing, array $details): void
    {
        foreach ($details as $detail) {
            if ($detail->id === null || $existing->has($detail->id)) {
                continue;
            }

            if ($phase->formPhaseDetails()->whereKey($detail->id)->exists()) {
                throw ValidationException::withMessages(['form_phase_detail_id' => AssertCurrentPhaseStep::REPUBLISHED]);
            }

            throw ValidationException::withMessages([
                'phase_details' => "Detail #{$detail->id} bukan milik tahap formulir ini.",
            ]);
        }
    }

    /**
     * @param  Collection<int, FormPhaseDetail>  $existing
     * @param  list<FormPhaseDetailInput>  $details
     */
    private function structure(Collection $existing, array $details): PhaseStructureInput
    {
        return new PhaseStructureInput(array_map(function (FormPhaseDetailInput $detail) use ($existing): FormPhaseDetailInput {
            $current = $detail->id === null ? null : $existing->get($detail->id);

            return $current === null ? $detail : $detail->withEvaluationForms(PhaseStructureInput::evaluationFormsOf($current));
        }, $details));
    }

    /**
     * @param  array<int, int>  $detailIds
     * @return array<string, string|bool|list<int>|null>
     */
    private function snapshot(FormPhase $phase, array $detailIds): array
    {
        return [
            'title' => $phase->title,
            'description' => $phase->description,
            'is_active' => $phase->is_active,
            'detail_ids' => array_values($detailIds),
        ];
    }
}
