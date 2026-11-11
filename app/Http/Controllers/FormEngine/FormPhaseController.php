<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\DeleteFormPhase;
use App\Actions\Form\LinkPhaseToPeriod;
use App\Actions\Form\ReviseFormPhase;
use App\Actions\Form\SetFormPhaseActive;
use App\Data\View\FormEngine\FormHeaderData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormPhaseActiveRequest;
use App\Http\Requests\Form\FormPhaseRequest;
use App\Http\Requests\Form\PhasePeriodRequest;
use App\Models\Form;
use App\Models\FormPhase;
use App\Models\SubmissionPeriod;
use App\Queries\Form\FieldTypeOptions;
use App\Queries\Form\FormPhaseCards;
use App\Queries\Form\PhaseAccessOptions;
use App\Queries\Form\PhaseEdit;
use App\Queries\Form\PhaseEvaluationForms;
use App\Queries\Form\PhaseTypeOptions;
use App\Queries\Period\PeriodScheduleGaps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab Phases Form Builder (ADMIN-08-08) dan editor tahap (ADMIN-08-09). Route dijaga
 * `can:schemes.manage` (W88); tahap yang tidak memakai Form di URL → 404 (W92).
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

    public function create(Form $form): Response
    {
        return $this->editor($form, null);
    }

    // BR-FE-07 — tahap baru belum dipakai periode mana pun; W95 — lanjut ke editornya.
    public function store(FormPhaseRequest $request, Form $form): RedirectResponse
    {
        $phase = CreateFormPhase::run($request->toInput(), $this->actor($request));

        // W92 — tahap yang hanya memakai Form lain tidak punya editor di bawah Form ini.
        $route = $phase->usesForm($form)
            ? redirect()->route('admin.forms.phases.edit', [$form, $phase])
            : redirect()->route('admin.forms.phases.index', $form);

        return $route->with('success', 'Tahap dibuat. Tambahkan form evaluasi untuk detail yang direview.');
    }

    public function edit(Form $form, FormPhase $formPhase): Response
    {
        abort_unless($formPhase->usesForm($form), 404);

        return $this->editor($form, $formPhase);
    }

    // BR-FE-20 — disinkronkan per id; penjaga detail berjalan di Action (W97, BR-FE-22).
    public function update(FormPhaseRequest $request, Form $form, FormPhase $formPhase): RedirectResponse
    {
        abort_unless($formPhase->usesForm($form), 404);

        ReviseFormPhase::run($formPhase, $request->toInput(), $this->actor($request));

        return redirect()->route('admin.forms.phases.index', $form)->with('success', 'Tahap diperbarui.');
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

    // BR-FE-26 (ADR 0019) — penautan longgar; pesan menyebut tanggal kalender yang masih kurang.
    public function linkPeriod(PhasePeriodRequest $request, Form $form, FormPhase $formPhase): RedirectResponse
    {
        abort_unless($formPhase->usesForm($form), 404);

        $period = SubmissionPeriod::query()->findOrFail($request->integer('submission_period_id'));

        LinkPhaseToPeriod::run($formPhase, $period, $this->actor($request));

        $missing = PeriodScheduleGaps::missingCount($period, $formPhase);

        return back()->with('success', $missing > 0
            ? "Tahap dihubungkan ke periode {$period->name}. Periode {$period->name} belum punya {$missing} tanggal yang dibutuhkan tahap ini."
            : "Tahap dihubungkan ke periode {$period->name}.");
    }

    // `$phase` null = mode buat (W94); layar yang sama, state-nya di child ber-key. Form evaluasi
    // butuh detail tersimpan, jadi mode buat belum punya bagiannya (W95).
    private function editor(Form $form, ?FormPhase $phase): Response
    {
        return Inertia::render('FormEngine/PhaseEditPage', [
            'form' => FormHeaderData::fromModel($form),
            'phase' => PhaseEdit::run($form, $phase),
            'accessOptions' => PhaseAccessOptions::run($form, $phase),
            'phaseTypeOptions' => PhaseTypeOptions::run(),
            'evaluation' => $phase === null ? [] : PhaseEvaluationForms::run($phase),
            'fieldTypeOptions' => $phase === null ? [] : FieldTypeOptions::run(),
        ]);
    }
}
