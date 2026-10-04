<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormAccessControl;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Urutan detail tahap diturunkan dari form-nya: detail berform sama mendapat urutan sama,
 * dinomori menurut kemunculan pertama. Urutan kiriman layar diabaikan. Satu query untuk
 * seluruh akses form. **Tidak menulis audit (BR-RPT-09).** Kueri murni.
 *
 * @method static list<int> run(list<int> $accessControlIds)
 */
class NormalisePhaseDetailOrder implements ChangesNoState
{
    use AsAction;

    /**
     * @param  list<int>  $accessControlIds  akses form tiap detail, urut seperti di layar
     * @return list<int> urutan tiap detail, sejajar dengan masukannya
     */
    public function handle(array $accessControlIds): array
    {
        $formIds = FormAccessControl::query()
            ->whereIn('id', array_unique($accessControlIds))
            ->get(['id', 'form_id'])
            ->mapWithKeys(fn (FormAccessControl $access): array => [$access->id => $access->form_id]);

        $orderOfForm = [];
        $orders = [];

        foreach ($accessControlIds as $accessControlId) {
            $formId = $formIds->get($accessControlId);
            $orderOfForm[$formId] ??= count($orderOfForm) + 1;
            $orders[] = $orderOfForm[$formId];
        }

        return $orders;
    }
}
