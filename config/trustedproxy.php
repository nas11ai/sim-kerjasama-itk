<?php

// E2E-09, W165 — proxy yang dipercaya untuk X-Forwarded-*, dibaca middleware TrustProxies
// bawaan Laravel. Daftar dipisah koma atau `*`; kosong = tidak memercayai siapa pun, sehingga
// IP pelaku di jejak audit (CaptureAuditIp) tidak bisa dipalsukan lewat header.
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
