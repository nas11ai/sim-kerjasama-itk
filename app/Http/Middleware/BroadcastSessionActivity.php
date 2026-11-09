<?php

namespace App\Http\Middleware;

use App\Events\Identity\UserSessionActive;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * RT1, W168 — mendorong `session.active` ke layar user. Dipasang setelah EnsureAccountActive,
 * jadi akun nonaktif sudah dikeluarkan sebelum sampai sini.
 */
class BroadcastSessionActivity
{
    public const THROTTLE_SECONDS = 60;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // BR-IAM-21 — setiap request adalah aktivitas sesi; throttle membuat badge basi ≤ 1 menit.
        if ($user instanceof User && $user->is_active && Cache::add("session-activity:{$user->id}", true, self::THROTTLE_SECONDS)) {
            UserSessionActive::dispatch($user->id, CarbonImmutable::now());
        }

        return $next($request);
    }
}
