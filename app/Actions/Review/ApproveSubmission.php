<?php

namespace App\Actions\Review;

use App\Actions\Concerns\SettlesThroughUnderReview;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Notifications\ProposalApprovedNotification;
use App\Queries\Review\DecisionReadiness;
use App\States\Submission\Approved;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-05, ADR 0018 — **satu-satunya** jalur menuju APPROVED: aksi Setujui Operator atas
 * pengajuan yang siap diputuskan dan tanpa catatan reviewer terbuka. Cermin RejectSubmission.
 *
 * @method static FormSubmission run(FormSubmission $submission, User $operator)
 */
class ApproveSubmission
{
    use AsAction;
    use SettlesThroughUnderReview;

    /** BR-RPT-09 — deskripsi audit persetujuan, beserta Operator yang memutuskan. */
    public const AUDIT_DESCRIPTION = 'Pengajuan disetujui';

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(FormSubmission $submission, User $operator): FormSubmission
    {
        // Satu hasil kesiapan untuk Policy dan pemeriksaan ulang: Super Admin lolos Policy lewat
        // Gate::before, jadi syaratnya dibaca ulang di sini (ADR 0015).
        $readiness = DecisionReadiness::run($submission);

        if (!$operator->can('approve', [$submission, $readiness])) {
            throw new AuthorizationException(
                'Persetujuan hanya dapat dilakukan Operator atas pengajuan yang siap diputuskan.'
            );
        }

        if (!$readiness->approvable) {
            throw ValidationException::withMessages([
                'approve' => $readiness->blockers[0] ?? 'Pengajuan ini tidak sedang menunggu keputusan.',
            ]);
        }

        $this->settleInto($submission, Approved::class);
        $tercatat = $submission->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record(self::AUDIT_DESCRIPTION, $tercatat, $operator);

        Deliver::to($this->team($tercatat), new ProposalApprovedNotification($tercatat));

        return $tercatat;
    }

    /**
     * Ketua dan anggota aktif yang punya akun (tabel notifikasi `ddd/generic/03_notification.md`).
     *
     * @return Collection<int, User>
     */
    private function team(FormSubmission $submission): Collection
    {
        $members = $submission->researchMembers()->where('status', 'active')->with('userProfile.user')->get()
            ->map(fn (ResearchMember $member): ?User => $member->userProfile->user)
            ->filter();

        return collect([$submission->submittedBy])->concat($members)->unique('id')->values();
    }
}
