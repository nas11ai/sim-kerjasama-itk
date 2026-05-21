<?php

namespace App\Http\Middleware;

use App\Models\Reviewer;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class CheckReviewer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $today = Carbon::today();

            $isReviewer = Reviewer::where('user_id', $user->id)
                ->where(function ($q) use ($today) {
                    $q->whereNull('start_date')
                        ->orWhere('start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->exists();

            $user->setAttribute('is_reviewer', $isReviewer);

            $user->syncOriginalAttribute('is_reviewer');
        }

        return $next($request);
    }
}
