<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\DeleteFormPhase;
use App\Actions\Form\SetFormPhaseActive;
use App\Data\View\FormEngine\FormHeaderData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormPhaseActiveRequest;
use App\Models\Form;
use App\Models\FormPhase;
use App\Queries\Form\FormPhaseCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab Phases Form Builder (ADMIN-08-08). Route dijaga `can:schemes.manage` (W88).
 */
class FormPhaseController extends Controller
{
    use ResolvesActor;

    public function index(Request $request, Form $form): Response
    {
        return Inertia::render('FormEngine/FormEditPage', [
            'tab' => 'phases',
            'form' => FormHeaderData::fromModel($form),
            'phases' => FormPhaseCards::run($form),
            'can' => ['linkPeriod' => $request->user()?->can('periods.manage') === true],
        ]);
    }

    public function setActive(FormPhaseActiveRequest $request, Form $form, FormPhase $formPhase): RedirectResponse
    {
        abort_unless($formPhase->usesForm($form), 404);

        $active = $request->boolean('active');

        SetFormPhaseActive::run($formPhase, $active, $this->actor($request));

        return back()->with('success', $active ? 'Tahap diaktifkan.' : 'Tahap dinonaktifkan.');
    }

    // BR-FE-20 — penjaga rujukan berjalan di Action; penolakannya sampai apa adanya.
    public function destroy(Request $request, Form $form, FormPhase $formPhase): RedirectResponse
    {
        abort_unless($formPhase->usesForm($form), 404);

        DeleteFormPhase::run($formPhase, $this->actor($request));

        return redirect()->route('admin.forms.phases.index', $form)->with('success', 'Tahap dihapus.');
    }
}
