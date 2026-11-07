<?php

namespace App\Http\Controllers\Notification;

use App\Actions\Notification\DeclareNotificationChannels;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationChannelRequest;
use Illuminate\Http\RedirectResponse;

/**
 * BR-NOT-06 — Admin menetapkan channel per tipe event tanpa deploy (ADMIN-08-10). Route dijaga
 * `can:schemes.manage` di grup /admin/reference (W105).
 */
class NotificationChannelController extends Controller
{
    use ResolvesActor;

    public function update(NotificationChannelRequest $request, string $eventType): RedirectResponse
    {
        DeclareNotificationChannels::run($eventType, $request->channels(), $this->actor($request));

        return back()->with('success', "Channel {$eventType} diperbarui.");
    }
}
