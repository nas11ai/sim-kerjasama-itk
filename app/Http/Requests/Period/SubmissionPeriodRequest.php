<?php

namespace App\Http\Requests\Period;

use App\Data\Request\SubmissionPeriodInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SubmissionPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'submission_dates' => 'required|array|min:1',
            // W72 — label teks bebas; label baru dibuat saat periode disimpan (BR-FE-17).
            'submission_dates.*.label' => 'required|string|max:255',
            'submission_dates.*.date' => 'required|date',
            'form_phase_ids' => 'required|array|min:1',
            'form_phase_ids.*' => 'exists:form_phases,id',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [$this->assertLabelsDistinct(...)];
    }

    private function assertLabelsDistinct(Validator $validator): void
    {
        // Setiap tanggal adalah batas akhir (SubmissionPeriodGate), bukan jendela
        // mulai–selesai; yang bisa salah hanyalah satu label diberi dua tanggal.
        $labels = array_column($this->submissionDates(), 'label');

        if (count($labels) === count(array_unique($labels))) {
            return;
        }

        $validator->errors()->add('submission_dates', 'Setiap label tanggal hanya boleh muncul satu kali.');
    }

    public function toInput(): SubmissionPeriodInput
    {
        return new SubmissionPeriodInput(
            name: $this->string('name')->value(),
            dates: $this->submissionDates(),
            formPhaseIds: array_map(intval(...), $this->array('form_phase_ids')),
        );
    }

    /** @return list<array{label: string, date: string}> */
    private function submissionDates(): array
    {
        $rows = [];

        foreach ($this->array('submission_dates') as $row) {
            if (!is_array($row) || !is_string($row['label'] ?? null) || !is_string($row['date'] ?? null)) {
                continue;
            }

            $rows[] = ['label' => trim($row['label']), 'date' => $row['date']];
        }

        return $rows;
    }
}
