<?php

namespace App\Http\Middleware;

use App\Queries\Identity\EffectivePermissionNames;
use App\Queries\Submission\HasApprovedSubmission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    // Add any other user properties you need
                    'roles' => $request->user()->getRoleNames(), // If you're using Spatie Laravel Permission
                    'is_reviewer' => $request->user()->is_reviewer,
                    'permissions' => EffectivePermissionNames::run($request->user()),
                ] : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
                // BR-REV-04 — peringatan beban reviewer bukan error: penugasan
                // berhasil, Operator yang menilai kewajaran beban. Dibagikan sebagai
                // flash tersendiri supaya tidak tertukar dengan kegagalan.
                'workloadWarnings' => fn () => $request->session()->get('workloadWarnings'),
            ],
            'navigation' => fn () => $request->user()
                ? ['hasApprovedSubmission' => HasApprovedSubmission::run($request->user())]
                : null,
            'biodataStatus' => fn () => $request->session()->get('biodataStatus'),
        ]);
    }
}
