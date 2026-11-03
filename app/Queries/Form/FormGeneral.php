<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\ArchivedFieldData;
use App\Data\View\FormEngine\FormFieldEditData;
use App\Data\View\FormEngine\FormGeneralData;
use App\Models\Form;
use App\Models\FormField;
use App\Support\AffectedDraftOwners;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-06 — isi tab General & Fields. Tanpa Form berarti mode buat: kosong dan aktif.
 *
 * @method static FormGeneralData run(?Form $form)
 */
class FormGeneral implements ChangesNoState
{
    use AsAction;

    public function handle(?Form $form): FormGeneralData
    {
        if ($form === null) {
            return new FormGeneralData('', null, null, true, [], [], 0);
        }

        return new FormGeneralData(
            title: $form->title,
            description: $form->description,
            formTypeId: $form->form_type_id,
            isActive: $form->is_active,
            fields: $this->fields($form),
            archivedFields: $this->archivedFields($form),
            // BR-FE-13 — angkanya terlihat sebelum Admin menyimpan, saat keputusan notifikasi diambil.
            affectedDrafts: AffectedDraftOwners::countForForm($form),
        );
    }

    /** @return list<FormFieldEditData> */
    private function fields(Form $form): array
    {
        return array_values($form->formFields()
            ->with('formFieldOptions')
            ->withCount('formFieldResponses')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (FormField $field): FormFieldEditData => FormFieldEditData::fromModel($field))
            ->all());
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
            ->map(fn (FormField $field): ArchivedFieldData => ArchivedFieldData::fromModel($field))
            ->all());
    }
}
