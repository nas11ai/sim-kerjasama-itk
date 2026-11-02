<?php

namespace App\Support\Identity;

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
}
