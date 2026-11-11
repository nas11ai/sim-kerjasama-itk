<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
use App\Models\FormPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — membuat tahap formulir beserta detailnya; setiap detail membawa jenis tenggat dan
 * siklusnya (ADR 0019, `FormPhaseRequest` mewajibkannya). Urutan detail diturunkan dari form-nya (K3).
 *
 * @method static FormPhase run(FormPhaseInput $input, User $actor)
 */
class CreateFormPhase
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhaseInput $input, User $actor): FormPhase
    {
        AssertPhaseSteps::run($input->details);

        return DB::transaction(function () use ($input, $actor): FormPhase {
            $phase = FormPhase::create([
                'title' => $input->title,
                'description' => $input->description,
                'is_active' => $input->isActive,
            ]);

            $detailIds = $this->createDetails($phase, $input->details);

            // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
            AuditTrail::record(
                'Tahap formulir dibuat',
                $phase,
                $actor,
                new AuditChange(new: [
                    'title' => $phase->title,
                    'description' => $phase->description,
                    'is_active' => $phase->is_active,
                    'detail_ids' => $detailIds,
                ]),
            );

            return $phase;
        });
    }

    /**
     * @param  list<FormPhaseDetailInput>  $details
     * @return list<int>
     */
    private function createDetails(FormPhase $phase, array $details): array
    {
        $orders = NormalisePhaseDetailOrder::run(array_map(
            fn (FormPhaseDetailInput $d): int => $d->formAccessControlId,
            $details,
        ));

        $detailIds = [];

        foreach ($details as $index => $detail) {
            $detailIds[] = $phase->formPhaseDetails()->create([
                'form_access_control_id' => $detail->formAccessControlId,
                'phase_type_id' => $detail->phaseTypeId,
                'order' => $orders[$index],
                'needs_review' => $detail->needsReview,
                ...$detail->stepKind(),
            ])->id;
        }

        return $detailIds;
    }
}
