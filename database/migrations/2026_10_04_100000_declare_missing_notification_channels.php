<?php

use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BR-NOT-06 — deklarasi kanal untuk event katalog yang belum punya baris.
 *
 * Seeder hanya berjalan sekali di database yang sudah hidup. Event yang
 * ditambahkan ke katalog sesudahnya (RevisionResolved) tidak punya deklarasi,
 * sehingga pengirimannya ditolak dan Deliver melempar ulang — permintaan
 * menjawab 500 setelah state berubah. Migration ini menutup celah itu.
 *
 * Default-nya diambil dari `NotificationEventCatalog::defaults()`, sumber yang
 * sama dengan `NotificationChannelSeeder`, bukan disalin. `insertOrIgnore`
 * (ON CONFLICT DO NOTHING atas `event_type` yang unik) hanya mengisi yang
 * belum ada: baris yang sudah disunting Admin tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = collect(NotificationEventCatalog::defaults())
            ->map(fn (array $channels, string $eventType) => [
                'event_type' => $eventType,
                'channels' => json_encode($channels),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table('notification_channel_settings')->insertOrIgnore($rows);

        NotificationChannels::forget();
    }

    /**
     * Sengaja kosong. Migration ini tidak bisa membedakan baris yang ia
     * sisipkan dari baris yang sudah ada atau sudah disunting Admin sesudahnya;
     * menghapusnya akan mencabut deklarasi yang sah dan membuat pengiriman
     * kembali ditolak (BR-NOT-06).
     */
    public function down(): void {}
};
