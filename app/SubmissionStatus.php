<?php

namespace App;

enum SubmissionStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case NEEDS_REVISION = 'needs_revision';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Menunggu Review',
            self::UNDER_REVIEW => 'Sedang Direview',
            self::NEEDS_REVISION => 'Perlu Revisi',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'yellow',
            self::UNDER_REVIEW => 'blue',
            self::NEEDS_REVISION => 'orange',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'FileText',
            self::PENDING => 'Clock',
            self::UNDER_REVIEW => 'Search',
            self::APPROVED => 'CheckCircle',
            self::NEEDS_REVISION => 'AlertCircle',
            self::REJECTED => 'XCircle',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'outline',
            self::UNDER_REVIEW => 'secondary',
            self::APPROVED => 'default',
            self::NEEDS_REVISION => 'outline',
            self::REJECTED => 'destructive',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ])->toArray();
    }

    public static function forSelect(): array
    {
        return collect(self::cases())->pluck('label', 'value')->toArray();
    }
}
