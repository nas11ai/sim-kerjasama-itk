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
 * W102 — pilihan akses detail tahap: aturan akses semua Form aktif dan Form yang sudah dipakai
 * detail tahap ini, urut judul Form. Editor mandiri (BR-FE-28); bila dibuka dari pustaka bertapis
 * Form (`$preferred`), Form itu didahulukan supaya langkah Pengajuan bawaan memakainya.
 *
 * @phpstan-type Option array{value: string, label: string, formId: int, formTitle: string}
 *
 * @method static list<Option> run(?FormPhase $phase, ?Form $preferred = null)
 */
class PhaseAccessOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<Option> */
    public function handle(?FormPhase $phase, ?Form $preferred = null): array
    {
        // Mode buat (`$phase` null) tidak punya detail: subquery-nya kosong.
        $used = FormAccessControl::query()->select('form_id')->whereIn(
            'id',
            FormPhaseDetail::query()->select('form_access_control_id')->where('form_phase_id', $phase?->id)->inLatestVersion(),
        );

        $forms = Form::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereIn('id', $used))
            ->when($preferred, fn (Builder $query, Form $form) => $query->orderByRaw('(id = ?) desc', [$form->id]))
            ->orderBy('title')
            ->orderBy('id')
            ->get(['id', 'title']);

        $rules = FormAccessRules::forForms(array_values(array_map(fn (Form $f): int => $f->id, $forms->all())));
        $options = [];

        foreach ($forms as $candidate) {
            foreach ($rules[$candidate->id] ?? [] as $rule) {
                $options[] = [
                    'value' => (string) $rule->id,
                    'label' => implode(' · ', [$candidate->title, $rule->permissionLabel, $rule->organizationLabel]),
                    'formId' => $candidate->id,
                    'formTitle' => $candidate->title,
                ];
            }
        }

        return $options;
    }
}
