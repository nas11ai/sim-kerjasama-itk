<?php

// Penjaga QaDataSeeder (docs/design/2026-11-13-seeder-data-uji-qa-design.md): tidak pernah jalan di
// production, tidak menumpuk di database yang sudah dipakai QA, dan jam yang dimundurkan selalu pulih.

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Qa\QaClock;
use Database\Seeders\QaDataSeeder;
use Illuminate\Support\Carbon;

it('menolak berjalan di production', function () {
    $this->seed(DatabaseSeeder::class);
    app()->detectEnvironment(fn (): string => 'production');

    // Lewat `run()` langsung: di production `db:seed` sendiri sudah meminta konfirmasi lebih dulu,
    // dan yang diuji di sini penjaga milik seeder (mis. `--force` di staging yang salah setel).
    expect(fn () => app(QaDataSeeder::class)->run())->toThrow(RuntimeException::class, 'production')
        ->and(User::where('email', 'like', '%@simpas.test')->count())->toBe(0);
});

it('menolak run kedua dan menyuruh migrate:fresh', function () {
    User::factory()->create(['email' => QaDataSeeder::ADMIN_EMAIL]);
    $before = User::count();

    expect(fn () => $this->seed(QaDataSeeder::class))->toThrow(RuntimeException::class, 'migrate:fresh --seed')
        ->and(User::count())->toBe($before);
});

it('memulihkan jam walau pekerjaan gagal', function () {
    try {
        QaClock::at(Carbon::parse('2020-01-01 09:00'), fn () => throw new LogicException('gagal'));
    } catch (LogicException) {
    }

    expect(now()->year)->toBe((int) date('Y'))
        ->and(CarbonImmutable::now()->year)->toBe((int) date('Y'));
});

it('menjalankan pekerjaan pada jam yang diminta', function () {
    $year = QaClock::at(Carbon::parse('2020-03-04 09:00'), fn (): int => now()->year);

    expect($year)->toBe(2020)->and(now()->year)->toBe((int) date('Y'));
});
