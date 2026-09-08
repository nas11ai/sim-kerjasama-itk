<?php

namespace App\Support\Reporting;

/**
 * BR-RPT-02 — ambang antara ekspor yang dijalankan langsung dan yang masuk
 * background job.
 *
 * Satu konstanta, dipakai ekspor tabular maupun cetak PDF (BR-RPT-11 memang
 * menyatakan ambangnya sama). Angka kedua yang berbeda akan membuat "ambang
 * yang sama" berhenti benar pada pemakaian pertamanya, dan Operator
 * menebak-nebak kapan layarnya akan menggantung.
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
