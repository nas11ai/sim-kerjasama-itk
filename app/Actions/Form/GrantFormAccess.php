<?php

namespace App\Actions\Form;

use App\Data\Request\FormAccessInput;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-02 — memasang kontrol akses formulir pada satu atau beberapa organisasi (K6). ADR 0013:
 * form itu terlihat oleh organisasi tersebut dan seluruh unit di bawahnya.
 *
 * @method static list<FormAccessControl> run(FormAccessInput $input, User $actor)
 */
class GrantFormAccess
{
    use AsAction;

    /** @param  list<string>  $organizationNames */
    public static function duplicateMessage(array $organizationNames): string
    {
        return 'Hak akses ini sudah terdaftar untuk: '.implode(', ', $organizationNames).'.';
    }

    /**
     * @return list<FormAccessControl>
     *
     * @throws ValidationException
     */
    public function handle(FormAccessInput $input, User $actor): array
    {
        // Seluruhnya atau tidak sama sekali: satu pasangan ganda menolak kiriman sebelum ada yang tersimpan.
        $duplicates = array_values(array_filter(
            $input->organizationIds,
            static fn (int $organizationId): bool => FormAccessControl::grantExists($input->formId, $input->permission, $organizationId),
        ));

        if ($duplicates !== []) {
            $names = array_values(Organization::query()->whereKey($duplicates)->orderBy('name')->get(['name'])
                ->map(static fn (Organization $organization): string => $organization->name)
                ->all());
            throw ValidationException::withMessages(['duplicate' => self::duplicateMessage($names)]);
        }

        return DB::transaction(fn (): array => array_map(
            fn (int $organizationId): FormAccessControl => $this->grant($input, $organizationId, $actor),
            $input->organizationIds,
        ));
    }

    private function grant(FormAccessInput $input, int $organizationId, User $actor): FormAccessControl
    {
        $control = FormAccessControl::create($input->attributesFor($organizationId));

        // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
        AuditTrail::record(
            'Kontrol akses formulir dibuat',
            $control,
            $actor,
            new AuditChange(new: $control->auditSnapshot()),
        );

        return $control;
    }
}
