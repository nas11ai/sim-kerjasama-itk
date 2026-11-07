<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Monev\MonevCycleData;
use App\Data\View\Monev\MonevReportHeaderData;
use App\Data\View\Submission\Wizard\WizardFieldData;
use App\Enums\MonevCycleState;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\SubmissionAnswers;
use App\Queries\Submission\WizardFieldFactory;
use App\Support\PhaseAccessGate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Props Monev/ReportFormPage (RESEARCHER-05-02). Semua field Form laporan tanpa langkah;
 * keadaan siklus dan kelayakan tombol dihitung di sini, bukan di Vue.
 *
 * @phpstan-type ReportForm array{report: MonevReportHeaderData, cycle: MonevCycleData, fields: list<WizardFieldData>, phaseAccess: array{is_open: bool, deadline: string|null, form_phase_detail_id: int|null, override: array{id: int, status: string, expires_at: string|null, reason: string|null}|null}, can: array{save: bool, submit: bool}}
 *
 * @method static ReportForm run(User $viewer, FormSubmission $report)
 */
class MonevReportForm implements ChangesNoState
{
    use AsAction;

    /** @return ReportForm */
    public function handle(User $viewer, FormSubmission $report): array
    {
        $parent = $report->parent()->with(['form:id,title', 'scheme:id,name', 'formPhaseDetail'])->firstOrFail();
        $cycle = self::cycleOf($parent, $report);
        $save = $cycle->state === MonevCycleState::Open && $viewer->can('update', $report);

        return [
            'report' => MonevReportHeaderData::of($report, $parent),
            'cycle' => $cycle,
            'fields' => $this->fields($report),
            // BR-FE-16 — peneliti melihat perpanjangan dan batasnya, bukan alasannya.
            'phaseAccess' => PhaseAccessGate::presentFor($report, $report->formPhaseDetail, false),
            'can' => ['save' => $save, 'submit' => $save && $viewer->can('submit', $report)],
        ];
    }

    /** Siklus laporan ini dalam definisi siklus induknya (W125). */
    public static function cycleOf(FormSubmission $parent, FormSubmission $report): MonevCycleData
    {
        return collect(MonevCycles::run($parent))
            ->firstOrFail(fn (MonevCycleData $cycle): bool => $cycle->detailId === $report->form_phase_detail_id);
    }

    /** @return list<WizardFieldData> */
    private function fields(FormSubmission $report): array
    {
        SubmissionAnswers::run($report);
        $responses = $report->formFieldResponses->keyBy('form_field_id');

        return array_values($report->form->formFields
            ->map(fn (FormField $field): WizardFieldData => WizardFieldFactory::of($report, $field, $responses->get($field->id)))
            ->all());
    }
}
