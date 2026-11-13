<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Review\ApplyLatestEvaluationVersion;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\EvaluationFormPin;
use App\Models\SubmissionPeriod;
use App\Queries\Period\CohortEvaluationVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-REV-19, G2-W5 — Versi Formulir Evaluasi per angkatan, dibuka dari baris tabel Periode
 * (OPERATOR-09-01). Route dijaga `can:periods.manage`.
 */
class CohortEvaluationVersionController extends Controller
{
    use ResolvesActor;

    public function index(SubmissionPeriod $submissionPeriod): Response
    {
        return Inertia::render('FormEngine/PeriodEvaluationVersionsPage', [
            'period' => ['id' => $submissionPeriod->id, 'name' => $submissionPeriod->name],
            'rows' => CohortEvaluationVersions::run($submissionPeriod),
        ]);
    }

    public function apply(Request $request, EvaluationFormPin $evaluationFormPin): RedirectResponse
    {
        $reopened = ApplyLatestEvaluationVersion::run($evaluationFormPin, $this->actor($request));
        $label = $evaluationFormPin->refresh()->reviewEvaluationFormVersion->label();

        return back()->with('success', "{$label} diterapkan; {$reopened} penilaian dibuka ulang.");
    }
}
