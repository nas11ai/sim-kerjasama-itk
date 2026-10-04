<?php

namespace App\Http\Middleware;

use App\Queries\Review\ReviewersWithinTenure;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckReviewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // BR-REV-09 — definisi masa tugas satu-satunya (inklusif per hari).
            $isReviewer = ReviewersWithinTenure::run()
                ->where('user_id', $user->id)
                ->exists();

            $user->setAttribute('is_reviewer', $isReviewer);

            $user->syncOriginalAttribute('is_reviewer');
        }

        return $next($request);
    }
}
