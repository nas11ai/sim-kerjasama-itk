<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\Dashboard\DashboardOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SHARED-02-01..04 — satu dasbor untuk semua peran (§4.4 K4); section disusun
 * DashboardOverview menurut permission, bukan nama peran.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('Dashboard/IndexPage', DashboardOverview::run($user));
    }
}
