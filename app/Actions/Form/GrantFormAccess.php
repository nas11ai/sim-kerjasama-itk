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
 * BR-FE-02 — memasang kontrol akses formulir pada sebuah organisasi. ADR 0013: form itu
 * terlihat oleh organisasi tersebut dan seluruh unit di bawahnya.
 *
 * @method static FormAccessControl run(FormAccessInput $input, User $actor)
 */
class GrantFormAccess
{
    use AsAction;

    public const DUPLICATE_MESSAGE = 'Gabungan formulir, permission, dan organisasi tersebut sudah terdaftar.';

    /**
     * @throws ValidationException
     */
    public function handle(FormAccessInput $input, User $actor): FormAccessControl
    {
        if (FormAccessControl::grantExists($input->formId, $input->permission, $input->organizationId)) {
            throw ValidationException::withMessages(['duplicate' => self::DUPLICATE_MESSAGE]);
        }

        return DB::transaction(function () use ($input, $actor): FormAccessControl {
            $control = FormAccessControl::create($input->attributes());

            // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
            AuditTrail::record(
                'Kontrol akses formulir dibuat',
                $control,
                $actor,
                new AuditChange(new: $control->auditSnapshot()),
            );

            return $control;
        });
    }
}
