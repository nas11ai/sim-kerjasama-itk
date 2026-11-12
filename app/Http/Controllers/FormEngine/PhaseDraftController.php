<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\DiscardPhaseDraft;
use App\Actions\Form\PublishPhaseDraft;
use App\Actions\Form\SavePhaseDraft;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\PhaseDraftRequest;
use App\Models\FormPhase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** BR-FE-33 — draf struktur tahap: simpan, buang, terbitkan menjadi versi berikutnya (BR-FE-32). */
class PhaseDraftController extends Controller
{
    use ResolvesActor;

    public function update(PhaseDraftRequest $request, FormPhase $formPhase): RedirectResponse
    {
        SavePhaseDraft::run($formPhase, $request->toSteps(), $this->actor($request));

        return back()->with('success', 'Draf disimpan.');
    }

    public function destroy(Request $request, FormPhase $formPhase): RedirectResponse
    {
        DiscardPhaseDraft::run($formPhase, $this->actor($request));

        return back()->with('success', 'Draf dibuang.');
    }

    public function publish(Request $request, FormPhase $formPhase): RedirectResponse
    {
        $version = PublishPhaseDraft::run($formPhase, $this->actor($request));

        return back()->with('success', "{$version->label()} diterbitkan.");
    }
}
