<?php

use App\Support\Format\PeriodName;

// Paket 2 P1 — "Periode Periode 2026" di Beranda: awalan hanya ditambahkan bila belum ada.
it('memberi awalan Periode pada nama tanpa awalan', function () {
    expect(PeriodName::format('DIPA 2026'))->toBe('Periode DIPA 2026');
});

it('tidak menggandakan awalan, apa pun huruf besarnya', function (string $name) {
    expect(PeriodName::format($name))->toBe(trim($name));
})->with(['Periode 2026', 'periode ganjil 2026', '  Periode A  ']);

it('kata yang hanya diawali "periode" tetap diberi awalan', function () {
    expect(PeriodName::format('Periodesasi 2026'))->toBe('Periode Periodesasi 2026');
});
