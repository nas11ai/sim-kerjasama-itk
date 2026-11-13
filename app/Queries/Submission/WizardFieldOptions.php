<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\TechnologyReadinessLevel;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan satu field wizard. Skema dan TRL dibaca dari tabelnya, bukan dari opsi field,
 * karena keduanya disimpan di kolom pengajuan (ADR 0005).
 *
 * @method static list<array{value: string, label: string}> run(FormSubmission $submission, FormField $field, string $type)
 */
class WizardFieldOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<array{value: string, label: string}> */
    public function handle(FormSubmission $submission, FormField $field, string $type): array
    {
        return match ($type) {
            'select', 'radio' => $this->choices($field),
            'scheme_selector' => $this->schemes($submission),
            'trl_selector' => $this->trls($submission),
            default => [],
        };
    }

    /** @return list<array{value: string, label: string}> */
    private function choices(FormField $field): array
    {
        return array_values($field->formFieldOptions
            ->map(fn (FormFieldOption $option): array => ['value' => (string) $option->id, 'label' => $option->label])
            ->all());
    }

    /**
     * BR-SCH-10 [SCH-RULE-14] — skema nonaktif tidak ditawarkan, kecuali yang sudah dipakai
     * pengajuan ini: menyembunyikannya membuat pilihan tersimpan tampak hilang.
     *
     * @return list<array{value: string, label: string}>
     */
    private function schemes(FormSubmission $submission): array
    {
        return array_values(Scheme::query()
            ->where(fn (Builder $q) => $q->where('is_active', true)->orWhere('id', $submission->scheme_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Scheme $scheme): array => ['value' => (string) $scheme->id, 'label' => $scheme->name])
            ->all());
    }

    /**
     * BR-SCH-05 — TRL hanya dari izin skema terpilih; tanpa skema belum ada yang bisa dipilih.
     *
     * @return list<array{value: string, label: string}>
     */
    private function trls(FormSubmission $submission): array
    {
        $scheme = $submission->scheme;

        if ($scheme === null) {
            return [];
        }

        return array_values($scheme->allowedTrls()
            ->orderBy('level')
            ->get()
            ->map(fn (TechnologyReadinessLevel $trl): array => ['value' => (string) $trl->id, 'label' => "TKT {$trl->level} · {$trl->name}"])
            ->all());
    }
}
