<?php

namespace App\Actions\Form;

use App\Data\Request\FormInput;
use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menyunting kartu General Form — judul, deskripsi, tipe, status — langsung, bukan isi berdraf.
 * Field Form lewat draf dan Terbitkan (BR-FE-34).
 *
 * @method static void run(Form $form, FormInput $input, User $actor)
 */
class ReviseForm
{
    use AsAction;

    public function handle(Form $form, FormInput $input, User $actor): void
    {
        DB::transaction(function () use ($form, $input, $actor): void {
            $old = $form->auditSnapshot();
            $form->update($input->attributes());

            AuditTrail::record('Formulir diubah', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
        });
    }
}
