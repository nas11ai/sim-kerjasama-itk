<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Announcement\AnnouncementSummaryData;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar `Announcement/IndexPage`: yang terlihat oleh pembaca (VisibleAnnouncements),
 * terbaru dulu, dengan penanda baca miliknya.
 *
 * @method static LengthAwarePaginator<int, AnnouncementSummaryData> run(User $reader)
 */
class ReaderAnnouncementList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 10;

    /** @return LengthAwarePaginator<int, AnnouncementSummaryData> */
    public function handle(User $reader): LengthAwarePaginator
    {
        return self::newestFor($reader)
            ->paginate(self::PER_PAGE)
            ->through(self::summary(...));
    }

    /**
     * Urutan dan penanda baca yang sama untuk daftar ini dan "Pengumuman terbaru" dasbor.
     *
     * @return Builder<Announcement>
     */
    public static function newestFor(User $reader): Builder
    {
        return VisibleAnnouncements::run($reader)
            ->withCount('announcementFiles')
            ->withExists(['announcementReader as is_read' => static fn (Builder $query) => $query->whereKey($reader->id)])
            ->latest()
            ->orderByDesc('id');
    }

    public static function summary(Announcement $announcement): AnnouncementSummaryData
    {
        return AnnouncementSummaryData::of($announcement, $announcement->getAttribute('is_read') === true);
    }
}
