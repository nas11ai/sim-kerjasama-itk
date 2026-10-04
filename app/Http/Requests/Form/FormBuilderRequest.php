<?php

namespace App\Http\Requests\Form;

use App\Data\Request\EvaluationFieldInput;
use App\Data\Request\EvaluationFormInput;
use App\Data\Request\FormBuilderFieldInput;
use App\Data\Request\FormBuilderInput;
use App\Data\Request\FormBuilderPeriodInput;
use App\Data\Request\FormBuilderPhaseInput;
use App\Models\FormAccessControl;
use Illuminate\Foundation\Http\FormRequest;

/** Wizard enam langkah; tiap tingkat bersarang divalidasi supaya masukan cacat bukan 500. */
class FormBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [...$this->formRules(), ...$this->phaseRules(), ...$this->evaluationRules(), ...$this->periodRules()];
    }

    /** @return array<string, string> */
    private function formRules(): array
    {
        return [
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.form_type_id' => 'required|integer|exists:form_types,id',
            'form.is_active' => 'boolean',
            'form.fields' => 'array',
            'form.fields.*.field_type_id' => 'required|integer|exists:field_types,id',
            'form.fields.*.label' => 'required|string|max:255',
            'form.fields.*.is_required' => 'boolean',
            'form.fields.*.options' => 'array',
            'form.fields.*.options.*.label' => 'required|string|max:255',
            'access_controls' => 'required|array|min:1',
            'access_controls.*.permission' => 'required|string|in:'.implode(',', FormAccessControl::ALLOWED_PERMISSIONS),
            // ADR 0013 — sama dengan layar kontrol akses: organisasi aktif bertipe apa pun.
            'access_controls.*.study_program_id' => 'required|integer|exists:organizations,id,is_active,1',
        ];
    }

    /** @return array<string, string> */
    private function phaseRules(): array
    {
        return [
            'phase.use_existing' => 'required|boolean',
            'phase.existing_phase_id' => 'nullable|required_if_accepted:phase.use_existing|integer|exists:form_phases,id',
            'phase.new_phase_title' => 'nullable|required_if_declined:phase.use_existing|string|max:255',
            'phase.new_phase_description' => 'nullable|string',
            'phase.phase_type_id' => 'required|integer|exists:phase_types,id',
            'phase.needs_review' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    private function evaluationRules(): array
    {
        return [
            'evaluation_forms' => 'nullable|array',
            'evaluation_forms.*.title' => 'required|string|max:255',
            'evaluation_forms.*.description' => 'nullable|string',
            'evaluation_forms.*.is_required' => 'boolean',
            'evaluation_forms.*.fields' => 'required|array|min:1',
            'evaluation_forms.*.fields.*.field_type_id' => 'required|integer|exists:field_types,id',
            'evaluation_forms.*.fields.*.label' => 'required|string|max:255',
            'evaluation_forms.*.fields.*.description' => 'nullable|string',
            'evaluation_forms.*.fields.*.is_required' => 'boolean',
            'evaluation_forms.*.fields.*.options' => 'array',
            'evaluation_forms.*.fields.*.options.*.label' => 'required|string|max:255',
            'evaluation_forms.*.fields.*.options.*.value' => 'nullable|string|max:255',
        ];
    }

    /** @return array<string, string> */
    private function periodRules(): array
    {
        $useExisting = 'submission_period.use_existing';

        return [
            $useExisting => 'required|boolean',
            'submission_period.existing_period_id' => "nullable|required_if_accepted:{$useExisting}|integer"
                .'|exists:submission_periods,id',
            'submission_period.new_period_name' => "nullable|required_if_declined:{$useExisting}|string|max:255",
            // Periode tanpa tanggal ditolak Action-nya, untuk periode baru maupun yang ada.
            'submission_period.dates' => 'nullable|array',
            // Satu tanggal per label, selaras layar periode; setiap tanggal batas akhir.
            'submission_period.dates.*.label_id' => 'required|integer|distinct|exists:submission_date_labels,id',
            'submission_period.dates.*.date' => 'required|date',
        ];
    }

    public function toInput(): FormBuilderInput
    {
        return new FormBuilderInput(
            title: $this->string('form.title')->value(),
            description: $this->optionalString('form.description'),
            formTypeId: $this->integer('form.form_type_id'),
            isActive: $this->boolean('form.is_active', true),
            fields: array_map($this->formField(...), $this->indexesOf('form.fields')),
            accessControls: array_map(fn (int|string $i): array => [
                'permission' => $this->string("access_controls.{$i}.permission")->value(),
                'organizationId' => $this->integer("access_controls.{$i}.study_program_id"),
            ], $this->indexesOf('access_controls')),
            phase: $this->phase(),
            evaluationForms: array_map($this->evaluationForm(...), $this->indexesOf('evaluation_forms')),
            period: $this->period(),
        );
    }

    private function formField(int|string $i): FormBuilderFieldInput
    {
        $key = "form.fields.{$i}";

        return new FormBuilderFieldInput(
            fieldTypeId: $this->integer("{$key}.field_type_id"),
            label: $this->string("{$key}.label")->value(),
            isRequired: $this->boolean("{$key}.is_required"),
            options: array_map(
                fn (int|string $o): string => $this->string("{$key}.options.{$o}.label")->value(),
                $this->indexesOf("{$key}.options"),
            ),
        );
    }

    private function phase(): FormBuilderPhaseInput
    {
        return new FormBuilderPhaseInput(
            existingPhaseId: $this->boolean('phase.use_existing') ? $this->integer('phase.existing_phase_id') : null,
            newTitle: $this->string('phase.new_phase_title')->value(),
            newDescription: $this->optionalString('phase.new_phase_description'),
            phaseTypeId: $this->integer('phase.phase_type_id'),
            needsReview: $this->boolean('phase.needs_review'),
        );
    }

    private function evaluationForm(int|string $i): EvaluationFormInput
    {
        $key = "evaluation_forms.{$i}";

        return new EvaluationFormInput(
            title: $this->string("{$key}.title")->value(),
            description: $this->optionalString("{$key}.description"),
            isRequired: $this->boolean("{$key}.is_required", true),
            fields: array_map(fn (int|string $f): EvaluationFieldInput => new EvaluationFieldInput(
                fieldTypeId: $this->integer("{$key}.fields.{$f}.field_type_id"),
                label: $this->string("{$key}.fields.{$f}.label")->value(),
                description: $this->optionalString("{$key}.fields.{$f}.description"),
                isRequired: $this->boolean("{$key}.fields.{$f}.is_required"),
                options: $this->evaluationOptions("{$key}.fields.{$f}.options"),
            ), $this->indexesOf("{$key}.fields")),
        );
    }

    /** @return list<array{label: string, value: string}> */
    private function evaluationOptions(string $key): array
    {
        return array_map(function (int|string $o) use ($key): array {
            $label = $this->string("{$key}.{$o}.label")->value();

            // Nilai opsi jatuh ke labelnya bila Operator tidak mengisinya.
            return ['label' => $label, 'value' => $this->optionalString("{$key}.{$o}.value") ?? $label];
        }, $this->indexesOf($key));
    }

    private function period(): FormBuilderPeriodInput
    {
        $useExisting = $this->boolean('submission_period.use_existing');

        return new FormBuilderPeriodInput(
            existingPeriodId: $useExisting ? $this->integer('submission_period.existing_period_id') : null,
            newName: $this->string('submission_period.new_period_name')->value(),
            dates: $useExisting ? [] : array_map(fn (int|string $i): array => [
                'labelId' => $this->integer("submission_period.dates.{$i}.label_id"),
                'date' => $this->string("submission_period.dates.{$i}.date")->value(),
            ], $this->indexesOf('submission_period.dates')),
        );
    }

    /** @return list<int|string> */
    private function indexesOf(string $key): array
    {
        return array_keys($this->array($key));
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->value() : null;
    }
}
