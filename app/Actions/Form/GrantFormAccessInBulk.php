<?php

namespace App\Actions\Form;

use App\Data\Request\FormAccessInput;
use App\Models\FormAccessControl;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-02 — memasang banyak kontrol akses sekaligus. Gabungan yang sudah terdaftar
 * dilewati dan dihitung, bukan menggagalkan seluruh kiriman.
 *
 * @method static array{created: int, skipped: int} run(list<FormAccessInput> $grants, User $actor)
 */
class GrantFormAccessInBulk
{
    use AsAction;

    /**
     * @param  list<FormAccessInput>  $grants
     * @return array{created: int, skipped: int}
     */
    public function handle(array $grants, User $actor): array
    {
        return DB::transaction(function () use ($grants, $actor): array {
            $created = 0;

            foreach ($grants as $grant) {
                if (FormAccessControl::grantExists($grant->formId, $grant->permission, $grant->organizationId)) {
                    continue;
                }

                $control = FormAccessControl::create($grant->attributes());
                AuditTrail::record('Kontrol akses formulir dibuat', $control, $actor, new AuditChange(
                    new: $control->auditSnapshot(),
                ));
                $created++;
            }

            return ['created' => $created, 'skipped' => count($grants) - $created];
        });
    }
}
