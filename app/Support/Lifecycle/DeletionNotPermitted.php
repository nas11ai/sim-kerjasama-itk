<?php

namespace App\Support\Lifecycle;

use RuntimeException;

/**
 * Entitas yang tak pernah boleh hilang, beda dari BR-SC-01 dan BR-FE-19 yang
 * menolak selama masih dirujuk — keadaan berubah, jadi ValidationException.
 * Dilempar dari `booted()`, bukan Policy (BR-RPT-04): jalur Eloquent tertutup.
 */
final class DeletionNotPermitted extends RuntimeException {}
