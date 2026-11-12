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
use App\Queries\Form\PhaseAccessOptions;
use App\Queries\Form\PhaseEdit;
use App\Queries\Form\PhaseEvaluationForms;
use App\Queries\Form\PhaseLibrary;
use App\Queries\Form\PhaseTypeOptions;
use App\Queries\Period\PeriodScheduleGaps;
use App\Queries\Review\PublishedEvaluationFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pustaka fase (ADMIN-08-08) dan editor tahap mandiri (ADMIN-08-09). Route dijaga
 * `can:schemes.manage` (W88); tahap tidak lagi bersarang di Form (R2, BR-FE-28).
 */
class FormPhaseController extends Controller
{
    use ResolvesActor;

    // `?form=` menapis tahap yang langkah mana pun memakai Form itu; id tak dikenal 404.
    public function index(Request $request): Response
    {
        $form = $this->requestedForm($request);

        return Inertia::render('FormEngine/PhaseIndexPage', [
            'phases' => PhaseLibrary::run($form),
            'filterForm' => $form === null ? null : FormHeaderData::fromModel($form),
            'can' => ['linkPeriod' => $request->user()?->can('periods.manage') === true],
        ]);
    }

    // Dari pustaka bertapis: Form itu menjadi akses bawaan langkah Pengajuan.
    public function create(Request $request): Response
    {
        return $this->editor(null, $this->requestedForm($request));
    }

    // BR-FE-07 — tahap baru belum dipakai periode mana pun; W95 — lanjut ke editornya.
    public function store(FormPhaseRequest $request): RedirectResponse
    {
        $phase = CreateFormPhase::run($request->toInput(), $this->actor($request));

        return redirect()->route('admin.phases.edit', ['formPhase' => $phase, ...$this->returnTo($request)])
            ->with('success', 'Tahap dibuat. Tambahkan form evaluasi untuk detail yang direview.');
    }

    public function edit(Request $request, FormPhase $formPhase): Response
    {
        return $this->editor($formPhase, $this->requestedForm($request));
    }

    // BR-FE-20 — disinkronkan per id; penjaga detail berjalan di Action (W97, BR-FE-22, BR-FE-28).
    public function update(FormPhaseRequest $request, FormPhase $formPhase): RedirectResponse
    {
        ReviseFormPhase::run($formPhase, $request->toInput(), $this->actor($request));

        return redirect()->route('admin.phases.index', $this->returnTo($request))->with('success', 'Tahap diperbarui.');
    }

    public function setActive(FormPhaseActiveRequest $request, FormPhase $formPhase): RedirectResponse
    {
        $active = $request->boolean('active');

        SetFormPhaseActive::run($formPhase, $active, $this->actor($request));

        return back()->with('success', $active ? 'Tahap diaktifkan.' : 'Tahap dinonaktifkan.');
    }

    // BR-FE-20 — penjaga rujukan berjalan di Action; penolakannya sampai apa adanya.
    public function destroy(Request $request, FormPhase $formPhase): RedirectResponse
    {
        DeleteFormPhase::run($formPhase, $this->actor($request));

        // B5 — kembali ke halaman asal (pustaka bertapis), sama dengan aktif/nonaktif.
        return back()->with('success', 'Tahap dihapus.');
    }

    // BR-FE-26 (ADR 0019) — penautan longgar; pesan menyebut tanggal kalender yang masih kurang.
    public function linkPeriod(PhasePeriodRequest $request, FormPhase $formPhase): RedirectResponse
    {
        $period = SubmissionPeriod::query()->findOrFail($request->integer('submission_period_id'));

        LinkPhaseToPeriod::run($formPhase, $period, $this->actor($request));

        $missing = PeriodScheduleGaps::missingCount($period, $formPhase);

        return back()->with('success', $missing > 0
            ? "Tahap dihubungkan ke periode {$period->name}. Periode {$period->name} belum punya {$missing} tanggal yang dibutuhkan tahap ini."
            : "Tahap dihubungkan ke periode {$period->name}.");
    }

    // `$phase` null = mode buat (W94); layar yang sama, state-nya di child ber-key. Form evaluasi
    // butuh detail tersimpan, jadi mode buat belum punya bagiannya (W95). `$filter` = Form tapis
    // pustaka asal: di mode buat menjadi akses bawaan, di keduanya tujuan kembali (B5).
    private function editor(?FormPhase $phase, ?Form $filter = null): Response
    {
        return Inertia::render('FormEngine/PhaseEditPage', [
            'returnForm' => $filter?->id,
            'phase' => PhaseEdit::run($phase),
            'accessOptions' => PhaseAccessOptions::run($phase, $filter),
            'phaseTypeOptions' => PhaseTypeOptions::run(),
            'evaluation' => $phase === null ? [] : PhaseEvaluationForms::run($phase),
            // BR-FE-30 — isi Form Evaluasi disusun di pustakanya; di sini hanya dipasang.
            'evaluationOptions' => $phase === null ? [] : PublishedEvaluationFormOptions::run(),
        ]);
    }

    // `?form=` = id Form; id tak dikenal 404.
    private function requestedForm(Request $request): ?Form
    {
        return $request->filled('form') ? Form::query()->findOrFail($request->integer('form')) : null;
    }

    /** @return array{form?: int} parameter tapis pustaka yang dibawa kiriman editor (B5). */
    private function returnTo(FormPhaseRequest $request): array
    {
        return $request->filled('form') ? ['form' => $request->integer('form')] : [];
    }
}
