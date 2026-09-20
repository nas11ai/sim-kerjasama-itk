<?php

namespace App\Support\Notification;

use Illuminate\Notifications\Notification;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Katalog tipe event notifikasi beserta channel default-nya. Tipe event-nya
 * **ditemukan dari kelas notifikasi**, bukan ditulis ulang di sini: dua daftar
 * yang dirawat terpisah akan berbeda pada kelas berikutnya yang ditambahkan.
 */
final class NotificationEventCatalog
{
    /**
     * Default per tipe event, satu-satunya bagian yang ditulis tangan. Email
     * hanya untuk yang menuntut tindakan segera (BR-NOT-02); kelas tanpa
     * default di sini ditolak `NotificationChannelTest`, bukan diberi in-app.
     *
     * @var array<string, array<int, string>>
     */
    private const DEFAULTS = [
        'ExportCompleted' => [NotificationChannels::IN_APP],
        'ExportFailed' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'FormChanged' => [NotificationChannels::IN_APP],
        'MonevEvaluationSubmitted' => [NotificationChannels::IN_APP],
        'MonevStageOpened' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'PhaseOverrideGranted' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'ProposalSubmitted' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'ReviewerAppointed' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'ReviewerAssigned' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'SubmissionDeadlineExtended' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'SubmissionPeriodActive' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'SubmissionPeriodClosed' => [NotificationChannels::IN_APP],
        'UserDeactivated' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'UserRegistered' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'UserVerificationRejected' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
        'UserVerified' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * Tipe event yang benar-benar dideklarasikan kelas notifikasi.
     *
     * @return array<string, class-string<Notification>>
     */
    public static function declaredByClasses(): array
    {
        $events = [];

        foreach (Finder::create()->files()->in(app_path('Notifications'))->name('*.php')->sortByName() as $file) {
            $class = 'App\\Notifications\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || !$reflection->isSubclassOf(Notification::class)) {
                continue;
            }

            if (!$reflection->hasConstant('EVENT_TYPE')) {
                throw new RuntimeException(
                    "{$class} tidak menyatakan EVENT_TYPE — channel-nya tidak bisa dikonfigurasi (BR-NOT-06)."
                );
            }

            /** @var string $eventType */
            $eventType = $reflection->getConstant('EVENT_TYPE');

            /** @var class-string<Notification> $notificationClass */
            $notificationClass = $reflection->getName();

            $events[$eventType] = $notificationClass;
        }

        return $events;
    }
}
