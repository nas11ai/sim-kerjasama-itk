<?php

namespace App\Http\Middleware;

use App\Enums\SessionEndReason;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-IAM-05, IAM-DEACT-09 — akun nonaktif tidak bisa bertindak, termasuk lewat sesi yang
 * sudah login atau cookie remember-me lama: permintaan berikutnya mengeluarkannya ke login.
 * User sudah dimuat guard, jadi tanpa query tambahan.
 */
class EnsureAccountActive
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User || $user->is_active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', SessionEndReason::Deactivated->notice());
    }
}
