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
        'reviewers.assign' => 'Menugaskan reviewer',
        'reviewers.evaluate' => 'Mengevaluasi pengajuan (reviewer)',
        'reviewers.view-scores-others' => 'Melihat skor reviewer lain',
        'periods.manage' => 'Mengelola periode',
        'schemes.manage' => 'Mengelola skema & konfigurasi',
        'outputs.manage' => 'Mengelola luaran',
        'users.verify' => 'Memverifikasi NIDN',
        'users.manage' => 'Mengelola user & hak akses',
        'reporting.export' => 'Mengekspor laporan',
        'reporting.view-audit-log' => 'Melihat audit log',
        'announcements.manage' => 'Mengelola pengumuman',
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

    /**
     * @param  list<string>  $names
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $names): array
    {
        return array_map(static fn (string $name): array => ['value' => $name, 'label' => self::label($name)], $names);
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
