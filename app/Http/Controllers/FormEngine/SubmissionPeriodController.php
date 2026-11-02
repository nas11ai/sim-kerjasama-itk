<?php

namespace App\Http\Controllers\FormEngine;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Period\SubmissionPeriodList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periode pengajuan (OPERATOR-09-01). Route dijaga `can:periods.manage` (§4.4 K1).
 */
class SubmissionPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'status' => $request->string('status')->value(),
            'year' => $request->string('year')->value(),
        ];

        return Inertia::render('FormEngine/PeriodIndexPage', [
            'filters' => $filters,
            ...SubmissionPeriodList::run($filters),
            // W69 — "Pengajuan Masuk" menaut ke Semua Pengajuan hanya bagi yang bisa membukanya.
            'can' => ['viewSubmissions' => $viewer->can('submissions.view-all')],
        ]);
    }
}
