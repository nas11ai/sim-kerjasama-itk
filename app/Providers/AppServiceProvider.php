<?php

namespace App\Providers;

use App\Models\FormSubmission;
use App\Policies\BudgetLineItemPolicy;
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
     * Ability yang menyandang invarian domain, bukan sekadar kewenangan.
     *
     * BR-SM-04 — submit dan resubmit adalah tindakan `submitted_by` mengikat dirinya
     * sendiri secara kontraktual. Super Admin boleh mengurus apa pun, tapi ia tidak
     * bisa *menjadi* orang lain: membiarkan Gate::before melewatinya akan membuat
     * record menyatakan seorang peneliti menyubmit padahal bukan dia.
     *
     * Kalau admin memang harus mengajukan untuk orang lain, jalurnya transfer
     * kepemilikan (BR-SM-09) — yang meninggalkan jejak dan membuat `submitted_by`
     * tetap jujur. Lihat ADR 0015.
     *
     * BR-BUD-02 dan BR-BUD-09 — `update-budget` menyusul dengan alasan yang sama.
     * BR-BUD-09 menyebut daftar tertutup (`submitted_by` dan `co_investigator`), dan
     * angka anggaran pada pengajuan yang sudah diputuskan adalah dasar pencairan dana.
     * Koreksi setelah putusan menempuh transisi status yang meninggalkan jejak, bukan
     * suntingan senyap. Register #24.
     *
     * @var list<string>
     */
    private const INVARIANT_ABILITIES = ['submit', 'resubmit', 'update-budget'];

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
            if (in_array($ability, self::INVARIANT_ABILITIES, true)) {
                return null;
            }

            return $user->hasRole('Super Admin') ? true : null;
        });
        Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);
        Gate::define('update-budget', [BudgetLineItemPolicy::class, 'update']);
    }
}
