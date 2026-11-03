<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
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
 * BR-FE-20 — merevisi tahap formulir dengan menyinkronkan detailnya per id. Versi lama
 * menghapus semua detail lewat query massal lalu membuat ulang: form evaluasi ikut
 * ter-cascade, pengajuan kehilangan tahapnya, dan `submission_date_id` hilang.
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
        $existing = $phase->formPhaseDetails()->get()->keyBy('id');

        $this->assertOwnedBy($existing, $input->details);
        AssertUsedPhaseDetailsKeepTheirForm::run($existing, $input->details);
        AssertPhaseDeadlines::run($phase, $input->details, $existing->mapWithKeys(
            fn (FormPhaseDetail $detail): array => [$detail->id => $detail],
        )->all());

        DB::transaction(function () use ($phase, $input, $actor, $existing): void {
            $old = $this->snapshot($phase, $existing->modelKeys());

            $phase->update([
                'title' => $input->title,
                'description' => $input->description,
                'is_active' => $input->isActive,
            ]);

            $this->removeDropped($existing, $input->details);
            $detailIds = $this->syncDetails($phase, $existing, $input->details);

            // BR-RPT-09 — di dalam transaksi: revisi yang ditolak tidak meninggalkan jejak.
            AuditTrail::record(
                'Tahap formulir direvisi',
                $phase,
                $actor,
                new AuditChange(
                    old: $old,
                    new: $this->snapshot($phase, $detailIds),
                ),
            );
        });
    }

    /**
     * Id milik tahap lain ditolak: memperbaruinya berarti memindahkan detail itu, beserta
     * pengajuan dan form evaluasinya, ke tahap ini tanpa ada yang memutuskannya.
     *
     * @param  Collection<int, FormPhaseDetail>  $existing
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    private function assertOwnedBy(Collection $existing, array $details): void
    {
        foreach ($details as $detail) {
            if ($detail->id === null || $existing->has($detail->id)) {
                continue;
            }

            throw ValidationException::withMessages([
                'phase_details' => "Detail #{$detail->id} bukan milik tahap formulir ini.",
            ]);
        }
    }

    /**
     * Satu per satu lewat model, bukan ->delete() massal: hanya event model yang menjalankan
     * penjaga BR-FE-20, dan penolakannya menggulung kembali seluruh revisi.
     *
     * @param  Collection<int, FormPhaseDetail>  $existing
     * @param  list<FormPhaseDetailInput>  $details
     */
    private function removeDropped(Collection $existing, array $details): void
    {
        $kept = array_filter(array_map(fn (FormPhaseDetailInput $d): ?int => $d->id, $details));

        $existing->except($kept)->each(fn (FormPhaseDetail $detail) => $detail->delete());
    }

    /**
     * Detail lama diperbarui di tempat, sehingga form evaluasi dan pengajuannya tetap
     * menempel. Detail tanpa id dibuat baru.
     *
     * @param  Collection<int, FormPhaseDetail>  $existing
     * @param  list<FormPhaseDetailInput>  $details
     * @return list<int> id detail tahap ini sesudah revisi
     */
    private function syncDetails(FormPhase $phase, Collection $existing, array $details): array
    {
        $ids = [];
        $orders = NormalisePhaseDetailOrder::run(array_map(
            fn (FormPhaseDetailInput $d): int => $d->formAccessControlId,
            $details,
        ));

        foreach ($details as $index => $detail) {
            $attributes = [
                'form_access_control_id' => $detail->formAccessControlId,
                'phase_type_id' => $detail->phaseTypeId,
                'order' => $orders[$index],
                'needs_review' => $detail->needsReview,
            ];

            $current = $detail->id === null ? null : $existing->get($detail->id);

            if ($current !== null) {
                // BR-FE-07/26 — tenggat boleh dipindah, tak boleh dikosongkan: null/absen
                // mempertahankan nilai yang ada.
                $current->update($attributes + array_filter(
                    $detail->deadlineIds(),
                    fn (?int $id): bool => $id !== null,
                ));
                $ids[] = $current->id;

                continue;
            }

            // BR-FE-07 — detail baru membawa tenggatnya sendiri (wajib di FormPhaseRequest).
            $ids[] = $phase->formPhaseDetails()->create($attributes + $detail->deadlineIds())->id;
        }

        return $ids;
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
