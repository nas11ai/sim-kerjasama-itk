<?php

namespace App\Queries\Dashboard;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Dashboard\DashboardSectionData;
use App\Data\View\Dashboard\KpiCardData;
use App\Data\View\Dashboard\PendingWorkData;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\Submitted;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SHARED-02-03/04 — section "Manajemen"; tiap kartu bergerbang permission-nya sendiri
 * (§4.4 K1). Angka pengajuan = baris yang juga tampil di OperatorSubmissionList (W42).
 *
 * @method static array{section: DashboardSectionData|null, pending: PendingWorkData|null} run(User $user)
 */
class ManagementDashboardCards implements ChangesNoState
{
    use AsAction;

    /** @return array{section: DashboardSectionData|null, pending: PendingWorkData|null} */
    public function handle(User $user): array
    {
        $awaitingReviewer = $user->can('submissions.view-all') ? $this->awaitingReviewer() : 0;
        $awaitingVerification = $user->can('users.verify') ? UserProfile::query()->awaitingVerification()->count() : 0;
        $cards = [
            ...$this->submissionCards($user, $awaitingReviewer),
            ...$this->verificationCards($user, $awaitingVerification),
            ...$this->exportCards($user),
            ...$this->userCards($user),
        ];

        return [
            'section' => $cards === [] ? null : new DashboardSectionData('management', 'Manajemen', $cards),
            'pending' => $this->pending($user, $awaitingReviewer, $awaitingVerification),
        ];
    }

    /** @return list<KpiCardData> */
    private function submissionCards(User $user, int $awaitingReviewer): array
    {
        if (!$user->can('submissions.view-all')) {
            return [];
        }

        return [
            new KpiCardData('active', 'Total Submission Aktif', strval($this->visibleToOperator()->active()->count()), 'Semua skema', route('admin.submissions.index')),
            new KpiCardData('awaitingReviewer', 'Perlu Penugasan Reviewer', strval($awaitingReviewer), 'Status Disubmit', route('admin.submissions.index', ['status' => Submitted::$name])),
        ];
    }

    /** @return list<KpiCardData> */
    private function verificationCards(User $user, int $awaitingVerification): array
    {
        if (!$user->can('users.verify')) {
            return [];
        }

        return [new KpiCardData('awaitingVerification', 'Perlu Verifikasi User', strval($awaitingVerification), 'Antrian NIDN', route('admin.verifications.index'))];
    }

    /**
     * BR-RPT-07 — hanya peminta yang diberi tahu ekspornya selesai, jadi hanya ekspor
     * miliknya yang dihitung.
     *
     * @return list<KpiCardData>
     */
    private function exportCards(User $user): array
    {
        if (!$user->can('reporting.export')) {
            return [];
        }

        $completed = ExportJob::query()
            ->where('requested_by', $user->id)
            ->where('status', ExportJob::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        return [new KpiCardData('exports', 'Export Selesai', strval($completed), 'Minggu ini', route('exports.index'))];
    }

    /** @return list<KpiCardData> */
    private function userCards(User $user): array
    {
        if (!$user->can('users.manage')) {
            return [];
        }

        // BR-IAM-05 — akun yang dinonaktifkan tidak dihapus, jadi disaring.
        $active = User::query()->where('is_active', true)->count();

        return [new KpiCardData('activeUsers', 'User Aktif', strval($active), 'Semua role', route('admin.users.index'))];
    }

    /** Banner antrian bagi yang menugaskan reviewer, hanya bila ada yang menunggu. */
    private function pending(User $user, int $awaitingReviewer, int $awaitingVerification): ?PendingWorkData
    {
        if (!$user->can('reviewers.assign') || $awaitingReviewer + $awaitingVerification === 0) {
            return null;
        }

        return new PendingWorkData($awaitingReviewer, $awaitingVerification);
    }

    private function awaitingReviewer(): int
    {
        return $this->visibleToOperator()->whereState('status', Submitted::class)->count();
    }

    /**
     * Draft tidak terlihat oleh Operator di mana pun; laporan monev anak bukan pengajuan.
     *
     * @return Builder<FormSubmission>
     */
    private function visibleToOperator(): Builder
    {
        return FormSubmission::query()->where('is_submitted', true)->whereNull('parent_submission_id');
    }
}
