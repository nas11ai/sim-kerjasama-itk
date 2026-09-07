<?php

namespace App\Http\Controllers;

use App\Actions\Scheme\DeactivateScheme;
use App\Actions\Scheme\SaveScheme;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionType;
use App\Models\TechnologyReadinessLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-SCH-01, BR-SCH-02, BR-SCH-03, BR-SCH-10 — pengelolaan skema.
 *
 * Sebelum ini skema hanya punya tabel dan model: tidak ada rute, controller,
 * maupun layar. M1 sudah memakai `max_budget` untuk menolak anggaran, tapi
 * tidak ada cara membuat skemanya selain lewat seeder.
 */
class SchemeController extends Controller
{
    public function index(): Response
    {
        $this->authorizeManage();

        return Inertia::render('Schemes/IndexPage', [
            'schemes' => Scheme::query()
                ->with(['schemeType:id,name', 'submissionType:id,name'])
                ->withCount([
                    // Jumlah pengajuan aktif — angka yang menentukan apakah
                    // skema ini boleh dinonaktifkan (BR-SCH-01). Dihitung di
                    // sini supaya layar bisa menjelaskan sebelum Admin mencoba.
                    'formSubmissions as active_submissions_count' => fn ($q) => $q->active(),
                ])
                ->orderBy('name')
                ->get(),
            'schemeTypes' => SchemeType::query()->orderBy('name')->get(['id', 'name']),
            'submissionTypes' => SubmissionType::query()->orderBy('name')->get(['id', 'name']),
            'trls' => TechnologyReadinessLevel::query()->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validated($request, null);

        SaveScheme::run(
            $validated['attributes'],
            null,
            $validated['allowed_trl_ids'],
        );

        return back()->with('success', 'Skema dibuat.');
    }

    public function update(Request $request, Scheme $scheme): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validated($request, $scheme);

        SaveScheme::run($validated['attributes'], $scheme, $validated['allowed_trl_ids']);

        return back()->with('success', 'Skema diperbarui.');
    }

    /**
     * BR-SCH-01 — penonaktifan diblokir selama masih dipakai pengajuan aktif.
     */
    public function deactivate(Scheme $scheme): RedirectResponse
    {
        $this->authorizeManage();

        DeactivateScheme::run($scheme);

        return back()->with('success', 'Skema dinonaktifkan.');
    }

    public function activate(Scheme $scheme): RedirectResponse
    {
        $this->authorizeManage();

        $scheme->update(['is_active' => true]);

        return back()->with('success', 'Skema diaktifkan kembali.');
    }

    /**
     * @return array{attributes: array<string, mixed>, allowed_trl_ids: array<int, int>|null}
     */
    private function validated(Request $request, ?Scheme $scheme): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // BR-SCH-03 — kode unik. Index unik menjaga lomba-balap; aturan ini
            // yang memberi pesan pada field yang benar alih-alih 500.
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('schemes', 'code')->ignore($scheme?->id),
            ],
            // BR-SCH-02 — batas yang bernilai nol akan menolak setiap anggaran
            // dan setiap anggota tanpa ada yang tahu sebabnya.
            'max_budget' => 'required|integer|min:1',
            'max_members' => 'required|integer|min:1',
            'duration_months' => 'required|integer|min:1',
            'scheme_type_id' => 'nullable|exists:scheme_types,id',
            'submission_type_id' => 'nullable|exists:submission_types,id',
            'rules' => 'nullable|array',
            'allowed_trl_ids' => 'nullable|array',
            'allowed_trl_ids.*' => 'integer|exists:technology_readiness_levels,id',
        ]);

        return [
            'attributes' => [
                'name' => $data['name'],
                'code' => $data['code'],
                'max_budget' => $data['max_budget'],
                'max_members' => $data['max_members'],
                'duration_months' => $data['duration_months'],
                'scheme_type_id' => $data['scheme_type_id'] ?? null,
                'submission_type_id' => $data['submission_type_id'] ?? null,
                'rules' => $data['rules'] ?? null,
            ],
            'allowed_trl_ids' => $data['allowed_trl_ids'] ?? null,
        ];
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('schemes.manage'), 403);
    }
}
