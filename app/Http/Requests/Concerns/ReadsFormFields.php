<?php

namespace App\Http\Requests\Concerns;

use App\Data\Request\FormDraftInput;
use App\Data\Request\FormFieldInput;

/**
 * BR-FE-34 — `fields` kiriman editor Form, urut seperti di layar. `id` field dan opsi hanya
 * diperiksa keberadaannya (bukan baris terhapus, B14); kepemilikannya diperiksa saat terbit.
 */
trait ReadsFormFields
{
    /** @return array<string, string> */
    protected function fieldRules(): array
    {
        return [
            'fields' => 'array',
            'fields.*.id' => 'nullable|integer|exists:form_fields,id,deleted_at,NULL',
            'fields.*.field_type_id' => 'required|integer|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.is_required' => 'boolean',
            'fields.*.options' => 'array',
            'fields.*.options.*.id' => 'nullable|integer|exists:form_field_options,id,deleted_at,NULL',
            'fields.*.options.*.label' => 'required|string|max:255',
        ];
    }

    /** @return array<string, string> */
    protected function fieldMessages(): array
    {
        return [
            'fields.*.id.exists' => 'Field ini sudah dihapus oleh penerbitan lain. Muat ulang halaman lalu coba lagi.',
            'fields.*.options.*.id.exists' => 'Opsi ini sudah dihapus oleh penerbitan lain. Muat ulang halaman lalu coba lagi.',
        ];
    }

    public function toFields(): FormDraftInput
    {
        return new FormDraftInput(array_map($this->field(...), $this->indexesOf('fields')));
    }

    private function field(int|string $i): FormFieldInput
    {
        $key = "fields.{$i}";

        return new FormFieldInput(
            id: $this->optionalId("{$key}.id"),
            fieldTypeId: $this->integer("{$key}.field_type_id"),
            label: $this->string("{$key}.label")->value(),
            isRequired: $this->boolean("{$key}.is_required"),
            options: $this->has("{$key}.options") ? array_map(fn (int|string $o): array => [
                'id' => $this->optionalId("{$key}.options.{$o}.id"),
                'label' => $this->string("{$key}.options.{$o}.label")->value(),
            ], $this->indexesOf("{$key}.options")) : null,
        );
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
