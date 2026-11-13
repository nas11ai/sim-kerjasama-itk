<?php

namespace App\Support\Identity;

use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * W4 — label manusia untuk setiap permission `PermissionSeeder::PERMISSIONS`
 * (IdentityCatalogTest menjaga keduanya tetap sama).
 */
final class PermissionCatalog
{
    /** @var array<string, string> */
    public const LABELS = [
        'submissions.create' => 'Membuat pengajuan',
        'submissions.view-own' => 'Melihat pengajuan sendiri',
        'submissions.view-all' => 'Melihat semua pengajuan',
        'submissions.view-assigned' => 'Melihat pengajuan yang ditugaskan',
        'budget.edit' => 'Menyunting anggaran',
        'members.manage' => 'Mengelola anggota',
        'reviewers.assign' => 'Menugaskan penilai',
        'reviewers.evaluate' => 'Menilai pengajuan',
        'reviewers.view-scores-others' => 'Melihat skor penilai lain',
        'periods.manage' => 'Mengelola periode',
        'schemes.manage' => 'Mengelola skema & formulir',
        'outputs.manage' => 'Mengelola luaran',
        'users.verify' => 'Memverifikasi NIDN',
        'users.manage' => 'Mengelola pengguna & hak akses',
        'organizations.manage' => 'Mengelola struktur organisasi',
        'reporting.export' => 'Mengekspor laporan',
        'reporting.view-audit-log' => 'Melihat log audit',
        'announcements.manage' => 'Mengelola pengumuman',
    ];

    /**
     * K5 (2026-11-13) — penjelasan satu kalimat untuk tooltip, di samping label pendeknya.
     *
     * @var array<string, string>
     */
    public const DESCRIPTIONS = [
        'submissions.create' => 'Membuat dan mengirim pengajuan baru.',
        'submissions.view-own' => 'Melihat pengajuan yang dibuat sendiri atau yang diikuti sebagai anggota.',
        'submissions.view-all' => 'Melihat seluruh pengajuan dari semua unit.',
        'submissions.view-assigned' => 'Melihat pengajuan yang ditugaskan kepadanya untuk dinilai.',
        'budget.edit' => 'Menyunting rencana anggaran pengajuan yang dikelolanya.',
        'members.manage' => 'Menambah dan menghapus anggota tim pengajuan yang dikelolanya.',
        'reviewers.assign' => 'Menugaskan dan mengganti penilai sebuah pengajuan.',
        'reviewers.evaluate' => 'Mengisi formulir evaluasi untuk pengajuan yang ditugaskan.',
        'reviewers.view-scores-others' => 'Melihat skor yang diberikan penilai lain pada pengajuan yang sama.',
        'periods.manage' => 'Membuat periode pengajuan dan mengatur jadwalnya.',
        'schemes.manage' => 'Mengelola skema, pembuat formulir, formulir evaluasi, tahap, dan data acuan.',
        'outputs.manage' => 'Mencatat luaran penelitian; bagi Operator juga mengoreksi luaran semua pengajuan.',
        'users.verify' => 'Memverifikasi atau menolak NIDN pendaftar baru.',
        'users.manage' => 'Mengelola akun pengguna beserta peran dan hak aksesnya.',
        'organizations.manage' => 'Menambah, mengubah, dan menonaktifkan unit organisasi.',
        'reporting.export' => 'Mengunduh data pengajuan dan laporan sebagai berkas.',
        'reporting.view-audit-log' => 'Melihat jejak perubahan yang tercatat di sistem.',
        'announcements.manage' => 'Membuat, menerbitkan, dan menarik pengumuman.',
    ];

    /**
     * Hak akses langsung hanya dari katalog DDD; nama lain (legacy, karangan) ditolak.
     *
     * @throws ValidationException
     */
    public static function guardKnown(string $name): void
    {
        if (!array_key_exists($name, self::LABELS)) {
            throw ValidationException::withMessages(['permission' => 'Hak akses tidak dikenal.']);
        }
    }

    public static function label(string $name): string
    {
        return self::LABELS[$name] ?? $name;
    }

    public static function description(string $name): string
    {
        return self::DESCRIPTIONS[$name] ?? '';
    }

    /**
     * @param  list<string>  $names
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(array $names): array
    {
        return array_map(static fn (string $name): array => [
            'value' => $name,
            'label' => self::label($name),
            'description' => self::description($name),
        ], $names);
    }

    /**
     * Nama permission dari koleksi model Spatie, urut abjad.
     *
     * @param  iterable<object>  $permissions
     * @return list<string>
     */
    public static function namesOf(iterable $permissions): array
    {
        $names = [];
        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $names[] = $permission->name;
            }
        }
        sort($names);

        return $names;
    }
}
