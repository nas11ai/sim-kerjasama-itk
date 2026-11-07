<?php

namespace App\Data\View\SystemConfig;

use Spatie\LaravelData\Data;

/** Satu tipe event di kartu channel ADMIN-08-10; `declared` false bila belum punya baris (W113). */
final class NotificationChannelData extends Data
{
    /** @param  list<string>  $channels */
    public function __construct(
        public string $eventType,
        public array $channels,
        public bool $declared,
    ) {}
}
