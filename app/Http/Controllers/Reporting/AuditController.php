<?php

namespace App\Http\Controllers\Reporting;

use App\Data\Reporting\AuditFilters;
use App\Http\Controllers\Controller;
use App\Queries\Reporting\AuditLogList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OPERATOR-07-03 — Log Audit (W155). BR-RPT-04, BR-RPT-05 — satu controller untuk Operator dan
 * peneliti; yang membedakan keduanya cakupan `AuditScope`, bukan tampilan.
 */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $filters = AuditFilters::fromRequest($request);

        return Inertia::render('Reporting/AuditPage', [
            ...AuditLogList::run($user, $filters),
            'filters' => $filters,
        ]);
    }
}
