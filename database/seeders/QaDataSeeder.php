<?php

namespace Database\Seeders;

use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data uji QA: empat set per penguji (`qa1`–`qa4`) plus volume institusi, seluruhnya lewat Action asli.
 * Desain: docs/design/2026-11-13-seeder-data-uji-qa-design.md. Cara pakai: docs/qa/data-uji.md.
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=QaDataSeeder
 *
 * Sengaja tidak idempoten dan tanpa transaksi luar: reset selalu lewat database segar, dan
 * `qa-admin` dibuat paling awal sehingga run yang gagal di tengah tetap ditolak saat diulang.
 */
class QaDataSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'qa-admin@simpas.test';

    public const SETS = 4;

    public function run(): void
    {
        $this->guard();

        // Notifikasi ber-antrean terkirim saat itu juga, dengan jam yang sedang dimundurkan, dan tidak
        // meninggalkan ratusan job basi. Broadcast tidak butuh Reverb yang hidup.
        config(['queue.default' => 'sync', 'broadcasting.default' => 'log']);
        fake()->seed(20261114);

        $this->admin();
    }

    private function guard(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('QaDataSeeder tidak boleh dijalankan di production.');
        }

        if (User::where('email', self::ADMIN_EMAIL)->exists()) {
            throw new RuntimeException('Data uji QA sudah ada. Jalankan `php artisan migrate:fresh --seed` dulu, lalu seeder ini.');
        }
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'QA Admin',
            'email' => self::ADMIN_EMAIL,
            'password' => bcrypt(self::ADMIN_EMAIL),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        UserProfile::create([
            'user_id' => $admin->id,
            'organization_id' => Organization::where('type', 'institution')->sole()->id,
            'verification_status' => VerificationStatus::Active,
        ]);

        return $admin;
    }
}
