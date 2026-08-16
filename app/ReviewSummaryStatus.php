<?php

namespace App;

enum ReviewSummaryStatus: string
{
    case NEEDS_REVISION = 'needs_revision';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NEEDS_REVISION => 'Perlu Revisi',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEEDS_REVISION => 'orange',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::APPROVED => 'CheckCircle',
            self::NEEDS_REVISION => 'AlertCircle',
            self::REJECTED => 'XCircle',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::APPROVED => 'default',
            self::NEEDS_REVISION => 'outline',
            self::REJECTED => 'destructive',
        };
    }

    /**
     * @return list<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            self::cases()
        );
    }

    /**
     * @return array<string, string>
     */
    public static function forSelect(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
