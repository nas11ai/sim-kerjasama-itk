<?php

// BR-REV-09 — masa tugas reviewer inklusif per hari, satu definisi.

use App\Models\Reviewer;
use App\Models\User;
use App\Queries\Review\ReviewersWithinTenure;

function tenureReviewer(?string $start, ?string $end): Reviewer
{
    return Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => $start,
        'end_date' => $end,
    ])->refresh();
}

it('BR-REV-09 — end_date hari ini pukul 15:00 masih bertugas', function () {
    $this->travelTo(now()->setTime(15, 0));
    $reviewer = tenureReviewer(now()->subYear()->toDateString(), now()->toDateString());

    expect($reviewer->isWithinTenure())->toBeTrue()
        ->and(ReviewersWithinTenure::run()->whereKey($reviewer->id)->exists())->toBeTrue();
});

it('BR-REV-09 — start_date besok belum bertugas', function () {
    $reviewer = tenureReviewer(now()->addDay()->toDateString(), null);

    expect($reviewer->isWithinTenure())->toBeFalse()
        ->and(ReviewersWithinTenure::run()->whereKey($reviewer->id)->exists())->toBeFalse();
});

it('BR-REV-09 — end_date kemarin sudah habis', function () {
    $reviewer = tenureReviewer(now()->subYear()->toDateString(), now()->subDay()->toDateString());

    expect($reviewer->isWithinTenure())->toBeFalse()
        ->and(ReviewersWithinTenure::run()->whereKey($reviewer->id)->exists())->toBeFalse();
});

// reviewers.start_date NOT NULL di skema: tanpa start hanya ada di model yang belum disimpan.
it('BR-REV-09 — tanpa start_date dan end_date bertugas', function () {
    expect(new Reviewer(['start_date' => null, 'end_date' => null])->isWithinTenure())->toBeTrue();

    $open = tenureReviewer(now()->subYear()->toDateString(), null);

    expect($open->isWithinTenure())->toBeTrue()
        ->and(ReviewersWithinTenure::run()->whereKey($open->id)->exists())->toBeTrue();
});

it('BR-REV-09 — query dan isWithinTenure menghasilkan himpunan yang sama', function () {
    $this->travelTo(now()->setTime(15, 0));
    $all = collect([
        tenureReviewer(now()->subYear()->toDateString(), now()->toDateString()),
        tenureReviewer(now()->addDay()->toDateString(), null),
        tenureReviewer(now()->subYear()->toDateString(), now()->subDay()->toDateString()),
        tenureReviewer(now()->subYear()->toDateString(), null),
    ]);

    $viaModel = $all->filter(fn (Reviewer $r) => $r->isWithinTenure())->pluck('id')->sort()->values()->all();
    $viaQuery = ReviewersWithinTenure::run()->whereKey($all->pluck('id'))->pluck('id')->sort()->values()->all();

    expect($viaQuery)->toBe($viaModel)->and($viaModel)->toHaveCount(2);
});

it('BR-REV-09 — start_date hari ini dengan jam bukan tengah malam bertugas', function () {
    $this->travelTo(now()->setTime(8, 0));
    $reviewer = tenureReviewer(now()->setTime(13, 45)->toDateTimeString(), null);

    expect($reviewer->isWithinTenure())->toBeTrue()
        ->and(ReviewersWithinTenure::run()->whereKey($reviewer->id)->exists())->toBeTrue();
});

it('BR-REV-09 — pukul 23:30 end_date hari ini masih bertugas', function () {
    $this->travelTo(now()->setTime(23, 30));
    $reviewer = tenureReviewer(now()->subYear()->toDateString(), now()->toDateString());

    expect($reviewer->isWithinTenure())->toBeTrue()
        ->and(ReviewersWithinTenure::run()->whereKey($reviewer->id)->exists())->toBeTrue();
});
