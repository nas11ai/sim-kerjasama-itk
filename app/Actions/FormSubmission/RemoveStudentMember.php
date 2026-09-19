<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\StudentMember;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-20 — mengeluarkan anggota mahasiswa.
 *
 * Pasangan AddStudentMember, dibuat bersamaan dengannya karena alasan yang
 * sama dengan BR-SM-18: batas yang tidak punya jalan keluar adalah jalan
 * buntu, bukan batas.
 *
 * @method static void run(FormSubmission $submission, StudentMember $member, User $actor)
 */
class RemoveStudentMember
{
    use AsAction;

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, StudentMember $member, User $actor): void
    {
        if (!$actor->can('manageResearchMembers', $submission)) {
            throw new AuthorizationException(
                'Hanya ketua pengaju yang dapat mengatur anggota mahasiswa, dan hanya selama pengajuan belum disetujui, ditolak, atau ditarik.'
            );
        }

        if ($member->form_submission_id !== $submission->id) {
            throw ValidationException::withMessages([
                'id' => 'Mahasiswa ini bukan bagian dari pengajuan tersebut.',
            ]);
        }

        AuditTrail::record(
            'Anggota mahasiswa dikeluarkan',
            $member,
            $actor,
            ['name' => $member->name, 'student_id' => $member->student_id],
            null,
        );

        $member->delete();
    }
}
