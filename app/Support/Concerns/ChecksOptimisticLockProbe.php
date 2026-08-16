<?php

namespace App\Support\Concerns;

/**
 * PHPStan tidak menganalisis isi trait yang belum dipakai kelas manapun
 * (lihat https://phpstan.org/blog/how-phpstan-analyses-traits). Kelas ini
 * hanya berfungsi supaya `ChecksOptimisticLock` tetap diverifikasi level 9
 * sebelum ada controller nyata yang memakainya (rencana M1).
 *
 * @internal Hapus begitu controller pertama memakai trait ini secara langsung.
 */
final class ChecksOptimisticLockProbe
{
    use ChecksOptimisticLock;
}
