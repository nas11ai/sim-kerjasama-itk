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
 * Tab Phases Form Builder (ADMIN-08-08) dan editor tahap mandiri (ADMIN-08-09). Route dijaga
 * `can:schemes.manage` (W88); tab yang bersarang di Form menolak tahap Form lain (404, W92).
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

    public function create(): Response
    {
        return $this->editor(null);
    }

    // BR-FE-07 — tahap baru belum dipakai periode mana pun; W95 — lanjut ke editornya.
    public function store(FormPhaseRequest $request): RedirectResponse
    {
        $phase = CreateFormPhase::run($request->toInput(), $this->actor($request));

        return redirect()->route('admin.phases.edit', $phase)
            ->with('success', 'Tahap dibuat. Tambahkan form evaluasi untuk detail yang direview.');
    }

    public function edit(FormPhase $formPhase): Response
    {
        return $this->editor($formPhase);
    }

    // BR-FE-20 — disinkronkan per id; penjaga detail berjalan di Action (W97, BR-FE-22, BR-FE-28).
    public function update(FormPhaseRequest $request, FormPhase $formPhase): RedirectResponse
    {
        ReviseFormPhase::run($formPhase, $request->toInput(), $this->actor($request));

        return redirect()->route('admin.phases.edit', $formPhase)->with('success', 'Tahap diperbarui.');
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
    private function editor(?FormPhase $phase): Response
    {
        return Inertia::render('FormEngine/PhaseEditPage', [
            'phase' => PhaseEdit::run($phase),
            'accessOptions' => PhaseAccessOptions::run($phase),
            'phaseTypeOptions' => PhaseTypeOptions::run(),
            'evaluation' => $phase === null ? [] : PhaseEvaluationForms::run($phase),
            'fieldTypeOptions' => $phase === null ? [] : FieldTypeOptions::run(),
        ]);
    }
}
