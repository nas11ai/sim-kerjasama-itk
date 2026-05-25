<?php

namespace App\Console\Commands;

use App\Models\SubmissionPeriod;
use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyActiveSubmissionPeriods extends Command
{
    protected $signature = 'notify:active-submission-periods';

    protected $description = 'Notify users about newly active submission periods';

    protected $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

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

                $startDate = Carbon::parse($dates->first()->datetime);
                $endDate = Carbon::parse($dates->last()->datetime);

                // Check if period just became active (started today)
                return $startDate->isToday() && $now->between($startDate, $endDate);
            });

        foreach ($submissionPeriods as $period) {
            $cacheKey = "submission_period_notified_{$period->id}";

            // Check if we already sent notification for this period
            if (Cache::has($cacheKey)) {
                continue;
            }

            // Send notification
            $this->emailService->notifySubmissionPeriodActive($period);

            // Mark as notified (cache for 1 year to prevent re-sending)
            Cache::put($cacheKey, true, now()->addYear());

            $this->info("Notification sent for period: {$period->name}");
        }

        $this->info('Done checking active submission periods.');

        return 0;
    }
}
