<?php

namespace App\Queries\Dashboard;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Dashboard\DashboardOverviewData;
use App\Data\View\Dashboard\DashboardSectionData;
use App\Data\View\Dashboard\ProfileCompletionData;
use App\Models\Reviewer;
use App\Models\User;
use App\Queries\Announcement\LatestAnnouncements;
use App\Queries\Submission\ResearcherStudyProgram;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SHARED-02-01..04 — satu dasbor untuk semua peran (§4.4 K4). Tiap section bergerbang
 * permission, sehingga user dua peran melihat gabungannya; nama peran tak pernah dibaca.
 *
 * @method static DashboardOverviewData run(User $user)
 */
class DashboardOverview implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): DashboardOverviewData
    {
        $sections = [];
        $reviewerDeadlines = [];

        if ($user->can('submissions.view-own')) {
            $sections[] = ResearcherDashboardCards::run($user);
        }

        $reviewer = $this->reviewer($user);

        if ($reviewer !== null) {
            $review = ReviewerDashboardCards::run($reviewer);
            $sections[] = $review['section'];
            $reviewerDeadlines = $review['deadlines'];
        }

        ['section' => $management, 'pending' => $pending] = ManagementDashboardCards::run($user);

        return new DashboardOverviewData(
            subtitle: $this->subtitle($user),
            sections: $this->withManagement($sections, $management),
            pending: $pending,
            activities: RecentActivity::run($user),
            deadlines: DashboardDeadlines::run($user, $reviewerDeadlines),
            announcements: LatestAnnouncements::run($user),
            profileCompletion: ProfileCompletionData::of($user),
        );
    }

    /** Permission tanpa baris Reviewer berarti belum ada penugasan yang bisa dibaca. */
    private function reviewer(User $user): ?Reviewer
    {
        if (!$user->can('reviewers.evaluate')) {
            return null;
        }

        return Reviewer::query()->where('user_id', $user->id)->first();
    }

    /**
     * @param  list<DashboardSectionData>  $sections
     * @return list<DashboardSectionData>
     */
    private function withManagement(array $sections, ?DashboardSectionData $management): array
    {
        return $management === null ? $sections : [...$sections, $management];
    }

    private function subtitle(User $user): ?string
    {
        $program = ResearcherStudyProgram::run($user);

        if ($program === null) {
            return null;
        }

        return $program['faculty']['name'] === ''
            ? $program['name']
            : "{$program['faculty']['name']} — {$program['name']}";
    }
}
