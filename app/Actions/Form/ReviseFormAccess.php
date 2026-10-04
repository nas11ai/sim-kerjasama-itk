<?php

namespace App\Actions\Form;

use App\Data\Request\FormAccessInput;
use App\Models\FormAccessControl;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mengubah permission atau organisasi sebuah kontrol akses formulir. Formnya hanya boleh
 * diganti selama belum dipakai detail tahap (BR-FE-20, semangat BR-FE-23).
 *
 * @method static FormAccessControl run(FormAccessControl $control, FormAccessInput $input, User $actor)
 */
class ReviseFormAccess
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormAccessControl $control, FormAccessInput $input, User $actor): FormAccessControl
    {
        return DB::transaction(function () use ($control, $input, $actor): FormAccessControl {
            // Dikunci: detail tahap baru yang merujuknya lewat FK menunggu sampai selesai,
            // jadi penjaga form di bawah tidak kalah balapan.
            $locked = FormAccessControl::query()->lockForUpdate()->findOrFail($control->id);

            // Permission dan organisasi adalah kebijakan akses; form adalah identitas data
            // yang dirujuk detail tahap dan pengajuannya — menggantinya memindahkan mereka
            // diam-diam.
            if ($input->formId !== $locked->form_id && $locked->formPhaseDetails()->exists()) {
                throw ValidationException::withMessages([
                    'form_id' => 'Kontrol akses ini sudah dipakai detail tahap formulir, jadi formulirnya '
                        .'tidak dapat diganti. Buat kontrol akses baru untuk formulir lain.',
                ]);
            }

            if (FormAccessControl::grantExists($input->formId, $input->permission, $input->organizationId, $locked->id)) {
                throw ValidationException::withMessages(['duplicate' => GrantFormAccess::DUPLICATE_MESSAGE]);
            }

            $old = $locked->auditSnapshot();

            $locked->update($input->attributes());

            AuditTrail::record(
                'Kontrol akses formulir diubah',
                $locked,
                $actor,
                new AuditChange(old: $old, new: $locked->auditSnapshot()),
            );

            return $locked;
        });
    }
}
