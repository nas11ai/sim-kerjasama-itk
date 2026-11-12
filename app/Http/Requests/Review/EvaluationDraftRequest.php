<?php

namespace App\Http\Requests\Review;

use App\Data\Request\EvaluationDraftInput;
use App\Data\Request\ReviewFormFieldInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Simpan draf Form Evaluasi (BR-FE-31): field urut seperti di layar. `id` field dan opsi tidak
 * dibaca — Terbitkan menyalin baris baru (BR-REV-18), jadi kiriman tak bisa menyentuh baris lain.
 * Draf boleh kosong; minimal satu field baru ditagih saat Terbitkan.
 */
class EvaluationDraftRequest extends FormRequest
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
            'fields' => 'present|array',
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
            'fields.*.options.*.label' => 'required|string|max:255',
            'fields.*.options.*.value' => 'nullable|string|max:255',
        ];
    }

    public function toInput(): EvaluationDraftInput
    {
        return new EvaluationDraftInput(array_map($this->field(...), $this->indexesOf('fields')));
    }

    private function field(int|string $i): ReviewFormFieldInput
    {
        $key = "fields.{$i}";

        return new ReviewFormFieldInput(
            id: null,
            fieldTypeId: $this->integer("{$key}.field_type_id"),
            label: $this->string("{$key}.label")->value(),
            description: $this->optionalString("{$key}.description"),
            isRequired: $this->boolean("{$key}.is_required"),
            validationRules: $this->fieldLimits("{$key}.validation_rules"),
            options: $this->has("{$key}.options") ? array_map(fn (int|string $o): array => [
                'id' => null,
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

    /** @return list<int|string> */
    private function indexesOf(string $key): array
    {
        return array_keys($this->array($key));
    }
}
