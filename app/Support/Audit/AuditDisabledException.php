<?php

namespace App\Support\Audit;

use RuntimeException;

/**
 * Dilempar saat penulisan audit gagal karena activitylog dimatikan. Sengaja
 * bukan dilewati diam-diam: BR-RPT-09 menyandarkan cakupan audit pada "semua
 * Action menulis", jadi gagal tanpa suara membuat jaminannya bohong.
 */
class AuditDisabledException extends RuntimeException {}
