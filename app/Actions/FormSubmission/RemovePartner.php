<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\Partner;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-21 — mencabut mitra dari sebuah pengajuan.
 *
 * Dibuat bersama pasangannya karena alasan yang sama dengan BR-SM-18 dan
 * BR-SM-20: daftar yang hanya bisa bertambah bukan daftar, melainkan jalan
 * buntu yang menunggu satu salah ketik.
 *
 * @method static void run(FormSubmission $submission, Partner $partner, User $actor)
 */
class RemovePartner
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, Partner $partner, User $actor): void
    {
        if (!$actor->can('manageResearchMembers', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat mengatur mitra, dan hanya selama pengajuan belum disetujui, ditolak, atau ditarik.'
            );
        }

        if ($partner->form_submission_id !== $submission->id) {
            throw ValidationException::withMessages([
                'id' => 'Mitra ini bukan bagian dari pengajuan tersebut.',
            ]);
        }

        AuditTrail::record(
            'Mitra dicabut',
            $partner,
            $actor,
            ['institution_name' => $partner->institution_name],
            null,
        );

        $partner->delete();
    }
}
