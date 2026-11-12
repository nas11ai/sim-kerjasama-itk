<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\Form\NormalisePhaseDetailOrder;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseDraftInput;
use App\Data\Request\PhaseStructureInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Data\View\FormEngine\PhaseDetailEditData;
use App\Data\View\FormEngine\PhaseEditData;
use App\Data\View\FormEngine\StepEvaluationFormData;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseVersion;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi editor tahap ADMIN-08-09. BR-FE-33 — langkah dibaca dari draf bila ada, selain itu dari
 * versi terbaru (BR-FE-32); label Form, aturan akses, tipe, dan Form Evaluasi dimuat sekali untuk
 * semua langkah, tanpa kueri per langkah.
 *
 * @method static PhaseEditData run(?FormPhase $phase)
 */
class PhaseEdit implements ChangesNoState
{
    use AsAction;

    public function handle(?FormPhase $phase): PhaseEditData
    {
        // W94 — mode buat: langkah Pengajuan kosong pertamanya dibuat layar dari pilihan akses pertama.
        if ($phase === null) {
            return new PhaseEditData(
                id: null,
                title: '',
                description: null,
                isActive: true,
                steps: [],
                hasDraft: false,
                versionLabel: null,
                versions: [],
                impact: ['nextLabel' => FormPhaseVersion::labelFor(1), 'pinnedCohorts' => 0],
            );
        }

        $draft = $phase->draft()->first();
        $latest = $phase->latestVersion()->first();
        $steps = match (true) {
            $draft !== null => PhaseDraftInput::fromPayload($draft->payload)->steps,
            $latest !== null => PhaseStructureInput::fromVersion($latest)->details,
            default => [],
        };

        return new PhaseEditData(
            id: $phase->id,
            title: $phase->title,
            description: $phase->description,
            isActive: $phase->is_active,
            steps: $this->steps($steps),
            hasDraft: $draft !== null,
            versionLabel: $latest?->label(),
            versions: $this->versions($phase),
            impact: PhasePublishImpact::run($phase),
        );
    }

    /**
     * @param  list<FormPhaseDetailInput>  $steps
     * @return list<PhaseDetailEditData>
     */
    private function steps(array $steps): array
    {
        $accessIds = array_map(fn (FormPhaseDetailInput $step): int => $step->formAccessControlId, $steps);
        $accesses = FormAccessControl::query()->whereIn('id', $accessIds)->with('form:id,title')->get()->keyBy('id');
        $labels = $this->accessLabels($accesses);
        $types = PhaseType::query()->whereIn('id', array_map(fn (FormPhaseDetailInput $step): int => $step->phaseTypeId, $steps))
            ->get(['id', 'name'])->keyBy('id');
        $forms = $this->evaluationForms($steps);
        $orders = $this->orders($accessIds, $accesses);
        $data = [];

        foreach ($steps as $index => $step) {
            $data[] = new PhaseDetailEditData(
                order: $orders[$index],
                formAccessControlId: $step->formAccessControlId,
                formTitle: $accesses->get($step->formAccessControlId)?->form->title ?? '',
                accessLabel: $labels[$step->formAccessControlId] ?? '',
                phaseTypeId: $step->phaseTypeId,
                phaseTypeName: $types->get($step->phaseTypeId)->name ?? '',
                needsReview: $step->needsReview,
                deadlineType: $step->deadlineType->value,
                deadlineCycle: $step->deadlineCycle,
                deadlineLabel: $step->deadlineType->stepLabel($step->deadlineCycle),
                evaluationForms: $this->attachments($step->evaluationForms, $forms),
            );
        }

        return $data;
    }

    /**
     * W96 — nomor urut diturunkan dari Form, sama dengan saat terbit; aturan akses yang sudah
     * dihapus sesudah draf disimpan bernomor 0.
     *
     * @param  list<int>  $accessIds
     * @param  Collection<int, FormAccessControl>  $accesses
     * @return list<int> sejajar dengan `$accessIds`
     */
    private function orders(array $accessIds, Collection $accesses): array
    {
        $known = NormalisePhaseDetailOrder::run(array_values(array_filter($accessIds, fn (int $id): bool => $accesses->has($id))));
        $orders = [];

        foreach ($accessIds as $id) {
            $orders[] = $accesses->has($id) ? array_shift($known) ?? 0 : 0;
        }

        return $orders;
    }

    /**
     * @param  list<StepEvaluationFormInput>  $attached
     * @param  Collection<int, ReviewEvaluationForm>  $forms
     * @return list<StepEvaluationFormData>
     */
    private function attachments(array $attached, Collection $forms): array
    {
        return array_map(fn (StepEvaluationFormInput $a): StepEvaluationFormData => new StepEvaluationFormData(
            formId: $a->reviewEvaluationFormId,
            title: $forms->get($a->reviewEvaluationFormId)->title ?? '',
            versionLabel: $forms->get($a->reviewEvaluationFormId)?->latestVersion?->label() ?? '',
            isRequired: $a->isRequired,
            isActive: $a->isActive,
        ), $attached);
    }

    /**
     * "{permissionLabel} · {organizationLabel}" per id aturan akses, dari FormAccessRules.
     *
     * @param  Collection<int, FormAccessControl>  $accesses
     * @return array<int, string>
     */
    private function accessLabels(Collection $accesses): array
    {
        $formIds = array_values(array_unique($accesses->map(fn (FormAccessControl $access): int => $access->form_id)->all()));
        $labels = [];

        foreach (FormAccessRules::forForms($formIds) as $rules) {
            foreach ($rules as $rule) {
                $labels[$rule->id] = "{$rule->permissionLabel} · {$rule->organizationLabel}";
            }
        }

        return $labels;
    }

    /**
     * @param  list<FormPhaseDetailInput>  $steps
     * @return Collection<int, ReviewEvaluationForm>
     */
    private function evaluationForms(array $steps): Collection
    {
        $ids = array_merge(...array_map(
            fn (FormPhaseDetailInput $step): array => array_map(fn (StepEvaluationFormInput $a): int => $a->reviewEvaluationFormId, $step->evaluationForms),
            $steps,
        ));

        return ReviewEvaluationForm::query()->whereIn('id', $ids)->with('latestVersion')->get(['id', 'title'])->keyBy('id');
    }

    /** @return list<array{label: string, publishedAt: string, publishedBy: string|null, cohortCount: int}> */
    private function versions(FormPhase $phase): array
    {
        return array_values($phase->versions()
            ->reorder('number', 'desc')
            ->with('publisher:id,name')
            ->withCount('pins')
            ->get()
            ->map(fn (FormPhaseVersion $version): array => [
                'label' => $version->label(),
                'publishedAt' => $version->published_at->toIso8601String(),
                'publishedBy' => $version->publisher?->name,
                'cohortCount' => $version->pins_count,
            ])
            ->all());
    }
}
