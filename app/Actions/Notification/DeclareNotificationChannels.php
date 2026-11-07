<?php

namespace App\Actions\Notification;

use App\Models\NotificationChannelSetting;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\NotificationChannels;
use App\Support\Notification\NotificationEventCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BR-NOT-06 — Admin menetapkan channel satu tipe event tanpa deploy. Ada-tidaknya tipe event
 * tetap mengikuti kelas notifikasi; event yang belum berdeklarasi boleh ditetapkan (W113).
 *
 * @method static NotificationChannelSetting run(string $eventType, list<string> $channels, User $actor)
 */
class DeclareNotificationChannels
{
    use AsAction;

    /**
     * @param  list<string>  $channels
     *
     * @throws ValidationException
     */
    public function handle(string $eventType, array $channels, User $actor): NotificationChannelSetting
    {
        if (!array_key_exists($eventType, NotificationEventCatalog::declaredByClasses())) {
            throw new NotFoundHttpException("Tipe event {$eventType} tidak dikenal kelas notifikasi mana pun.");
        }

        // BR-NOT-06 — hanya in-app, email, atau keduanya; daftar kosong membuat event bisu.
        if ($channels === []) {
            throw ValidationException::withMessages(['channels' => 'Pilih minimal satu channel: in-app, email, atau keduanya.']);
        }

        $declared = array_values(array_unique($channels));
        sort($declared);

        return DB::transaction(function () use ($eventType, $declared, $actor): NotificationChannelSetting {
            $existing = NotificationChannelSetting::query()->where('event_type', $eventType)->first();
            $before = $existing === null ? [] : $existing->channels;

            $setting = NotificationChannelSetting::query()->updateOrCreate(
                ['event_type' => $eventType],
                ['channels' => $declared, 'updated_by' => $actor->id],
            );

            NotificationChannels::forget();

            AuditTrail::record(
                'Channel notifikasi diubah',
                $setting,
                $actor,
                new AuditChange(old: ['channels' => $before], new: ['channels' => $declared]),
            );

            return $setting;
        });
    }
}
