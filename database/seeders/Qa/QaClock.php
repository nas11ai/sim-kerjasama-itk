<?php

namespace Database\Seeders\Qa;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;

/**
 * Menjalankan sepotong alur pada jam tertentu, supaya data "tahun lalu" lahir pada waktunya sendiri:
 * gerbang periode, Temporal Field Binding (BR-FE-11), dan tahun statistik membaca jam, bukan kolom.
 */
final class QaClock
{
    /** Panjang linimasa "tahun ini" dalam hari: periode berjalan dibuka sejauh ini ke belakang. */
    public const SPAN_DAYS = 42;

    /**
     * Saat `$days` hari lalu dalam linimasa tahun ini. Bila sejak 1 Januari belum ada SPAN_DAYS hari,
     * jaraknya dimampatkan ke dalam tahun berjalan (urutannya tetap): tahun statistik dibaca dari jam
     * audit, jadi data "tahun ini" tidak boleh tumpah ke tahun lalu.
     */
    public static function ago(int $days): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $span = min(self::SPAN_DAYS * 86_400, (int) abs($now->diffInSeconds($now->startOfYear())));

        return $now->subSeconds(intdiv($span * $days, self::SPAN_DAYS));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function at(CarbonInterface $moment, Closure $work): mixed
    {
        $previous = Carbon::hasTestNow() ? Carbon::getTestNow() : null;

        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);

        try {
            return $work();
        } finally {
            Carbon::setTestNow($previous);
            CarbonImmutable::setTestNow($previous);
        }
    }
}
