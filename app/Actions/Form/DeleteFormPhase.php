<?php

namespace App\Actions\Form;

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-20 — menghapus tahap formulir beserta detailnya.
 *
 * @method static void run(FormPhase $phase, User $actor)
 */
class DeleteFormPhase
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhase $phase, User $actor): void
    {
        // Penjagaannya dijalankan **sebelum** apa pun disentuh. Versi lama menghapus
        // detail dulu lalu membiarkan foreign key yang menolak, sehingga Admin menerima
        // SQLSTATE mentah dan penghapusan bergantung pada rollback, bukan pada aturan.
        ReferenceGuard::assertUnreferenced('Tahap formulir', [
            'pengajuan' => FormSubmission::query()
                ->whereIn('form_phase_detail_id', $phase->formPhaseDetails()->select('id'))
                ->count(),
        ]);

        AuditTrail::record(
            'Tahap formulir dihapus',
            $phase,
            $actor,
            new AuditChange(
                old: ['title' => $phase->title],
            ),
        );

        DB::transaction(function () use ($phase): void {
            // Satu per satu, bukan ->delete() massal: penghapusan lewat query
            // builder tidak memicu event model, jadi penjagaan per detail
            // (BR-FE-20) tidak akan pernah dijalankan.
            $phase->formPhaseDetails->each(fn (FormPhaseDetail $detail) => $detail->delete());

            $phase->delete();
        });
    }
}
