<?php

namespace App\Http\Controllers;

use App\Actions\Form\CreateFormPhase;
use App\Http\Requests\Form\FormPhaseRequest;
use App\Queries\Form\FormPhaseFormOptions;
use Inertia\Inertia;

class FormPhaseController extends Controller
{
    public function create()
    {
        return Inertia::render('FormPhases/CreatePage', FormPhaseFormOptions::run());
    }

    public function store(FormPhaseRequest $request)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        CreateFormPhase::run($request->toInput(), $actor);

        return redirect()->route('admin.forms.index')
            ->with('success', 'Tahap Formulir berhasil dibuat.');
    }
}
