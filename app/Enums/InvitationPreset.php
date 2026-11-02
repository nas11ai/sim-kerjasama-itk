<?php

namespace App\Enums;

use Database\Seeders\PermissionSeeder;

/**
 * W51 — hak akses undangan dipilih dari preset, bukan daftar bebas. Operator ikut menerbitkan
 * (W47), jadi preset adalah batas keamanan: tak ada undangan yang lebih berkuasa dari peneliti.
 */
enum InvitationPreset: string
{
    case Researcher = 'researcher';

    public function label(): string
    {
        return match ($this) {
            self::Researcher => 'Membuat & mengelola pengajuan',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Researcher => PermissionSeeder::ROLE_PERMISSIONS['researcher'],
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $preset): array => ['value' => $preset->value, 'label' => $preset->label()],
            self::cases(),
        );
    }

    /**
     * Cocok persis setelah diurutkan; token lama atau kustom tidak punya preset.
     *
     * @param  list<string>  $permissions
     */
    public static function fromPermissions(array $permissions): ?self
    {
        sort($permissions);

        foreach (self::cases() as $preset) {
            $expected = $preset->permissions();
            sort($expected);

            if ($expected === $permissions) {
                return $preset;
            }
        }

        return null;
    }
}
