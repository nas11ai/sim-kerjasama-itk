<?php

namespace App\Http\Requests\Reporting;

use App\Data\Reporting\ExportFilters;
use App\Data\Reporting\ExportRequestData;
use App\Enums\ExportFormat;
use App\Queries\Submission\SubmissionStatusChoices;
use App\Support\Reporting\ExportType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * W154 — bentuk permintaan ekspor tabular. Batas datanya bukan urusan request ini:
 * siapa pun yang login boleh meminta, isinya dibatasi `SubmissionVisibility` (BR-RPT-10).
 */
class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $statuses = array_column(SubmissionStatusChoices::submitted(), 'value');

        return [
            'type' => ['required', 'string', 'in:'.implode(',', ExportType::all())],
            'format' => ['required', 'in:'.implode(',', array_column(ExportFormat::cases(), 'value'))],
            'year_from' => ['nullable', 'integer', 'between:2000,2100'],
            // Ujung bawah boleh kosong; `gte` hanya bermakna bila keduanya diisi.
            'year_to' => ['nullable', 'integer', 'between:2000,2100', ...($this->filled('year_from') ? ['gte:year_from'] : [])],
            'scheme' => ['nullable', 'integer', 'exists:schemes,id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', $statuses)],
        ];
    }

    public function toData(): ExportRequestData
    {
        return new ExportRequestData(
            $this->string('type')->value(),
            ExportFormat::from($this->string('format')->value()),
            new ExportFilters(
                yearFrom: $this->filled('year_from') ? $this->integer('year_from') : null,
                yearTo: $this->filled('year_to') ? $this->integer('year_to') : null,
                scheme: $this->filled('scheme') ? $this->integer('scheme') : null,
                status: $this->filled('status') ? $this->string('status')->value() : null,
            ),
        );
    }
}
