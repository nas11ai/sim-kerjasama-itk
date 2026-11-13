<?php

namespace App\Support\Announcement;

/**
 * fe-v2-design §4.7 — sasaran pengumuman: lima peran `RoleCatalog` dengan label versi
 * pengumuman yang sudah tampil sejak A1 (IdentityCatalogTest menjaga himpunannya sama).
 */
final class AnnouncementAudience
{
    /** @var array<string, string> */
    public const ROLE_LABELS = [
        'researcher' => 'Dosen',
        'reviewer_internal' => 'Penilai Internal',
        'reviewer_external' => 'Penilai Eksternal',
        'operator' => 'Operator LPPM',
        'admin' => 'Admin',
    ];

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        $options = [];

        foreach (self::ROLE_LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /** @param  list<string>  $roleNames */
    public static function describe(string $type, array $roleNames): string
    {
        if ($type === 'public') {
            return 'Publik';
        }

        if ($roleNames === []) {
            return 'Semua pengguna yang sudah masuk';
        }

        $labels = array_map(static fn (string $name): string => self::ROLE_LABELS[$name] ?? $name, $roleNames);

        return implode(', ', $labels);
    }
}
