<?php

namespace App\Providers;

use App\Models\FormSubmission;
use App\Policies\FormSubmissionPolicy;
use App\Services\Storage\S3StorageService;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StorageService::class, fn () => new S3StorageService(
            config('filesystems.default') === 'local' ? 'local' : 's3'
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Schema::defaultStringLength(191);
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
        Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);
    }
}
