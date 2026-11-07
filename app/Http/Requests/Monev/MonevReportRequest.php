<?php

namespace App\Http\Requests\Monev;

use App\Data\Request\MonevReportInput;
use App\Http\Requests\Concerns\ValidatesFieldResponses;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isian laporan monev (RESEARCHER-05-02), untuk simpan draft maupun submit. Tanpa langkah
 * wizard: form, periode, dan tahapnya milik laporan di route.
 */
class MonevReportRequest extends FormRequest
{
    use ValidatesFieldResponses;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            ...$this->responseRules(),
            // Laporan yang isiannya hanya berkas dan tidak diunggah ulang tidak membawa jawaban.
            'responses' => 'nullable|array',
        ];
    }

    public function toInput(): MonevReportInput
    {
        return new MonevReportInput(
            answers: $this->fieldAnswers(),
            uploads: $this->uploadsByField(),
            lastUpdatedAt: $this->lastUpdatedAt(),
        );
    }
}
