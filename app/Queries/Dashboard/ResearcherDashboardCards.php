<?php

namespace App\Queries\Dashboard;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Dashboard\DashboardSectionData;
use App\Data\View\Dashboard\KpiCardData;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Notification\UnreadNotificationCount;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SHARED-02-01 — section "Pengajuan Saya". Hanya predikat yang sudah ada: pengajuan
 * terlihat (BR-SM-07, anggota tim ikut) dan Active Submission (bukan REJECTED/WITHDRAWN).
 *
 * @method static DashboardSectionData run(User $user)
 */
class ResearcherDashboardCards implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): DashboardSectionData
    {
        $list = route('user.submissions.index');
        $underReview = $this->mine($user)->whereState('status', UnderReview::class)->count();

        return new DashboardSectionData('researcher', 'Pengajuan Saya', [
            new KpiCardData('active', 'Submission Aktif', strval($this->mine($user)->active()->count()), "{$underReview} dalam review", $list),
            new KpiCardData('revision', 'Dalam Revisi', strval($this->inState($user, NeedsRevision::class)), 'Butuh respon', $list),
            new KpiCardData('approved', 'Disetujui', strval($this->inState($user, Approved::class)), 'Total selesai', $list),
            new KpiCardData('notifications', 'Notifikasi', strval(UnreadNotificationCount::run($user)), 'Belum dibaca', route('notifications.index')),
        ]);
    }

    /**
     * Laporan monev anak bukan pengajuan tersendiri.
     *
     * @return Builder<FormSubmission>
     */
    private function mine(User $user): Builder
    {
        return SubmissionVisibility::ownedOrJoinedBy($user)->whereNull('parent_submission_id');
    }

    private function inState(User $user, string $state): int
    {
        return $this->mine($user)->whereState('status', $state)->count();
    }
}
