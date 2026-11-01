<?php

namespace App\Data\View\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;

/** Satu baris layar notifikasi SHARED-02-05 (BR-NOT-01). */
final class NotificationRowData extends Data
{
    private const UNCATEGORISED = 'other';

    private const UNCATEGORISED_LABEL = 'Lainnya';

    public function __construct(
        public string $id,
        public string $title,
        public string $message,
        public string $category,
        public string $categoryLabel,
        public string $createdAt,
        public bool $isRead,
    ) {}

    public static function of(DatabaseNotification $notification): self
    {
        $category = NotificationCategory::tryForClass($notification->type);

        return new self(
            id: $notification->id,
            title: self::text($notification, 'title'),
            message: self::text($notification, 'message'),
            category: $category->value ?? self::UNCATEGORISED,
            categoryLabel: $category?->label() ?? self::UNCATEGORISED_LABEL,
            createdAt: $notification->created_at?->toIso8601String() ?? '',
            isRead: $notification->read_at !== null,
        );
    }

    private static function text(DatabaseNotification $notification, string $key): string
    {
        $value = $notification->data[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
