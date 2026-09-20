<?php

namespace App\Support\Reporting;

/**
 * BR-RPT-02 — ambang antara ekspor yang langsung dan yang masuk background
 * job. Satu konstanta untuk tabular maupun cetak PDF (BR-RPT-11 menyatakan
 * ambangnya sama); angka kedua membuat Operator menebak kapan layar menggantung.
 */
final class ExportThreshold
{
    /** Lebih dari ini masuk background job. */
    public const SYNCHRONOUS_ROWS = 100;

    public static function needsBackgroundJob(int $rowCount): bool
    {
        return $rowCount > self::SYNCHRONOUS_ROWS;
    }
}
