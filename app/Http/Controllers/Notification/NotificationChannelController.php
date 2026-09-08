<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannelSetting;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\NotificationChannels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BR-NOT-06 — Admin menyunting channel per tipe event tanpa deploy.
 *
 * Yang tidak bisa disunting adalah **ada atau tidaknya** deklarasi: baris
 * hanya lahir dari seeder yang mengikuti kelas notifikasi, jadi tidak ada
 * jalan membuat deklarasi untuk event yang tidak ada, maupun menghapus
 * deklarasi milik event yang masih dipakai.
 */
class NotificationChannelController extends Controller
{
    public function update(Request $request, string $eventType): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.manage'), 403);

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
            ['channels' => $sebelumnya],
            ['channels' => $setting->channels],
        );

        return back()->with('success', "Channel {$eventType} diperbarui.");
    }
}
