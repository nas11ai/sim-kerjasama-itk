<?php

namespace App\Http\Requests\Monev;

use App\Models\FormSubmission;
use App\Models\Reviewer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Penugasan satu reviewer pada satu siklus monev (OPERATOR-05-02). BR-MON-04, W129 —
 * dijaga Policy `assignMonevReviewer` atas induknya.
 */
class AssignCycleReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parent = $this->route('submission');

        return $parent instanceof FormSubmission && $this->user()?->can('assignMonevReviewer', $parent) === true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['reviewer_id' => 'required|integer|exists:reviewers,id'];
    }

    public function reviewer(): Reviewer
    {
        return Reviewer::query()->findOrFail($this->integer('reviewer_id'));
    }
}
