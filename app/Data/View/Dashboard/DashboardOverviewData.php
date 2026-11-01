<?php

namespace App\Data\View\Dashboard;

use App\Data\View\Announcement\AnnouncementSummaryData;
use Spatie\LaravelData\Data;

/** Props `Dashboard/IndexPage` — satu dasbor untuk semua peran (§4.4 K4, W45). */
final class DashboardOverviewData extends Data
{
    /**
     * @param  list<DashboardSectionData>  $sections
     * @param  list<ActivityData>  $activities
     * @param  list<DeadlineData>  $deadlines
     * @param  list<AnnouncementSummaryData>  $announcements
     */
    public function __construct(
        public ?string $subtitle,
        public array $sections,
        public ?PendingWorkData $pending,
        public array $activities,
        public array $deadlines,
        public array $announcements,
    ) {}
}
