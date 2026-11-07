<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannelSetting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\NotificationChannels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BR-NOT-06 — Admin menyunting channel per tipe event tanpa deploy.
 *
 * Yang tak bisa disunting adalah ada-tidaknya deklarasi: baris cuma lahir dari
 * seeder yang mengikuti kelas notifikasi — tak bisa dibuat atau dihapus manual.
 */
class NotificationChannelController extends Controller
{
    public function update(Request $request, string $eventType): RedirectResponse
    {
        // W105 — wewenangnya `can:schemes.manage` di grup route /admin/reference.
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'channels' => ['array'],
            'channels.*' => ['string', 'in:'.NotificationChannels::IN_APP.','.NotificationChannels::EMAIL],
        ]);

        $setting = NotificationChannelSetting::query()
            ->where('event_type', $eventType)
            ->firstOrFail();

        $sebelumnya = $setting->channels;
        $setting->update([
            'channels' => array_values(array_unique($validated['channels'] ?? [])),
            'updated_by' => $user->id,
        ]);

        NotificationChannels::forget();

        AuditTrail::record(
            'Channel notifikasi diubah',
            $setting->refresh(),
            $user,
            new AuditChange(
                old: ['channels' => $sebelumnya],
                new: ['channels' => $setting->channels],
            ),
        );

        return back()->with('success', "Channel {$eventType} diperbarui.");
    }
}
