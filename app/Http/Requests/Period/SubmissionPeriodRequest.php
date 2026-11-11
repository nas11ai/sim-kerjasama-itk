<?php

namespace App\Http\Requests\Period;

use App\Data\Request\PeriodDateInput;
use App\Data\Request\SubmissionPeriodInput;
use App\Enums\DeadlineType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SubmissionPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|list<string|Enum>> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'submission_dates' => 'required|array|min:1',
            // BR-FE-25 — jenis tenggat tetap × siklus menggantikan label teks bebas (W72).
            'submission_dates.*.type' => ['required', Rule::enum(DeadlineType::class)],
            'submission_dates.*.cycle' => 'nullable|integer|min:1|max:'.DeadlineType::MAX_CYCLE,
            'submission_dates.*.date' => 'required|date',
            'form_phase_ids' => 'required|array|min:1',
            'form_phase_ids.*' => 'exists:form_phases,id',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [$this->assertTypesDistinct(...), $this->assertOutputHasNoCycle(...)];
    }

    private function assertTypesDistinct(Validator $validator): void
    {
        // BR-FE-25 — setiap tanggal adalah batas akhir (SubmissionPeriodGate), bukan jendela
        // mulai–selesai; yang bisa salah hanyalah satu (jenis, siklus) diberi dua tanggal.
        $keys = array_map(fn (PeriodDateInput $row): string => $row->key(), $this->submissionDates());

        if (count($keys) === count(array_unique($keys))) {
            return;
        }

        $validator->errors()->add('submission_dates', 'Setiap jenis tenggat hanya boleh muncul satu kali per siklus.');
    }

    // W139 — Batas Akhir (Upload Luaran) satu per periode, tidak per siklus monev.
    private function assertOutputHasNoCycle(Validator $validator): void
    {
        foreach ($this->array('submission_dates') as $index => $row) {
            if (is_array($row) && ($row['type'] ?? null) === DeadlineType::Output->value && !blank($row['cycle'] ?? null)) {
                $validator->errors()->add("submission_dates.{$index}.cycle", 'Batas Akhir tidak bersiklus.');
            }
        }
    }

    public function toInput(): SubmissionPeriodInput
    {
        return new SubmissionPeriodInput(
            name: $this->string('name')->value(),
            dates: $this->submissionDates(),
            // Satu tahap ditautkan ke periode sekali saja; pivotnya tanpa unique constraint.
            formPhaseIds: array_values(array_unique(array_map(intval(...), $this->array('form_phase_ids')))),
        );
    }

    /** @return list<PeriodDateInput> */
    private function submissionDates(): array
    {
        return array_values(array_filter(array_map($this->dateInput(...), $this->array('submission_dates'))));
    }

    private function dateInput(mixed $row): ?PeriodDateInput
    {
        if (!is_array($row) || !is_string($row['type'] ?? null) || !is_string($row['date'] ?? null)) {
            return null;
        }

        $type = DeadlineType::tryFrom($row['type']);
        $cycle = filter_var($row['cycle'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $type === null ? null : new PeriodDateInput($type, $cycle, $row['date']);
    }
}
