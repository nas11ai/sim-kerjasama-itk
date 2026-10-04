<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-20 — kontrol akses yang dirujuk detail tahap tidak boleh dihapus. Penolakannya
 * menyebut setiap kontrol akses yang menghalangi, supaya Operator tidak menebak. Hanya
 * membaca dan menolak, **tidak menulis audit (BR-RPT-09).**
 *
 * @method static void run(Collection<int, FormAccessControl> $controls)
 */
class AssertFormAccessUnused implements ChangesNoState
{
    use AsAction;

    /**
     * @param  Collection<int, FormAccessControl>  $controls
     *
     * @throws ValidationException
     */
    public function handle(Collection $controls): void
    {
        $usage = FormPhaseDetail::query()
            ->whereIn('form_access_control_id', $controls->modelKeys())
            ->get(['form_access_control_id'])
            ->countBy('form_access_control_id');

        if ($usage->isEmpty()) {
            return;
        }

        $blockers = $controls->load(['form', 'organization'])
            ->filter(fn (FormAccessControl $control): bool => $usage->has($control->id))
            ->map(fn (FormAccessControl $control): string => sprintf(
                '%s — %s — %s (%d detail tahap)',
                $control->form?->title,
                $control->permission,
                $control->organization?->name,
                $usage->get($control->id),
            ));

        throw ValidationException::withMessages([
            'id' => 'Tidak ada kontrol akses yang dihapus: '.$blockers->implode('; ')
                .' masih dipakai detail tahap formulir. Lepaskan dari tahapnya lebih dulu.',
        ]);
    }
}
