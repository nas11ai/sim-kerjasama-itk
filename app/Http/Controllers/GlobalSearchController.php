<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use App\Queries\Search\GlobalSearch;
use Illuminate\Http\JsonResponse;

/** K2 (W171) — route JSON internal untuk command palette topbar, bersesi sama dengan halaman. */
class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request): JsonResponse
    {
        return response()->json(GlobalSearch::run($request->viewer(), $request->term()));
    }
}
