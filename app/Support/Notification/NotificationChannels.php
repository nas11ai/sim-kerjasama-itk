<?php

namespace App\Support\Notification;

use App\Models\NotificationChannelSetting;

/**
 * BR-NOT-06 — channel mana yang dipakai satu tipe event.
 *
 * Nilainya dibaca dari tabel, bukan dari konstanta kelas, supaya LPPM bisa
 * memindahkan sebuah peristiwa dari "cukup in-app" ke "sekalian email" tanpa
 * menunggu rilis. Yang tidak boleh berubah adalah kewajiban mendeklarasikan:
 * event tanpa deklarasi ditolak saat dipublish, bukan diam-diam diberi
 * default.
 */
final class NotificationChannels
{
    public const IN_APP = 'database';

    public const EMAIL = 'mail';

    /** @var array<string, array<int, string>>|null */
    private static ?array $cache = null;

    /**
     * @return array<int, string>
     *
     * @throws ChannelNotDeclared
     */
    public static function for(string $eventType): array
    {
        $channels = self::all()[$eventType] ?? null;

        if ($channels === null) {
            throw ChannelNotDeclared::for($eventType);
        }

        return $channels;
    }

    public static function declared(string $eventType): bool
    {
        return array_key_exists($eventType, self::all());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $settings = [];

        foreach (NotificationChannelSetting::query()->get() as $setting) {
            $settings[$setting->event_type] = $setting->channels;
        }

        return self::$cache = $settings;
    }

    /**
     * Dipanggil setelah deklarasi disunting. Cache-nya per-request, jadi ini
     * hanya penting dalam satu permintaan yang menyunting lalu mengirim —
     * termasuk test.
     */
    public static function forget(): void
    {
        self::$cache = null;
    }
}
