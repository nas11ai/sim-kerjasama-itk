<?php

namespace App\Support\Lifecycle;

use RuntimeException;

/**
 * Entitas yang tak pernah boleh hilang. Dilempar dari `booted()` seperti jejak
 * audit (BR-RPT-04), bukan Policy: jalur Eloquent tertutup. Beda dari
 * BR-SC-01/BR-FE-19: masih dirujuk itu keadaan berubah — ValidationException.
 */
final class DeletionNotPermitted extends RuntimeException {}
