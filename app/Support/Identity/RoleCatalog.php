<?php

namespace App\Support\Identity;

use Spatie\Permission\Models\Role;

/**
 * Peran DDD beserta label layarnya (02_ubiquitous_language §Actors). ASSIGNABLE = peran yang
 * boleh disunting langsung; peran reviewer hanya lewat penunjukan (BR-IAM-11, W57).
 */
final class RoleCatalog
{
    /** @var array<string, string> */
    public const LABELS = [
        'researcher' => 'Peneliti',
        'reviewer_internal' => 'Reviewer (internal)',
        'reviewer_external' => 'Reviewer (eksternal)',
        'operator' => 'Operator',
        'admin' => 'Admin',
    ];

    /** @var list<string> */
    public const ASSIGNABLE = ['researcher', 'operator', 'admin'];

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
     * Opsi dari koleksi role Spatie; role legacy tetap tampil dengan nama mentahnya.
     *
     * @param  iterable<object>  $roles
     * @return list<array{value: string, label: string}>
     */
    public static function optionsOf(iterable $roles): array
    {
        return self::options(self::namesOf($roles));
    }

    /**
     * @param  iterable<object>  $roles
     * @return list<string> urut abjad
     */
    public static function namesOf(iterable $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $names[] = $role->name;
            }
        }
        sort($names);

        return $names;
    }
}
