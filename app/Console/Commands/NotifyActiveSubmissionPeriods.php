<?php

namespace App\Console\Commands;

use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodActiveNotification;
use App\Queries\Period\PeriodsOpeningToday;
use App\Support\Notification\Deliver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifyActiveSubmissionPeriods extends Command
{
    protected $signature = 'notify:active-submission-periods';

    protected $description = 'Notify users about newly active submission periods';

    public function handle(): int
    {
        foreach (PeriodsOpeningToday::run(Carbon::now()) as $period) {
            $cacheKey = "submission_period_notified_{$period->id}";

            // Check if we already sent notification for this period
            // Period gagal (cari penerima atau kirim) tidak ditandai sudah
            // diberitahukan, jadi run terjadwal berikutnya mencoba lagi.
            if (Cache::has($cacheKey) || !$this->notify($period)) {
                continue;
            }

            // Mark as notified (cache for 1 year to prevent re-sending)
            Cache::put($cacheKey, true, now()->addYear());

            $this->info("Notification sent for period: {$period->name}");
        }

        $this->info('Done checking active submission periods.');

        return 0;
    }

    private function notify(SubmissionPeriod $period): bool
    {
        // BR-NOT-03 — fire-and-forget: satu period gagal (query penerima
        // atau pengiriman) tidak menghentikan loop, agar period lain yang
        // sudah waktunya tetap ter-notify di run yang sama.
        $users = collect();

        try {
            $users = $this->recipients();

            $terkirim = Deliver::to($users, new SubmissionPeriodActiveNotification($period), [
                'submission_period_id' => $period->id,
                'recipients_count' => $users->count(),
            ]);

            // Period gagal kirim tidak ditandai sudah diberitahukan, agar
            // run terjadwal berikutnya mencobanya lagi. Fire-and-forget:
            // gagal tidak melempar, bukan dianggap berhasil.
            if (!$terkirim) {
                return false;
            }
        } catch (\Throwable $e) {
            // Di sini hanya kegagalan **mencari penerima**; kegagalan
            // kirim sudah ditelan Deliver (BR-NOT-03) beserta konteksnya,
            // jadi menangkapnya lagi mencatat kejadian dua kali.
            Log::error('Gagal menyiapkan notifikasi submission period active', [
                'submission_period_id' => $period->id,
                'recipients_count' => $users->count(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /** @return Collection<int, User> */
    private function recipients(): Collection
    {
        // Recipients: semua user non-admin — perilaku yang sama
        // seperti EmailNotificationService::notifySubmissionPeriodActive()
        // sebelumnya.
        return User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['Super Admin', 'Admin']);
        })->get();
    }
}
