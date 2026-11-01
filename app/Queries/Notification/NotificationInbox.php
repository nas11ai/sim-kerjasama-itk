<?php

namespace App\Queries\Notification;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Notification\NotificationRowData;
use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SHARED-02-05 — kotak masuk notifikasi pemiliknya, terbaru dulu. Membacanya
 * tidak mengisi `read_at` (BR-NOT-04): penanda baca hanya lewat "Buka".
 *
 * @method static array{notifications: LengthAwarePaginator<int, NotificationRowData>, filter: string, unreadCount: int} run(User $user, string $filter)
 */
class NotificationInbox implements ChangesNoState
{
    use AsAction;

    public const PER_PAGE = 20;

    /** W40 — chip wireframe; nilai lain dari URL dibaca sebagai `all`. */
    private const FILTERS = ['all', 'unread', 'review', 'monev'];

    /** @return array{notifications: LengthAwarePaginator<int, NotificationRowData>, filter: string, unreadCount: int} */
    public function handle(User $user, string $filter): array
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';

        $notifications = $user->notifications()
            ->tap(fn (Builder $query) => $this->narrow($query, $filter))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(static fn (DatabaseNotification $notification): NotificationRowData => NotificationRowData::of($notification));

        return [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => UnreadNotificationCount::run($user),
        ];
    }

    /** @param Builder<DatabaseNotification> $query */
    private function narrow(Builder $query, string $filter): void
    {
        match ($filter) {
            'unread' => $query->whereNull('read_at'),
            'review' => $query->whereIn('type', NotificationCategory::Review->classes()),
            'monev' => $query->whereIn('type', NotificationCategory::Monev->classes()),
            default => null,
        };
    }
}
