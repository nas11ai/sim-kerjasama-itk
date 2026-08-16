<?php

use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodActiveNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

// Helper: bangun satu SubmissionPeriod yang baru aktif hari ini — tanggal
// mulai hari ini, tanggal akhir di masa depan, supaya lolos filter
// `$startDate->isToday() && $now->between($startDate, $endDate)` di command.
function createActivePeriod(): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create();

    $label = SubmissionDateLabel::create([
        'name' => 'Label '.fake()->unique()->numerify('####-####'),
    ]);

    SubmissionDate::create([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->startOfDay(),
        'submission_period_id' => $period->id,
    ]);

    SubmissionDate::create([
        'submission_date_label_id' => $label->id,
        'datetime' => now()->addWeek(),
        'submission_period_id' => $period->id,
    ]);

    return $period;
}

it('runs without error when there are no active periods', function () {
    Notification::fake();

    $this->artisan('notify:active-submission-periods')->assertSuccessful();

    Notification::assertNothingSent();
});

it('notifies non-admin users when a submission period is newly active', function () {
    Notification::fake();

    $period = createActivePeriod();

    $researcher = User::factory()->create();

    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->artisan('notify:active-submission-periods')->assertSuccessful();

    Notification::assertSentTo(
        $researcher,
        SubmissionPeriodActiveNotification::class,
        fn (SubmissionPeriodActiveNotification $notification) => $notification->toDatabase($researcher)['meta']['submission_period_id'] === $period->id,
    );

    Notification::assertNotSentTo($admin, SubmissionPeriodActiveNotification::class);
});

it('does not re-notify a period that was already notified', function () {
    // BR-NOT-03 tidak relevan di sini — ini bukan soal kegagalan, tapi
    // guard Cache yang mencegah spam notifikasi tiap kali command jalan
    // (dijadwalkan hourly, lihat routes/console.php).
    Notification::fake();

    $period = createActivePeriod();

    Cache::put("submission_period_notified_{$period->id}", true, now()->addYear());

    User::factory()->create();

    $this->artisan('notify:active-submission-periods')->assertSuccessful();

    Notification::assertNothingSent();
});
