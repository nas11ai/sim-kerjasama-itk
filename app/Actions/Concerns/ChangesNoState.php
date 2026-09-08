<?php

namespace App\Actions\Concerns;

/**
 * Penanda: Action ini tidak mengubah state domain, jadi tidak menulis audit.
 *
 * BR-RPT-09 menurunkan cakupan audit dari arsitektur — "semua Action" berarti
 * "semua perubahan yang seseorang bertanggung jawab atasnya". Supaya kalimat
 * itu tetap benar, yang **bukan** perubahan harus dinyatakan, bukan
 * didiamkan: Action tanpa audit dan tanpa penanda ini adalah kegagalan yang
 * ditangkap `AuditCoverageTest`.
 *
 * Penanda ini bukan jalan keluar dari kewajiban menulis audit. Ia dipakai
 * hanya untuk dua hal: kueri murni, dan Action yang seluruh kerjanya
 * mengirim pemberitahuan atas perubahan yang **sudah** dicatat Action lain.
 */
interface ChangesNoState {}
