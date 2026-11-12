<?php

namespace App\Actions\Form;

use App\Data\Request\FormDraftInput;
use App\Data\Request\FormInput;
use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Form baru dari layar Buat Form. BR-FE-34 — field-nya lahir sebagai draf; Form belum terbit
 * (`published_at` null) sampai Admin menerbitkannya.
 *
 * @method static Form run(FormInput $input, FormDraftInput $fields, User $actor)
 */
class CreateForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormInput $input, FormDraftInput $fields, User $actor): Form
    {
        return DB::transaction(function () use ($input, $fields, $actor): Form {
            $form = Form::create($input->attributes());
            AuditTrail::record('Formulir dibuat', $form, $actor, new AuditChange(new: $form->auditSnapshot()));
            SaveFormDraft::run($form, $fields, $actor);

            return $form;
        });
    }
}
