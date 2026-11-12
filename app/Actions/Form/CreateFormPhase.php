<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseInput;
use App\Data\Request\PhaseStructureInput;
use App\Models\FormPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-07 — membuat tahap formulir beserta langkahnya; setiap langkah membawa jenis tenggat dan
 * siklusnya (ADR 0019). BR-FE-32 (G3-W4, sementara sampai draf tahap) — langkahnya terbit sebagai Versi 1.
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
        return DB::transaction(function () use ($input, $actor): FormPhase {
            $phase = FormPhase::create([
                'title' => $input->title,
                'description' => $input->description,
                'is_active' => $input->isActive,
            ]);

            $version = PublishPhaseVersion::run($phase, new PhaseStructureInput($input->details), $actor);

            // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
            AuditTrail::record(
                'Tahap formulir dibuat',
                $phase,
                $actor,
                new AuditChange(new: [
                    'title' => $phase->title,
                    'description' => $phase->description,
                    'is_active' => $phase->is_active,
                    'detail_ids' => $version->formPhaseDetails()->pluck('id')->all(),
                ]),
            );

            return $phase;
        });
    }
}
