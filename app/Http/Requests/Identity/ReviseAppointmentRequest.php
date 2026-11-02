<?php

namespace App\Http\Requests\Identity;

use App\Support\Identity\ReviewerTypeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** BR-IAM-11, BR-REV-09 — tipe dan masa tugas reviewer; keduanya wajib (OPERATOR-08-04). */
class ReviseAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'reviewer_type' => ['required', Rule::in(array_keys(ReviewerTypeCatalog::LABELS))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function reviewerType(): string
    {
        return $this->string('reviewer_type')->value();
    }

    /** @return array{start: string, end: string} */
    public function tenure(): array
    {
        return [
            'start' => $this->string('start_date')->value(),
            'end' => $this->string('end_date')->value(),
        ];
    }
}
