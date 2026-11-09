<?php

use App\Http\ErrorPage;
use App\Http\Middleware\BroadcastSessionActivity;
use App\Http\Middleware\CaptureAuditIp;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // BR-IAM-05 — setelah sesi dibuka, sebelum route berjalan.
            EnsureAccountActive::class,
            // RT1, W168 — dorongan kehadiran untuk layar user; sumbernya tetap SessionRegistry.
            BroadcastSessionActivity::class,
            // W155 — IP pelaku untuk jejak audit.
            CaptureAuditIp::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // W163 — halaman error di shell v2, bukan halaman bawaan Laravel.
        $exceptions->respond(ErrorPage::respond(...));
    })->create();
