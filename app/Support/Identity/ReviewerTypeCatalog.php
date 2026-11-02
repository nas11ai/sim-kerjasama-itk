<?php

namespace App\Support\Identity;

use App\Actions\Identity\AppointReviewer;

/** BR-IAM-11 — label tipe reviewer; satu sumber untuk daftar, kandidat, dan dialog penunjukan. */
final class ReviewerTypeCatalog
{
    /** @var array<string, string> */
    public const LABELS = [
        AppointReviewer::TYPE_INTERNAL => 'Internal',
        AppointReviewer::TYPE_EXTERNAL => 'Eksternal',
    ];

    /** @return array{value: string, label: string} */
    public static function option(string $type): array
    {
        return ['value' => $type, 'label' => self::LABELS[$type] ?? $type];
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(self::option(...), array_keys(self::LABELS));
    }
}
