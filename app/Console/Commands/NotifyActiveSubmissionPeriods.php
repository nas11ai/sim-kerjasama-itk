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
        // BR-NOT-03 — notifikasi bersifat fire-and-forget: kegagalan pada
        // satu period (query penerima maupun pengiriman) tidak boleh
        // menghentikan loop, supaya period lain yang juga sudah waktunya
        // tetap ter-notify pada run yang sama. Period yang gagal tidak
        // di-cache sebagai sudah dinotifikasi, sehingga akan dicoba lagi
        // pada run terjadwal berikutnya.
        $users = collect();

        try {
            $users = $this->recipients();

            $terkirim = Deliver::to($users, new SubmissionPeriodActiveNotification($period), [
                'submission_period_id' => $period->id,
                'recipients_count' => $users->count(),
            ]);

            // Period yang pengirimannya gagal tidak ditandai sudah
            // diberitahukan, supaya run terjadwal berikutnya mencobanya
            // lagi. Fire-and-forget berarti kegagalannya tidak melempar,
            // bukan bahwa kegagalannya dianggap keberhasilan.
            if (!$terkirim) {
                return false;
            }
        } catch (\Throwable $e) {
            // Yang tersisa untuk ditangkap di sini adalah kegagalan
            // **mencari penerima**. Kegagalan pengirimannya sendiri sudah
            // ditelan Deliver (BR-NOT-03) beserta konteks period-nya, jadi
            // menangkapnya dua kali hanya akan mencatat satu kejadian
            // sebagai dua baris.
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
