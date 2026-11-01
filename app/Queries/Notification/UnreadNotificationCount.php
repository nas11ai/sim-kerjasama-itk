<?php

namespace App\Queries\Notification;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-NOT-05, W41 — badge "belum dibaca" di-cache per user, karena ia dibaca di
 * setiap request Inertia. Cache dilupakan di setiap titik yang mengubah
 * angkanya: notifikasi `database` terkirim, dibaca, dibaca semua, dihapus.
 *
 * @method static int run(User $user)
 */
class UnreadNotificationCount implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): int
    {
        return Cache::rememberForever(self::key($user), fn (): int => $user->unreadNotifications()->count());
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    private static function key(User $user): string
    {
        return "notifications.unread.{$user->id}";
    }
}
