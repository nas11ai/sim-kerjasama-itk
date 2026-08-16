<?php

namespace App\Console\Commands;

use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodActiveNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyActiveSubmissionPeriods extends Command
{
    protected $signature = 'notify:active-submission-periods';

    protected $description = 'Notify users about newly active submission periods';

    public function handle()
    {
        $now = Carbon::now();

        $submissionPeriods = SubmissionPeriod::with('submissionDates')
            ->get()
            ->filter(function ($period) use ($now) {
                $dates = $period->submissionDates->sortBy('datetime');

                if ($dates->isEmpty()) {
                    return false;
                }

                /** @var SubmissionDate $firstDate */
                $firstDate = $dates->first();

                /** @var SubmissionDate $lastDate */
                $lastDate = $dates->last();

                $startDate = Carbon::parse($firstDate->datetime);
                $endDate = Carbon::parse($lastDate->datetime);

                // Check if period just became active (started today)
                return $startDate->isToday() && $now->between($startDate, $endDate);
            });

        foreach ($submissionPeriods as $period) {
            $cacheKey = "submission_period_notified_{$period->id}";

            // Check if we already sent notification for this period
            if (Cache::has($cacheKey)) {
                continue;
            }

            // BR-NOT-03 — notifikasi bersifat fire-and-forget: kegagalan pada
            // satu period (query penerima maupun pengiriman) tidak boleh
            // menghentikan loop, supaya period lain yang juga sudah waktunya
            // tetap ter-notify pada run yang sama. Period yang gagal tidak
            // di-cache sebagai sudah dinotifikasi, sehingga akan dicoba lagi
            // pada run terjadwal berikutnya.
            $users = collect();

            try {
                // Recipients: semua user non-admin — perilaku yang sama
                // seperti EmailNotificationService::notifySubmissionPeriodActive()
                // sebelumnya.
                $users = User::whereDoesntHave('roles', function ($query) {
                    $query->whereIn('name', ['Super Admin', 'Admin']);
                })->get();

                Notification::send($users, new SubmissionPeriodActiveNotification($period));
            } catch (\Exception $e) {
                Log::error('Gagal mengirim notifikasi submission period active', [
                    'submission_period_id' => $period->id,
                    'recipients_count' => $users->count(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // Mark as notified (cache for 1 year to prevent re-sending)
            Cache::put($cacheKey, true, now()->addYear());

            $this->info("Notification sent for period: {$period->name}");
        }

        $this->info('Done checking active submission periods.');

        return 0;
    }
}
