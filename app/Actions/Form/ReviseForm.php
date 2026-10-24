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
 * Menyunting Form dari layar Ubah Form. Pemberitahuan ke pemilik draft berjalan (BR-FE-13)
 * use case terpisah: NotifyFormChange, dipanggil sesudah perubahan ini tersimpan.
 *
 * @method static void run(Form $form, FormInput $input, User $actor)
 */
class ReviseForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Form $form, FormInput $input, User $actor): void
    {
        // BR-SCH-05 — berlaku sama saat Form disunting, bukan hanya saat dibuat.
        SchemeFieldGuard::assertTrlHasSchemeSelector($input->fieldTypeIds());

        DB::transaction(function () use ($form, $input, $actor): void {
            $old = $form->auditSnapshot();
            $form->update($input->attributes());

            if ($input->fields !== null) {
                FormFieldWriter::sync($form, $input->fields);
            }

            AuditTrail::record('Formulir diubah', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
        });
    }
}
