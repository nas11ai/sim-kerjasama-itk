<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormDraftInput;
use App\Data\Request\FormFieldInput;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Support\AffectedDraftOwners;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-34 — dampak Terbitkan Form: draf dibanding field terbit. Field baru wajib mencakup field
 * lama yang menjadi wajib (BR-FE-18); `runningDrafts` = penerima prompt BR-FE-13. Tanpa draf: nol.
 *
 * @phpstan-type Impact array{newRequired: int, runningDrafts: int, relabelled: list<array{label: string, answered: int}>, removed: int}
 *
 * @method static Impact run(Form $form)
 */
class FormPublishImpact implements ChangesNoState
{
    use AsAction;

    /** @return Impact */
    public function handle(Form $form): array
    {
        $draft = $form->draft()->first();
        $fields = $draft === null ? [] : FormDraftInput::fromPayload($draft->payload)->fields;
        $published = $draft === null ? new Collection : $form->formFields()
            ->with('formFieldOptions')
            ->withCount('formFieldResponses')
            ->orderBy('order')
            ->get()
            ->keyBy('id');
        $kept = array_filter(array_map(fn (FormFieldInput $field): ?int => $field->id, $fields));

        return [
            'newRequired' => count(array_filter($fields, fn (FormFieldInput $field): bool => $field->isRequired
                && $published->get($field->id ?? 0)?->is_required !== true)),
            'runningDrafts' => AffectedDraftOwners::countForForm($form),
            'relabelled' => $this->relabelled($fields, $published),
            'removed' => $published->except($kept)->count(),
        ];
    }

    /**
     * Field terjawab yang label field-nya atau label opsi tersimpannya berubah, dengan label lamanya.
     *
     * @param  list<FormFieldInput>  $fields
     * @param  Collection<int, FormField>  $published
     * @return list<array{label: string, answered: int}>
     */
    private function relabelled(array $fields, Collection $published): array
    {
        $rows = [];

        foreach ($fields as $input) {
            $field = $published->get($input->id ?? 0);

            if ($field === null || $field->form_field_responses_count === 0 || !$this->labelChanged($input, $field)) {
                continue;
            }

            $rows[] = ['label' => $field->label, 'answered' => $field->form_field_responses_count];
        }

        return $rows;
    }

    private function labelChanged(FormFieldInput $input, FormField $field): bool
    {
        if ($input->label !== $field->label) {
            return true;
        }

        $labels = $field->formFieldOptions->mapWithKeys(fn (FormFieldOption $option): array => [$option->id => $option->label]);

        foreach ($input->options ?? [] as $option) {
            if ($option['id'] !== null && $labels->has($option['id']) && $labels->get($option['id']) !== $option['label']) {
                return true;
            }
        }

        return false;
    }
}
