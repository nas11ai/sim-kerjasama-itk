<?php

namespace App\Data\Contract;

/**
 * BR-NOT-01 — bentuk satu notifikasi in-app. Kontrak, bukan payload layar: ia
 * dibaca layar notifikasi dan kelak API, jadi mengubah kuncinya memutus konsumen.
 */
final class NotificationPayload
{
    /** @param array<string, int|string|null> $context */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        // Urutan kunci sama dengan baris lama; kunci wajib ditimpa terakhir supaya konteks tak bisa menggantinya.
        return array_replace(
            ['type' => $this->type],
            $this->context,
            ['type' => $this->type, 'title' => $this->title, 'message' => $this->message],
        );
    }
}
