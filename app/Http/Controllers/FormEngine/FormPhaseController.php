<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\DeleteFormPhase;
use App\Actions\Form\LinkPhaseToPeriod;
use App\Actions\Form\SetFormPhaseActive;
use App\Data\View\FormEngine\FormHeaderData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormPhaseActiveRequest;
use App\Http\Requests\Form\PhasePeriodRequest;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\SubmissionPeriod;
use App\Queries\Form\FormPhaseCards;
use App\Queries\Form\PhaseDeadlineGaps;
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

    // K9b — penautan longgar; pesan menyebut keadaan antara bila tenggat detail masih milik periode lain.
    public function linkPeriod(PhasePeriodRequest $request, Form $form, FormPhase $formPhase): RedirectResponse
    {
        abort_unless($formPhase->usesForm($form), 404);

        $period = SubmissionPeriod::query()->findOrFail($request->integer('submission_period_id'));

        LinkPhaseToPeriod::run($formPhase, $period, $this->actor($request));

        $gaps = PhaseDeadlineGaps::forPhases(collect([$formPhase]))[$formPhase->id] ?? [];
        $hasGap = collect($gaps)->contains(fn (array $gap): bool => $gap['period'] === $period->name);

        return back()->with('success', $hasGap
            ? "Tahap dihubungkan ke periode {$period->name}. Perbarui tenggat detailnya agar terbuka di periode ini."
            : "Tahap dihubungkan ke periode {$period->name}.");
    }
}
