<?php

namespace App\Http\Requests\Review;

use App\Data\Request\ReviewEvaluationFormInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Buat dan Ubah form evaluasi reviewer. `id` field dan opsi hanya diperiksa keberadaannya di
 * sini; kepemilikannya, dan apakah detailnya direview, diperiksa Action-nya. Detailnya tidak
 * pernah dari kiriman: route `formPhaseDetail` saat buat, form yang disunting saat ubah (W100).
 */
class ReviewEvaluationFormRequest extends FormRequest
{
    private const LENGTH_RULES = ['min_length', 'max_length'];

    private const VALUE_RULES = ['min_value', 'max_value'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.id' => 'nullable|integer|exists:review_form_fields,id',
            'fields.*.field_type_id' => 'required|integer|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.validation_rules' => 'nullable|array',
            'fields.*.validation_rules.min_length' => 'nullable|integer|min:0',
            'fields.*.validation_rules.max_length' => 'nullable|integer|min:0',
            'fields.*.validation_rules.min_value' => 'nullable|numeric',
            'fields.*.validation_rules.max_value' => 'nullable|numeric',
            'fields.*.options' => 'array',
            'fields.*.options.*.id' => 'nullable|integer|exists:review_form_field_options,id',
            'fields.*.options.*.label' => 'required|string|max:255',
            'fields.*.options.*.value' => 'nullable|string|max:255',
        ];
    }

    public function toInput(?ReviewEvaluationForm $current = null): ReviewEvaluationFormInput
    {
        return new ReviewEvaluationFormInput(
            title: $this->string('title')->value(),
            description: $this->optionalString('description'),
            formPhaseDetailId: $current->form_phase_detail_id ?? $this->routeDetailId(),
            isRequired: $this->boolean('is_required', true),
            isActive: $this->boolean('is_active', true),
            fields: $this->has('fields') ? array_map($this->field(...), $this->indexesOf('fields')) : null,
        );
    }

    // Tanpa route ber-detail (layar legacy) hasilnya 0, yang ditolak AssertReviewedPhaseDetail.
    private function routeDetailId(): int
    {
        $detail = $this->route('formPhaseDetail');

        return $detail instanceof FormPhaseDetail ? $detail->id : 0;
    }

    private function field(int|string $i): ReviewFormFieldInput
    {
        $key = "fields.{$i}";

        return new ReviewFormFieldInput(
            id: $this->optionalId("{$key}.id"),
            fieldTypeId: $this->integer("{$key}.field_type_id"),
            label: $this->string("{$key}.label")->value(),
            description: $this->optionalString("{$key}.description"),
            isRequired: $this->boolean("{$key}.is_required"),
            validationRules: $this->fieldLimits("{$key}.validation_rules"),
            options: $this->has("{$key}.options") ? array_map(fn (int|string $o): array => [
                'id' => $this->optionalId("{$key}.options.{$o}.id"),
                'label' => $this->string("{$key}.options.{$o}.label")->value(),
                'value' => $this->optionalString("{$key}.options.{$o}.value"),
            ], $this->indexesOf("{$key}.options")) : null,
        );
    }

    /**
     * Hanya empat batas yang dikenal layar; kunci lain dibuang. Tanpa batas berarti null,
     * bukan objek kosong.
     *
     * @return array<string, int|float>|null
     */
    private function fieldLimits(string $key): ?array
    {
        $rules = [];

        foreach (self::LENGTH_RULES as $name) {
            if ($this->filled("{$key}.{$name}")) {
                $rules[$name] = $this->integer("{$key}.{$name}");
            }
        }

        foreach (self::VALUE_RULES as $name) {
            if ($this->filled("{$key}.{$name}")) {
                $rules[$name] = $this->float("{$key}.{$name}");
            }
        }

        return $rules === [] ? null : $rules;
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->value() : null;
    }

    private function optionalId(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }

    /** @return list<int|string> */
    private function indexesOf(string $key): array
    {
        return array_keys($this->array($key));
    }
}
