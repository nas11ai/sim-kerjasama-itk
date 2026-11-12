<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormDraftInput;
use App\Data\Request\FormFieldInput;
use App\Data\View\FormEngine\ArchivedFieldData;
use App\Data\View\FormEngine\FormFieldEditData;
use App\Data\View\FormEngine\FormGeneralData;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-06 — isi tab General & Fields. Tanpa Form berarti mode buat: kosong dan aktif.
 * BR-FE-34 — editor membuka draf bila ada; field draf yang sudah terbit membawa data terbitnya.
 *
 * @method static FormGeneralData run(?Form $form)
 */
class FormGeneral implements ChangesNoState
{
    use AsAction;

    private const NO_IMPACT = ['newRequired' => 0, 'runningDrafts' => 0, 'relabelled' => [], 'removed' => 0];

    public function handle(?Form $form): FormGeneralData
    {
        if ($form === null) {
            return new FormGeneralData(
                title: '',
                description: null,
                formTypeId: null,
                isActive: true,
                hasDraft: false,
                publishedAt: null,
                fields: [],
                publishedFields: [],
                archivedFields: [],
                impact: self::NO_IMPACT,
            );
        }

        $published = $this->published($form);
        $draft = $form->draft()->first();
        $publishedFields = array_values($published->map(fn (FormField $field): FormFieldEditData => FormFieldEditData::fromModel($field))->all());

        return new FormGeneralData(
            title: $form->title,
            description: $form->description,
            formTypeId: $form->form_type_id,
            isActive: $form->is_active,
            hasDraft: $draft !== null,
            publishedAt: $form->published_at?->toIso8601String(),
            fields: $draft === null ? $publishedFields : $this->draftFields(FormDraftInput::fromPayload($draft->payload), $published->keyBy('id')),
            publishedFields: $publishedFields,
            archivedFields: $this->archivedFields($form),
            // BR-FE-13 — angkanya terlihat sebelum Admin menerbitkan, saat keputusan notifikasi diambil.
            impact: FormPublishImpact::run($form),
        );
    }

    /** @return Collection<int, FormField> */
    private function published(Form $form): Collection
    {
        return $form->formFields()
            ->with('formFieldOptions')
            ->withCount('formFieldResponses')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, FormField>  $published
     * @return list<FormFieldEditData>
     */
    private function draftFields(FormDraftInput $draft, Collection $published): array
    {
        return array_map(function (FormFieldInput $input) use ($published): FormFieldEditData {
            $field = $published->get($input->id ?? 0);
            $saved = $field === null ? null : FormFieldEditData::fromModel($field);

            return new FormFieldEditData(
                id: $input->id,
                label: $input->label,
                fieldTypeId: $input->fieldTypeId,
                isRequired: $input->isRequired,
                requiredSince: $saved?->requiredSince,
                answerCount: $saved->answerCount ?? 0,
                // BR-FE-24 — opsi null di draf berarti tak berubah: tampilkan opsi terbitnya.
                options: $input->options ?? $saved->options ?? [],
            );
        }, $draft->fields);
    }

    /** @return list<ArchivedFieldData> */
    private function archivedFields(Form $form): array
    {
        return array_values($form->formFields()
            ->onlyTrashed()
            ->with('fieldType')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (FormField $field): ArchivedFieldData => new ArchivedFieldData(
                id: $field->id,
                label: $field->label,
                typeName: FieldTypeOptions::label($field->fieldType->name ?? ''),
            ))
            ->all());
    }
}
