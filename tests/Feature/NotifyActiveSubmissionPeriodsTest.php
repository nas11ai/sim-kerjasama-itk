<?php

use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodActiveNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

it('keeps notifying remaining periods when one period fails, per BR-NOT-03', function () {
    // Isolasi error per-period: kegagalan pada satu period (query penerima
    // atau pengiriman notifikasi) tidak boleh menggagalkan period lain yang
    // juga sudah waktunya di-notify pada run yang sama.
    Log::spy();

    $failingPeriod = createActivePeriod();
    $survivingPeriod = createActivePeriod();

    User::factory()->create();

    $notifiedPeriodIds = [];

    Notification::shouldReceive('send')
        ->twice()
        ->andReturnUsing(function ($notifiables, SubmissionPeriodActiveNotification $notification) use (&$notifiedPeriodIds, $failingPeriod) {
            $notifiable = collect($notifiables)->first();
            $periodId = $notification->toDatabase($notifiable)['meta']['submission_period_id'];
            $notifiedPeriodIds[] = $periodId;

            if ($periodId === $failingPeriod->id) {
                throw new RuntimeException('Simulasi kegagalan pengiriman notifikasi.');
            }
        });

    $this->artisan('notify:active-submission-periods')->assertSuccessful();

    expect($notifiedPeriodIds)
        ->toContain($failingPeriod->id)
        ->toContain($survivingPeriod->id);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['submission_period_id'] === $failingPeriod->id
            && array_key_exists('recipients_count', $context));

    // Period yang gagal tidak boleh di-cache sebagai sudah dinotifikasi,
    // supaya dicoba lagi pada run berikutnya; period yang sukses harus.
    expect(Cache::has("submission_period_notified_{$failingPeriod->id}"))->toBeFalse();
    expect(Cache::has("submission_period_notified_{$survivingPeriod->id}"))->toBeTrue();
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
