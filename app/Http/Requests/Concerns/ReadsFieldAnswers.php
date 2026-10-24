<?php

namespace App\Http\Requests\Concerns;

/** Jawaban `responses.*` dari layar isian form, per id field. */
trait ReadsFieldAnswers
{
    /** @return array<int, ?string> id field => nilai; null berarti kosong */
    private function fieldAnswers(): array
    {
        $answers = [];

        foreach (array_keys($this->array('responses')) as $index) {
            $fieldId = $this->integer("responses.{$index}.form_field_id");
            $value = $this->input("responses.{$index}.value");

            // Kosong (null, '', false, []) jadi null; larik disimpan sebagai JSON.
            $answers[$fieldId] = match (true) {
                $value === null, $value === '', $value === false, $value === [] => null,
                is_array($value) => json_encode($value) ?: null,
                is_scalar($value) => (string) $value,
                default => null,
            };
        }

        return $answers;
    }
}
