<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-09, kanonik BR-IAM-10 — memindahkan kepemilikan pengajuan.
 */
class TransferSubmissionOwnership
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormSubmission $submission, User $successor, string $reason): FormSubmission
    {
        $this->guardSuccessor($submission, $successor);

        $tercatat = DB::transaction(function () use ($submission, $successor): FormSubmission {
            $previousOwner = $submission->submitted_by;

            $submission->update(['submitted_by' => $successor->id]);

            // Penerima berhenti jadi anggota — ia kini ketua.
            $submission->researchMembers()
                ->whereHas('userProfile', fn ($q) => $q->where('user_id', $successor->id))
                ->update(['role' => ResearchMember::ROLE_CO_INVESTIGATOR]);

            $this->keepPreviousOwnerAsMember($submission, $previousOwner);

            return $submission->refresh();
        });

        // BR-RPT-09 — satu Action, satu entri audit; alasannya ikut di entri itu (W26).
        AuditTrail::record('Kepemilikan pengajuan dialihkan', $tercatat, change: new AuditChange(new: ['reason' => $reason]));

        return $tercatat;
    }

    /**
     * Penerimanya wajib `co_investigator` yang **aktif**: member biasa tidak pernah
     * memegang tanggung jawab pengajuan (BR-BUD-09, BR-SM-15), jadi memindahkan ke
     * sana hanya memindahkan masalahnya.
     *
     * @throws ValidationException
     */
    private function guardSuccessor(FormSubmission $submission, User $successor): void
    {
        $eligible = $submission->researchMembers()
            ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
            ->where('status', 'active')
            ->whereHas('userProfile', fn ($q) => $q->where('user_id', $successor->id))
            ->exists();

        if ($eligible) {
            return;
        }

        throw ValidationException::withMessages([
            'successor_id' => 'Kepemilikan hanya dapat dipindahkan ke co_investigator yang masih aktif pada pengajuan ini.',
        ]);
    }

    /**
     * Menghapusnya akan menghilangkan jejak siapa
     * yang sesungguhnya mengerjakan penelitian ini.
     */
    private function keepPreviousOwnerAsMember(FormSubmission $submission, int $previousOwner): void
    {
        $profile = User::find($previousOwner)?->userProfile;

        if ($profile === null) {
            return;
        }

        $submission->researchMembers()->updateOrCreate(
            ['user_profile_id' => $profile->id],
            ['role' => ResearchMember::ROLE_CO_INVESTIGATOR, 'status' => 'active']
        );
    }
}
