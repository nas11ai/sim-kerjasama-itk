<?php

namespace App\Providers;

use App\Models\FormSubmission;
use App\Models\InvitationToken;
use App\Models\ReviewerFormAssignment;
use App\Models\User;
use App\Policies\BudgetLineItemPolicy;
use App\Policies\FormSubmissionPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\ReviewerFormAssignmentPolicy;
use App\Policies\StatisticsPolicy;
use App\Policies\UserPolicy;
use App\Services\Storage\S3StorageService;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * BR-SM-04 — ability yang menyandang invarian domain, bukan sekadar kewenangan: submit dan
     * resubmit mengikat `submitted_by` pada dirinya sendiri. Untuk orang lain: transfer kepemilikan
     * (BR-SM-09) yang berjejak. `evaluate` sama: jawaban evaluasi mengikat reviewer yang ditugaskan.
     *
     * @var list<string>
     */
    private const INVARIANT_ABILITIES = ['submit', 'resubmit', 'update-budget', 'evaluate', 'startMonevReport'];

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
            // Super Admin boleh mengurus apa pun, tapi tidak bisa *menjadi*
            // orang lain: record akan menyatakan seorang peneliti menyubmit
            // padahal bukan dia (ADR 0015).
            if (in_array($ability, self::INVARIANT_ABILITIES, true)) {
                return null;
            }

            return $user->hasRole('Super Admin') ? true : null;
        });
        Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);
        Gate::policy(ReviewerFormAssignment::class, ReviewerFormAssignmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(InvitationToken::class, InvitationPolicy::class);
        // BR-BUD-02, BR-BUD-09 (register #24) — `update-budget` menyusul dengan
        // alasan sama: daftarnya tertutup, dan angka anggaran yang sudah
        // diputuskan adalah dasar pencairan — koreksinya lewat transisi status.
        Gate::define('update-budget', [BudgetLineItemPolicy::class, 'update']);
        Gate::define('view-statistics', [StatisticsPolicy::class, 'view']);
    }
}
