<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\SubmissionTargetOutput;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-19 — menghapus target luaran.
 *
 * Gerbangnya sama persis dengan penyuntingannya (BR-SM-15): target adalah janji ketua pengaju, dan
 * janji yang bisa diubah harus bisa dicabut — satu salah pilih tipe terbawa sampai pelaporan.
 *
 * @method static void run(FormSubmission $submission, SubmissionTargetOutput $target, User $user)
 */
class DeleteTargetOutput
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, SubmissionTargetOutput $target, User $user): void
    {
        if (!$user->can('manageTargetOutput', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat menghapus target luaran, dan hanya selama pengajuan belum ditolak atau ditarik.'
            );
        }

        if ($target->form_submission_id !== $submission->id) {
            throw ValidationException::withMessages([
                'id' => 'Target luaran ini bukan milik pengajuan tersebut.',
            ]);
        }

        AuditTrail::record(
            'Target luaran dihapus',
            $target,
            $user,
            new AuditChange(
                old: ['output_type' => $target->output_type],
            ),
        );

        // Berbeda dari luaran realisasi yang tidak pernah dihapus (BR-RO-06): yang
        // dijanjikan boleh berubah, yang sudah terjadi tidak.
        $target->delete();
    }
}
