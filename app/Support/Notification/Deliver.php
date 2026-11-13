<?php

namespace App\Support\Notification;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * BR-NOT-03 — pengiriman notifikasi bersifat fire-and-forget: aksi bisnisnya
 * sudah tersimpan sebelum pemberitahuan dikirim. Menariknya kembali menghukum
 * peneliti atas kanal yang mati, dan melaporkan gagal apa yang sudah ada.
 */
final class Deliver
{
    /**
     * Mengembalikan apakah pengiriman berhasil, tanpa melempar — penjadwal
     * yang tak mau menandai period "sudah diberitahukan" padahal gagal bisa
     * membacanya. Melempar memaksa **setiap** pemanggil ikut gagal bersamanya.
     *
     * @param  iterable<object>|object|null  $notifiables
     * @param  array<string, int|string|null>  $context
     */
    public static function to(iterable|object|null $notifiables, Notification $notification, array $context = []): bool
    {
        // `null` diterima dan diam: pemanggil yang dulu menulis
        // `$profile->user?->notify(...)` bermaksud "kirim kalau ada orangnya",
        // dan penjagaan itu cukup ditulis sekali di sini.
        if ($notifiables === null) {
            return false;
        }

        try {
            if (is_iterable($notifiables)) {
                Notifier::send($notifiables, $notification);

                return true;
            }

            Notifier::sendNow([$notifiables], $notification);

            return true;
        } catch (ChannelNotDeclared $undeclared) {
            // BR-NOT-06 — dilempar ulang, bukan ditelan: ini bukan kanal yang
            // sedang mati melainkan deklarasi yang belum dibuat, dan
            // mendiamkannya membuat notifikasi diam-diam tak pernah terkirim.
            throw $undeclared;
        } catch (Throwable $failure) {
            // Gagal boleh, gagal tanpa suara tidak. `$context` dari pemanggil —
            // id period, id pengajuan — melengkapi catatan yang tanpa itu benar
            // tapi tak bisa ditelusuri ke kejadian mana pun.
            Log::error('Pengiriman notifikasi gagal, proses bisnis tetap dilanjutkan (BR-NOT-03)', $context + [
                'notification' => $notification::class,
                'event_type' => defined($notification::class.'::EVENT_TYPE')
                    ? constant($notification::class.'::EVENT_TYPE')
                    : null,
                'error' => $failure->getMessage(),
            ]);

            return false;
        }
    }
}
