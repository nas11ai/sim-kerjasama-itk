<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W102 — pilihan akses detail tahap: aturan akses semua Form aktif, Form yang sedang dibuka,
 * dan Form yang sudah dipakai detail tahap ini. Form yang dibuka lebih dulu, sisanya urut judul.
 *
 * @phpstan-type Option array{value: string, label: string, formId: int}
 *
 * @method static list<Option> run(Form $form, ?FormPhase $phase)
 */
class PhaseAccessOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<Option> */
    public function handle(Form $form, ?FormPhase $phase): array
    {
        // Mode buat (`$phase` null) tidak punya detail: subquery-nya kosong.
        $used = FormAccessControl::query()->select('form_id')->whereIn(
            'id',
            FormPhaseDetail::query()->select('form_access_control_id')->where('form_phase_id', $phase?->id),
        );

        $forms = Form::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhere('id', $form->id)
                ->orWhereIn('id', $used))
            ->orderBy('title')
            ->get(['id', 'title'])
            ->sortBy(fn (Form $candidate): int => $candidate->id === $form->id ? 0 : 1);

        $rules = FormAccessRules::forForms(array_values(array_map(fn (Form $f): int => $f->id, $forms->all())));
        $options = [];

        foreach ($forms as $candidate) {
            foreach ($rules[$candidate->id] ?? [] as $rule) {
                $options[] = [
                    'value' => (string) $rule->id,
                    'label' => implode(' — ', [$candidate->title, $rule->permissionLabel, $rule->organizationLabel]),
                    'formId' => $candidate->id,
                ];
            }
        }

        return $options;
    }
}
