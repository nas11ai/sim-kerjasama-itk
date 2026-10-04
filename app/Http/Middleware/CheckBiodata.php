<?php

namespace App\Http\Middleware;

use App\Queries\Identity\BiodataStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckBiodata
{
    private const ALLOWED_ROUTES = [
        'user.dashboard',
        'user.biodata.index',
        'user.biodata.submit',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $status = BiodataStatus::run($request->user());

        session(['biodataStatus' => $status]);

        if (isset($status['error'])) {
            Log::warning('Formulir biodata tidak ditemukan atau sedang tidak aktif di dalam database.');
        }

        if ($status['completed'] || !$this->shouldRedirect($request)) {
            return $next($request);
        }

        if ($status['status'] === 'not_submitted') {
            return redirect()
                ->route('user.biodata.index')
                ->with('warning', 'Silakan lengkapi biodata Anda terlebih dahulu untuk mengakses fitur lainnya.');
        }

        return redirect()
            ->route('user.dashboard')
            ->with('info', $status['message'].' Akses menu lain akan dibuka setelah biodata disetujui.');
    }

    private function shouldRedirect(Request $request): bool
    {
        return !in_array($request->route()?->getName(), self::ALLOWED_ROUTES);
    }
}
