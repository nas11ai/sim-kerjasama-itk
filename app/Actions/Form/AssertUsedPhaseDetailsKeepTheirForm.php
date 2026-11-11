<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-20 (semangat BR-FE-23), ADR 0019 — detail berpengajuan tetap pada form, tipe tahap,
 * dan jenis langkahnya (tanggal yang dibaca pengajuan); mengubahnya memindahkan pengajuan itu.
 * Urutan dan needs_review boleh. Hanya menolak, **tanpa audit (BR-RPT-09).**
 *
 * @method static void run(Collection<int, FormPhaseDetail> $existing, list<FormPhaseDetailInput> $details)
 */
class AssertUsedPhaseDetailsKeepTheirForm implements ChangesNoState
{
    use AsAction;

    /**
     * @param  Collection<int, FormPhaseDetail>  $existing  detail lama tahap, ber-key id
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    public function handle(Collection $existing, array $details): void
    {
        $used = FormSubmission::query()
            ->whereIn('form_phase_detail_id', $existing->modelKeys())
            ->distinct()
            ->pluck('form_phase_detail_id')
            ->all();
        $errors = [];

        foreach ($details as $index => $detail) {
            $current = $detail->id === null ? null : $existing->get($detail->id);

            if ($current === null || !in_array($current->id, $used, true)) {
                continue;
            }

            $errors += array_filter([
                "phase_details.{$index}.form_access_control_id" => $current->form_access_control_id !== $detail->formAccessControlId,
                "phase_details.{$index}.phase_type_id" => $current->phase_type_id !== $detail->phaseTypeId,
                "phase_details.{$index}.deadline_type" => $current->deadline_type !== $detail->deadlineType,
                "phase_details.{$index}.deadline_cycle" => $current->deadline_cycle !== $detail->deadlineCycle,
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(
                fn (): string => 'Detail ini sudah dipakai pengajuan; form, tipe tahap, dan jenis langkahnya tidak dapat diubah.',
                $errors,
            ));
        }
    }
}
