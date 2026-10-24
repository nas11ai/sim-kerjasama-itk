<?php

namespace App\Actions\Form;

use App\Data\Request\FormInput;
use App\Models\Form;
use App\Models\User;
use App\Services\Form\FormFieldWriter;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SchemeFieldGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Form baru beserta field dan opsinya dari layar Buat Form.
 *
 * @method static Form run(FormInput $input, User $actor)
 */
class CreateForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormInput $input, User $actor): Form
    {
        // BR-SCH-05 — trl_selector tidak berdiri sendiri; pilihannya disaring
        // oleh skema, jadi scheme_selector harus ada di Form yang sama.
        SchemeFieldGuard::assertTrlHasSchemeSelector($input->fieldTypeIds());

        return DB::transaction(function () use ($input, $actor): Form {
            $form = Form::create($input->attributes());
            FormFieldWriter::sync($form, $input->fields ?? []);

            AuditTrail::record('Formulir dibuat', $form, $actor, new AuditChange(new: $form->auditSnapshot()));

            return $form;
        });
    }
}
