<?php

namespace App\Support\Audit;

use RuntimeException;

/**
 * BR-RPT-04 — upaya menghapus atau menyunting entri audit. Bukan
 * ValidationException: ini operasi yang memang tidak ada di domain ini, dan
 * pemanggilnya kode, bukan pengguna yang mengisi formulir.
 */
class AppendOnlyViolation extends RuntimeException {}
