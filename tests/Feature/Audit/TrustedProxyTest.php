<?php

// tests/Feature/Audit/TrustedProxyTest.php
//
// E2E-09, W165 — IP pelaku di jejak audit (RPT-AUDIT-08) adalah IP asli hanya bila proxy di
// depannya dipercaya lewat env TRUSTED_PROXIES. Tanpa konfigurasi, X-Forwarded-For diabaikan:
// memercayai semua proxy membuka pemalsuan IP.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\AuditEntry;

function trustedProxySubmitIp(): ?string
{
    $fx = wizardFixture();
    $submission = StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']);

    test()->actingAs($fx['user'])
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'])
        ->post(route('user.submissions.submit', $submission))
        ->assertSessionHasNoErrors();

    return AuditEntry::query()->auditOnly()->where('description', 'Pengajuan disubmit')->latest('id')->firstOrFail()->ip_address;
}

it('[E2E-09] IP asli dicatat bila proxy dipercaya', function () {
    config(['trustedproxy.proxies' => '10.0.0.1']);

    expect(trustedProxySubmitIp())->toBe('203.0.113.9');
});

it('[E2E-09] daftar proxy dipisah koma', function () {
    config(['trustedproxy.proxies' => '192.168.1.1, 10.0.0.1']);

    expect(trustedProxySubmitIp())->toBe('203.0.113.9');
});

it('[E2E-09] tanpa konfigurasi, X-Forwarded-For diabaikan', function () {
    config(['trustedproxy.proxies' => null]);

    expect(trustedProxySubmitIp())->toBe('10.0.0.1');
});

it('[E2E-09] proxy yang tidak terdaftar tidak dipercaya', function () {
    config(['trustedproxy.proxies' => '192.168.1.1']);

    expect(trustedProxySubmitIp())->toBe('10.0.0.1');
});

it('[E2E-09] konfigurasi dibaca dari env TRUSTED_PROXIES; kosong berarti tidak ada proxy', function (string $env, ?string $expected) {
    putenv("TRUSTED_PROXIES={$env}");

    try {
        expect((require base_path('config/trustedproxy.php'))['proxies'])->toBe($expected);
    } finally {
        putenv('TRUSTED_PROXIES');
    }
})->with([
    'daftar' => ['10.0.0.1,10.0.0.2', '10.0.0.1,10.0.0.2'],
    'semua' => ['*', '*'],
    'kosong' => ['', null],
]);
