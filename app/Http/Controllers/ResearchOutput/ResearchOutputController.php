<?php

namespace App\Http\Controllers\ResearchOutput;

use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Queries\ResearchOutput\ResearcherOutputList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Layar luaran penelitian (ux/ia/06_research_output). Boleh-tidaknya menulis: `OutputWriteAccess`. */
class ResearchOutputController extends Controller
{
    use ResolvesActor;

    public function index(Request $request): Response
    {
        return Inertia::render('ResearchOutput/IndexPage', [
            'groups' => ResearcherOutputList::run($this->actor($request)),
        ]);
    }
}
