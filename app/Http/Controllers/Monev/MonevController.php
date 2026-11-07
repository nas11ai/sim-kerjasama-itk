<?php

namespace App\Http\Controllers\Monev;

use App\Actions\FormSubmission\StartChildSubmission;
use App\Http\Controllers\Controller;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Monev\MonevCycles;
use App\Queries\Monev\ResearcherMonevList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar Monev peneliti (RESEARCHER-05-01) dan memulai laporan satu siklus. `store` dijaga
 * Policy `startMonevReport` di route; gerbang siklusnya di `StartChildSubmission`.
 */
class MonevController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return Inertia::render('Monev/IndexPage', [
            'submissions' => ResearcherMonevList::run($viewer),
        ]);
    }

    public function store(Request $request, FormSubmission $submission, FormPhaseDetail $detail): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        // BR-MON-03 — pemanggilan kedua mengembalikan laporan yang sama (MON-CYCLE-06).
        StartChildSubmission::run($submission, $detail, $actor);

        return redirect()->route('monev.index')
            ->with('success', 'Laporan '.MonevCycles::nameOf($detail).' dibuat.');
    }
}
