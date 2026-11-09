<?php

namespace App\Support\Identity;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * BR-IAM-21, W60 — satu-satunya pemilik tabel `sessions` (driver `database`, konfigurasi
 * produksi). Di sini hanya dibaca; penghapusan dilakukan Action beraudit lewat `of()`.
 * Sumber kehadiran tetap di sini (W168); RT1 hanya mendorong `session.active`.
 */
final class SessionRegistry
{
    /** BR-IAM-21 — "sedang aktif" = ada aktivitas sesi dalam jendela ini. */
    public const ONLINE_WINDOW_MINUTES = 5;

    private const TABLE = 'sessions';

    /** Baris sesi milik user; Action beraudit yang menghapusnya (Support tidak menulis). */
    public static function of(User $user): Builder
    {
        return DB::table(self::TABLE)->where('user_id', $user->id);
    }

    /**
     * Aktivitas sesi terakhir per user, satu query untuk satu halaman daftar.
     *
     * @param  list<int>  $userIds
     * @return array<int, CarbonImmutable>
     */
    public static function lastActiveAtFor(array $userIds): array
    {
        $rows = DB::table(self::TABLE)
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) AS last_activity')
            ->get();

        $lastActive = [];
        foreach ($rows as $row) {
            if (is_int($row->user_id) && is_int($row->last_activity)) {
                $lastActive[$row->user_id] = CarbonImmutable::createFromTimestamp($row->last_activity, date_default_timezone_get());
            }
        }

        return $lastActive;
    }

    public static function lastActiveAt(User $user): ?CarbonImmutable
    {
        return self::lastActiveAtFor([$user->id])[$user->id] ?? null;
    }

    public static function isOnline(?CarbonImmutable $lastActiveAt): bool
    {
        return $lastActiveAt !== null && $lastActiveAt->greaterThanOrEqualTo(self::onlineSince());
    }

    /** Subquery `user_id` yang sedang aktif, untuk filter daftar user. */
    public static function onlineUserIds(): Builder
    {
        return DB::table(self::TABLE)
            ->select('user_id')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', self::onlineSince()->getTimestamp());
    }

    private static function onlineSince(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes(self::ONLINE_WINDOW_MINUTES);
    }
}
