<?php

namespace App\Http\Controllers\Scheme;

use App\Actions\Scheme\ActivateScheme;
use App\Actions\Scheme\DeactivateScheme;
use App\Actions\Scheme\SaveScheme;
use App\Data\Request\SchemeInput;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use App\Queries\Scheme\SchemeDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN-08-03/04 — pengelolaan skema. Route dijaga `can:schemes.manage` (W105).
 */
class SchemeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'type' => $request->string('type')->value(),
            'status' => $request->string('status')->value(),
        ];

        return Inertia::render('Scheme/IndexPage', [
            'filters' => $filters,
            ...SchemeDirectory::run($filters),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SaveScheme::run($this->validated($request, null));

        return back()->with('success', 'Skema dibuat.');
    }

    public function update(Request $request, Scheme $scheme): RedirectResponse
    {
        SaveScheme::run($this->validated($request, $scheme), $scheme);

        return back()->with('success', 'Skema diperbarui.');
    }

    /** BR-SCH-01 — penonaktifan diblokir selama masih dipakai pengajuan aktif. */
    public function deactivate(Scheme $scheme): RedirectResponse
    {
        DeactivateScheme::run($scheme);

        return back()->with('success', 'Skema dinonaktifkan.');
    }

    public function activate(Scheme $scheme): RedirectResponse
    {
        ActivateScheme::run($scheme);

        return back()->with('success', 'Skema diaktifkan kembali.');
    }

    private function validated(Request $request, ?Scheme $scheme): SchemeInput
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // BR-SCH-03 — kode unik; aturan ini memberi pesan pada field yang benar alih-alih 500.
            'code' => ['required', 'string', 'max:64', Rule::unique('schemes', 'code')->ignore($scheme?->id)],
            // BR-SCH-02 — batas bernilai nol menolak setiap anggaran dan anggota tanpa sebab yang terlihat.
            'max_budget' => 'required|integer|min:1',
            'max_members' => 'required|integer|min:1',
            'duration_months' => 'required|integer|min:1',
            'scheme_type_id' => 'nullable|exists:scheme_types,id',
            'submission_type_id' => 'nullable|exists:submission_types,id',
            'rules' => 'nullable|array',
            'allowed_trl_ids' => 'nullable|array',
            'allowed_trl_ids.*' => 'integer|exists:technology_readiness_levels,id',
        ]);

        return SchemeInput::fromValidated(new ValidatedInput($data));
    }
}
