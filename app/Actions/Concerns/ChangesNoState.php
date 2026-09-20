<?php

namespace App\Actions\Concerns;

/**
 * Penanda: Action ini tidak mengubah state domain, jadi tidak menulis audit. BR-RPT-09 menuntut
 * yang **bukan** perubahan ikut dinyatakan — tanpa penanda ini `AuditCoverageTest` menolak.
 * Sahnya hanya dua: kueri murni, dan pemberitahuan atas perubahan yang sudah dicatat Action lain.
 */
interface ChangesNoState {}
