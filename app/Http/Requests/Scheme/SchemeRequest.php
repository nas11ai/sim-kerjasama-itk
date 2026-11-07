<?php

namespace App\Http\Requests\Scheme;

use App\Data\Request\SchemeInput;
use App\Models\Scheme;
use App\Support\Scheme\SchemeRuleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/** ADMIN-08-04 — buat dan sunting skema. Wewenang dijaga middleware `can:schemes.manage`. */
class SchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|list<string|Unique>> */
    public function rules(): array
    {
        $scheme = $this->route('scheme');

        return [
            'name' => 'required|string|max:255',
            // BR-SCH-03 — kode unik; aturan ini memberi pesan pada field yang benar alih-alih 500.
            'code' => ['required', 'string', 'max:64', Rule::unique('schemes', 'code')->ignore($scheme instanceof Scheme ? $scheme->id : null)],
            // BR-SCH-02 — batas bernilai nol menolak setiap anggaran dan anggota tanpa sebab yang terlihat.
            'max_budget' => 'required|integer|min:1',
            'max_members' => 'required|integer|min:1',
            'duration_months' => 'required|integer|min:1',
            'scheme_type_id' => 'nullable|exists:scheme_types,id',
            'submission_type_id' => 'nullable|exists:submission_types,id',
            // W107 — layar hanya mengirim kunci katalog; kunci lain ditolak, bukan disimpan diam-diam.
            'rules' => ['sometimes', 'nullable', 'array:'.implode(',', SchemeRuleCatalog::keys())],
            ...SchemeRuleCatalog::validationRules(),
            'allowed_trl_ids' => 'nullable|array',
            'allowed_trl_ids.*' => 'integer|exists:technology_readiness_levels,id',
        ];
    }

    public function toInput(): SchemeInput
    {
        return SchemeInput::fromValidated($this->safe());
    }
}
