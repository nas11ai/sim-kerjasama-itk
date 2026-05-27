<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $allowedRoles = explode('|', $roles);

        if (!$request->user()->hasAnyRole($allowedRoles)) {
            // Redirect based on user's actual role
            if ($request->user()->hasAnyRole(['Super Admin', 'Admin'])) {
                return redirect('/dashboard')
                    ->with('error', 'Anda tidak memiliki akses ke halaman ini.');
            } else {
                return redirect('/user/dashboard')
                    ->with('error', 'Anda tidak memiliki akses ke halaman ini.');
            }
        }

        return $next($request);
    }
}
