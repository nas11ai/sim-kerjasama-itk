<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Salinan Form beserta field dan opsi yang masih aktif; yang sudah di-soft-delete
 * (BR-FE-12, BR-FE-24) tidak ikut tersalin.
 *
 * @method static Form run(Form $form, User $actor)
 */
class DuplicateForm
{
    use AsAction;

    public function handle(Form $form, User $actor): Form
    {
        return DB::transaction(function () use ($form, $actor): Form {
            $copy = $form->replicate();
            $copy->title = $form->title.' (Copy)';
            $copy->save();

            foreach ($form->formFields as $field) {
                $newField = $field->replicate();
                $newField->form_id = $copy->id;
                $newField->save();

                foreach ($field->formFieldOptions as $option) {
                    $newOption = $option->replicate();
                    $newOption->form_field_id = $newField->id;
                    $newOption->save();
                }
            }

            AuditTrail::record('Formulir diduplikasi', $copy, $actor, new AuditChange(new: [
                ...$copy->auditSnapshot(),
                'source_form_id' => $form->id,
            ]));

            return $copy;
        });
    }
}
