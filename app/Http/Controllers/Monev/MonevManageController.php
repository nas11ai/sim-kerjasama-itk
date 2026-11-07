<?php

namespace App\Http\Controllers\Monev;

use App\Http\Controllers\Controller;
use App\Queries\Monev\MonevCycleBoard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar monev sisi Operator (OPERATOR-05-01, 05-02). Dijaga `can:submissions.view-all`
 * di route (W129, BR-SM-07).
 */
class MonevManageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'period' => $request->string('period')->value(),
            'scheme' => $request->string('scheme')->value(),
        ];

        return Inertia::render('Monev/ManageIndexPage', [
            'filters' => $filters,
            ...MonevCycleBoard::run($filters),
        ]);
    }
}
