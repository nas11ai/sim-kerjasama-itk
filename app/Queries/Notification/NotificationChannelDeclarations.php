<?php

namespace App\Queries\Notification;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\SystemConfig\NotificationChannelData;
use App\Models\NotificationChannelSetting;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-NOT-06 — satu baris per tipe event yang dinyatakan kelas notifikasi, termasuk yang belum
 * berdeklarasi (NOT-CHANNEL-04 tergambar, W113). Baris di luar kelas notifikasi tidak tampil.
 *
 * @method static list<NotificationChannelData> run()
 */
class NotificationChannelDeclarations implements ChangesNoState
{
    use AsAction;

    /** @return list<NotificationChannelData> */
    public function handle(): array
    {
        // Dibaca langsung, bukan lewat cache NotificationChannels, supaya layar tidak basi.
        $declared = NotificationChannelSetting::query()->get()
            ->mapWithKeys(fn (NotificationChannelSetting $setting): array => [$setting->event_type => $setting->channels])
            ->all();
        $eventTypes = array_keys(NotificationEventCatalog::declaredByClasses());
        sort($eventTypes);

        return array_map(fn (string $eventType): NotificationChannelData => new NotificationChannelData(
            eventType: $eventType,
            channels: array_values($declared[$eventType] ?? []),
            declared: array_key_exists($eventType, $declared),
        ), $eventTypes);
    }

    /** @return list<array{value: string, label: string}> */
    public static function channelOptions(): array
    {
        return [
            ['value' => NotificationChannels::IN_APP, 'label' => 'In-app'],
            ['value' => NotificationChannels::EMAIL, 'label' => 'Email'],
        ];
    }
}
