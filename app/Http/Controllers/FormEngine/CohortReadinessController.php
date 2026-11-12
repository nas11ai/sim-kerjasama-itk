<?php

namespace App\Http\Controllers\FormEngine;

use App\Data\View\Period\CohortReadinessData;
use App\Http\Controllers\Controller;
use App\Models\SubmissionPeriod;
use App\Queries\Period\CohortReadiness;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-FE-36 — Kesiapan Angkatan satu periode, dibuka dari baris tabel Periode (OPERATOR-09-01).
 * Route dijaga `can:periods.manage`.
 */
class CohortReadinessController extends Controller
{
    public function index(SubmissionPeriod $submissionPeriod): Response
    {
        $cohorts = collect(CohortReadiness::run([$submissionPeriod->id]))
            ->sortBy(fn (CohortReadinessData $cohort): string => $cohort->phaseTitle)
            ->values()
            ->all();

        return Inertia::render('FormEngine/PeriodReadinessPage', [
            'period' => ['id' => $submissionPeriod->id, 'name' => $submissionPeriod->name],
            'cohorts' => $cohorts,
        ]);
    }
}
