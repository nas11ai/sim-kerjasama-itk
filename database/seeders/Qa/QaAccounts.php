<?php

namespace Database\Seeders\Qa;

use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\CompleteProfile;
use App\Actions\Identity\IssueInvitation;
use App\Actions\Identity\RejectNidnVerification;
use App\Actions\Identity\RevokeInvitation;
use App\Actions\Identity\VerifyNidn;
use App\Data\Request\ProfileDetailsInput;
use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Carbon;

/**
 * Akun satu set penguji, lahir di awal tahun lalu (sebelum riwayat pengajuannya).
 *
 * Peneliti tidak lewat `RegisterResearcher`: BR-IAM-01 hanya menerima `@itk.ac.id`, sedangkan akun uji
 * sengaja ber-domain `.test` supaya tidak ada email yang sampai ke orang sungguhan. Sisanya
 * (verifikasi NIDN, penolakan, kelengkapan profil, penunjukan penilai, undangan) lewat Action asli.
 */
final class QaAccounts
{
    public const DOMAIN = 'simpas.test';

    public static function build(int $k): QaSet
    {
        return QaClock::at(Carbon::create(now()->year - 1, 1, 6, 9), fn (): QaSet => self::accounts($k));
    }

    /** Peneliti latar (volume institusi) memakai jalur yang sama dengan peneliti set. */
    public static function researcher(string $local, string $name, Organization $unit, string $nidn): User
    {
        $user = self::register($local, $name, $unit, $nidn);
        VerifyNidn::run($user->userProfile);
        CompleteProfile::run($user->refresh(), new ProfileDetailsInput(
            '08'.substr($nidn, -10),
            fake()->randomElement(['Rekayasa Material', 'Sistem Informasi', 'Energi Terbarukan', 'Teknik Sipil', 'Matematika Terapan', 'Kimia Lingkungan']),
        ), QaFiles::photo());

        return $user->refresh();
    }

    /** Penilai beserta masa tugas aktif sampai akhir tahun depan (BR-REV-09). */
    public static function reviewer(string $local, string $name, string $type): Reviewer
    {
        $user = self::staff($local, $name, Organization::where('type', 'institution')->sole());

        return AppointReviewer::run($user, $type, now()->toDateString(), Carbon::create(now()->year + 2, 12, 31)->toDateString());
    }

    public static function operator(string $local, string $name): User
    {
        $operator = self::staff($local, $name, Organization::where('type', 'institution')->sole());
        $operator->assignRole('operator');

        return $operator;
    }

    private static function accounts(int $k): QaSet
    {
        $tag = "QA{$k}";
        $units = self::units($k);
        $nidn = fn (int $n): string => sprintf('%010d', 7_100_000_000 + $k * 1_000 + $n);

        $operator = self::operator("qa{$k}-operator", "{$tag} Operator LPPM");
        $reviewers = [
            self::reviewer("qa{$k}-penilai1", "{$tag} ".self::name(), 'internal'),
            self::reviewer("qa{$k}-penilai2", "{$tag} ".self::name(), 'internal'),
        ];
        $external = self::reviewer("qa{$k}-penilai-luar", "{$tag} ".self::name(), 'external');

        $researchers = [];
        foreach (range(1, 12) as $n) {
            $researchers[$n] = self::researcher(sprintf('qa%d-peneliti%02d', $k, $n), "{$tag} ".self::name(), $units[$n % count($units)], $nidn($n));
        }

        $member = self::researcher("qa{$k}-anggota", "{$tag} ".self::name(), $units[0], $nidn(20));
        $pending = self::register("qa{$k}-peneliti-baru", "{$tag} ".self::name(), $units[1], $nidn(21));
        $rejected = self::register("qa{$k}-peneliti-ditolak", "{$tag} ".self::name(), $units[2], $nidn(22));
        RejectNidnVerification::run($rejected->userProfile, 'NIDN tidak cocok dengan data PDDikti. Periksa kembali angka NIDN Anda.');

        // Undangan mitra eksternal: satu aktif, satu dicabut.
        $kept = IssueInvitation::run($operator, $units[0], ['max_uses' => 5]);
        RevokeInvitation::run($operator, IssueInvitation::run($operator, $units[1], ['max_uses' => 1]));

        return new QaSet($k, $operator, $reviewers, $external, $researchers, $pending->refresh(), $rejected->refresh(), $member);
    }

    /** Langkah `RegisterResearcher::createAccount` apa adanya: akun peneliti dengan profil `pending`. */
    private static function register(string $local, string $name, Organization $unit, string $nidn): User
    {
        $email = "{$local}@".self::DOMAIN;
        $user = User::create([
            'name' => $name,
            'email' => $email,
            // Kata sandi = email. Hanya untuk data uji.
            'password' => bcrypt($email),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('researcher');

        UserProfile::create([
            'user_id' => $user->id,
            'organization_id' => $unit->id,
            'nidn' => $nidn,
            'verification_status' => VerificationStatus::Pending,
        ]);

        return $user;
    }

    /** Staf LPPM dan penilai: tidak lewat registrasi mandiri, profilnya aktif di Institusi. */
    private static function staff(string $local, string $name, Organization $unit): User
    {
        $email = "{$local}@".self::DOMAIN;
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($email),
            'email_verified_at' => now(),
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'organization_id' => $unit->id,
            'verification_status' => VerificationStatus::Active,
        ]);

        return $user;
    }

    /**
     * Prodi berbeda per set, tersebar lintas fakultas.
     *
     * @return list<Organization>
     */
    private static function units(int $k): array
    {
        $prodi = Organization::where('type', 'study_program')->whereNotNull('parent_id')->orderBy('id')->get()->all();
        $rotated = [...array_slice($prodi, $k * 3), ...array_slice($prodi, 0, $k * 3)];

        return array_values(array_filter($rotated, fn (int $i): bool => $i % 2 === 0, ARRAY_FILTER_USE_KEY));
    }

    /** Nama orang rekaan dengan gelar akademik. */
    private static function name(): string
    {
        return fake()->randomElement(['Dr. ', 'Dr. Ir. ', '', '']).fake()->firstName().' '.fake()->lastName().fake()->randomElement([', M.T.', ', M.Sc.', ', M.Kom.', ', Ph.D.']);
    }
}
