<?php

namespace App\Support\Notification;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * BR-NOT-03 — pengiriman notifikasi bersifat fire-and-forget.
 *
 * Aksi bisnisnya sudah selesai dan tersimpan sebelum pemberitahuan dikirim.
 * Kalau kanal email mati, menariknya kembali berarti menghukum peneliti atas
 * kegagalan yang tidak bisa ia perbaiki — dan pengajuan yang sudah tersimpan
 * akan dilaporkan gagal padahal ada di database.
 *
 * Yang tidak boleh terjadi adalah gagal **tanpa suara**. Setiap kegagalan
 * dicatat lengkap dengan tipe event dan penerimanya, supaya "kenapa saya
 * tidak dapat email" punya jawaban yang bisa dicari.
 *
 * `ChannelNotDeclared` sengaja **tidak** ditangkap: itu bukan kanal yang
 * sedang mati melainkan deklarasi yang belum dibuat (BR-NOT-06), dan
 * mendiamkannya membuat kelalaian konfigurasi menjadi notifikasi yang
 * diam-diam tidak pernah terkirim.
 */
final class Deliver
{
    /**
     * `null` diterima dan tidak melakukan apa-apa: pemanggil yang dulu menulis
     * `$profile->user?->notify(...)` memang bermaksud "kirim kalau ada
     * orangnya", dan memindahkan penjagaan itu ke sini membuat setiap
     * pemanggil tidak perlu mengulanginya.
     *
     * `$context` diisi pemanggil dengan apa yang hanya ia tahu — id period,
     * id pengajuan, jumlah penerima. Tanpa itu catatan kegagalannya benar
     * tapi tidak bisa ditelusuri ke kejadian mana pun.
     *
     * Mengembalikan apakah pengiriman berhasil — tanpa melempar. Pemanggil
     * yang punya kebijakan sendiri atas kegagalan (mis. penjadwal yang tidak
     * ingin menandai sebuah period sudah diberitahukan padahal gagal) bisa
     * membacanya; yang tidak peduli tinggal mengabaikannya. Itu berbeda dari
     * melempar: melempar memaksa **setiap** pemanggil mengurusnya, dan di
     * situlah aksi bisnis mulai ikut gagal.
     *
     * @param  iterable<object>|object|null  $notifiables
     * @param  array<string, int|string|null>  $context
     */
    public static function to(iterable|object|null $notifiables, Notification $notification, array $context = []): bool
    {
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
            throw $undeclared;
        } catch (Throwable $failure) {
            Log::error('Pengiriman notifikasi gagal — proses bisnis tetap dilanjutkan (BR-NOT-03)', $context + [
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
