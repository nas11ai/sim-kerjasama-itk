<?php

// tests/Feature/Period/NotifyActiveSubmissionPeriodsTest.php
//
// Karakterisasi notify:active-submission-periods sebelum dibedah. Perilakunya
// dikunci apa adanya, termasuk yang terasa janggal — tujuan test ini menjaga
// refactor, bukan menilai aturannya.

use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodActiveNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

function periodeDenganTanggal(string $buka, string $tutup): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create();

    foreach (['Buka' => $buka, 'Tutup' => $tutup] as $label => $kapan) {
        SubmissionDate::create([
            'submission_period_id' => $period->id,
            'submission_date_label_id' => SubmissionDateLabel::firstOrCreate(['name' => $label])->id,
            'datetime' => $kapan,
        ]);
    }

    return $period;
}

beforeEach(function () {
    Notification::fake();
    Cache::flush();
    Carbon::setTestNow('2026-10-03 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('memberi tahu pengguna non-admin saat periode dibuka hari ini', function () {
    $peneliti = User::factory()->create();
    periodeDenganTanggal('2026-10-03 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods')->assertExitCode(0);

    Notification::assertSentTo($peneliti, SubmissionPeriodActiveNotification::class);
});

it('tidak memberi tahu Admin maupun Super Admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Admin', 'web'));
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('Super Admin', 'web'));
    periodeDenganTanggal('2026-10-03 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');

    Notification::assertNotSentTo($admin, SubmissionPeriodActiveNotification::class);
    Notification::assertNotSentTo($superAdmin, SubmissionPeriodActiveNotification::class);
});

it('mengabaikan periode yang dibuka kemarin', function () {
    User::factory()->create();
    periodeDenganTanggal('2026-10-02 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');

    Notification::assertNothingSent();
});

it('mengabaikan periode yang dibuka hari ini tapi jamnya belum tiba', function () {
    // Sekarang 09:00, pembukaannya 10:00: tanggalnya hari ini, tapi `between()`
    // menolaknya karena waktu sekarang masih di luar rentang.
    User::factory()->create();
    periodeDenganTanggal('2026-10-03 10:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');

    Notification::assertNothingSent();
});

it('tidak memberi tahu dua kali untuk periode yang sama', function () {
    $peneliti = User::factory()->create();
    periodeDenganTanggal('2026-10-03 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');
    $this->artisan('notify:active-submission-periods');

    Notification::assertSentToTimes($peneliti, SubmissionPeriodActiveNotification::class, 1);
});

it('mengabaikan periode tanpa tanggal sama sekali', function () {
    User::factory()->create();
    SubmissionPeriod::factory()->create();

    $this->artisan('notify:active-submission-periods')->assertExitCode(0);

    Notification::assertNothingSent();
});
