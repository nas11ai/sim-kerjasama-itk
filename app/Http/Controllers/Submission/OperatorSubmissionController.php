<?php

namespace App\Http\Controllers\Submission;

use App\Http\Controllers\Controller;
use App\Queries\Submission\OperatorSubmissionList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar pengajuan sisi Operator (OPERATOR-02-01, 02-02).
 */
class OperatorSubmissionController extends Controller
{
    // BR-SM-07 — view-all melihat seluruh pengajuan terkirim; draft belum ada bagi Operator.
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'status' => $request->string('status')->value(),
            'period' => $request->string('period')->value(),
            'scheme' => $request->string('scheme')->value(),
        ];

        return Inertia::render('Submission/ManageIndexPage', [
            'filters' => $filters,
            ...OperatorSubmissionList::run($filters),
        ]);
    }
}
