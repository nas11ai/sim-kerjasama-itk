<?php

namespace Database\Seeders;

use App\Enums\VerificationStatus;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\Qa\QaAccounts;
use Database\Seeders\Qa\QaBackground;
use Database\Seeders\Qa\QaCatalog;
use Database\Seeders\Qa\QaClock;
use Database\Seeders\Qa\QaHistory;
use Database\Seeders\Qa\QaScenarios;
use Database\Seeders\Qa\QaSet;
use Database\Seeders\Qa\QaSubmissionFlow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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
        fake('id_ID')->seed(20261114);

        // Admin lahir bersama katalog, di awal tahun lalu, supaya log audit tidak berisi pelaku yang "belum terdaftar".
        $admin = QaClock::at(Carbon::create(now()->year - 1, 1, 5, 8), fn (): User => $this->admin());
        $catalog = QaCatalog::build($admin);
        $sets = array_map(QaAccounts::build(...), range(1, self::SETS));
        $flow = new QaSubmissionFlow($catalog);

        // Riwayat lebih dulu: draf tahun ini harus tetap baris terbaru wizard peneliti yang sama.
        foreach ($sets as $set) {
            QaHistory::build($set, $flow);
            QaScenarios::build($set, $flow);
        }

        QaBackground::build($flow);

        $this->summary($sets);
    }

    /** @param  list<QaSet>  $sets */
    private function summary(array $sets): void
    {
        $rows = [['-', self::ADMIN_EMAIL, 'Admin']];
        foreach ($sets as $set) {
            $rows[] = [$set->tag(), $set->operator->email, 'Operator'];
            $rows[] = [$set->tag(), "qa{$set->k}-penilai1, -penilai2, -penilai-luar", 'Penilai'];
            $rows[] = [$set->tag(), "qa{$set->k}-peneliti01 … -peneliti12", 'Peneliti (nomor = skenario)'];
        }

        $this->command?->info('Data uji QA selesai. Kata sandi setiap akun = alamat emailnya.');
        $this->command?->table(['Set', 'Akun', 'Peran'], $rows);
        $this->command?->table(['Status', 'Jumlah pengajuan'], FormSubmission::query()
            ->whereNull('parent_submission_id')
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (FormSubmission $row): array => [class_basename((string) $row->getRawOriginal('status')), (string) $row->getAttribute('jumlah')])
            ->all());
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
