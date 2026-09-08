<?php

namespace App\Support\Audit;

use RuntimeException;

/**
 * Dilempar saat penulisan audit gagal karena activitylog dimatikan.
 *
 * Sengaja bukan diam-diam dilewati: BR-RPT-09 menyandarkan cakupan audit pada
 * "semua Action menulis", jadi penulisan yang gagal tanpa suara membuat
 * jaminannya bohong sejak saat itu.
 */
class AuditDisabledException extends RuntimeException {}
