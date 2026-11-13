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
