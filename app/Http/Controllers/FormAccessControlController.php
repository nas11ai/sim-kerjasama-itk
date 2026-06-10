<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\StudyProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class FormAccessControlController extends Controller
{
    public function index(Request $request)
    {
        $query = FormAccessControl::with(['form', 'role', 'studyProgram.faculty']);

        // Apply filters
        if ($request->has('form_id') && $request->form_id) {
            $query->where('form_id', $request->form_id);
        }

        if ($request->has('role_id') && $request->role_id) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->has('faculty_id') && $request->faculty_id) {
            $query->whereHas('studyProgram', function ($q) use ($request) {
                $q->where('faculty_id', $request->faculty_id);
            });
        }

        if ($request->has('study_program_id') && $request->study_program_id) {
            $query->where('study_program_id', $request->study_program_id);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('form', function ($subQ) use ($search) {
                    $subQ->where('title', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('role', function ($subQ) use ($search) {
                        $subQ->where('name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('studyProgram', function ($subQ) use ($search) {
                        $subQ->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        $groupAccessControls = $query->select('form_id')
            ->selectRaw('COUNT(*) as jumlah_access_controls')
            ->groupBy('form_id')
            ->with('form')
            ->orderByRaw('MAX(created_at) DESC')
            ->paginate(10);

        $form_id = $groupAccessControls->pluck('form_id');

        $controls = FormAccessControl::with(['role', 'studyProgram.faculty'])
            ->whereIn('form_id', $form_id)
            ->get()
            ->groupBy('form_id');

        $groupAccessControls->getCollection()->transform(function ($item) use ($controls) {
            $item->setAttribute(
                'controls',
                $controls[$item->form_id] ?? collect()
            );

            return $item;
        });

        // Get filter options
        $forms = Form::where('is_active', true)->orderBy('title')->get(['id', 'title']);
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $faculties = Faculty::with('studyPrograms')->orderBy('name')->get();

        return Inertia::render('FormAccessControls/IndexPage', [
            'groupAccessControls' => $groupAccessControls,
            'forms' => $forms,
            'roles' => $roles,
            'faculties' => $faculties,
            'filters' => $request->only(['form_id', 'role_id', 'faculty_id', 'study_program_id', 'search']),
        ]);
    }

    public function create()
    {
        $forms = Form::where('is_active', true)->orderBy('title')->get(['id', 'title']);
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $faculties = Faculty::with('studyPrograms')->orderBy('name')->get();

        return Inertia::render('FormAccessControls/CreatePage', [
            'forms' => $forms,
            'roles' => $roles,
            'faculties' => $faculties,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'form_id' => 'required|exists:forms,id',
            'role_id' => 'required|exists:roles,id',
            'study_program_id' => 'required|exists:study_programs,id',
        ]);

        // Check for duplicate combination
        $existingControl = FormAccessControl::where([
            'form_id' => $request->form_id,
            'role_id' => $request->role_id,
            'study_program_id' => $request->study_program_id,
        ])->first();

        if ($existingControl) {
            return back()->withErrors([
                'duplicate' => 'Gabungan Formulir, Role, dan Program Studi tersebut sudah terdaftar.',
            ]);
        }

        try {
            FormAccessControl::create([
                'form_id' => $request->form_id,
                'role_id' => $request->role_id,
                'study_program_id' => $request->study_program_id,
            ]);

            return redirect()->route('admin.form-access-controls.index')
                ->with('success', 'Kontrol Akses Formulir berhasil dibuat.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal membuat kontrol akses formulir: '.$e->getMessage()]);
        }
    }

    public function show(FormAccessControl $formAccessControl)
    {
        $formAccessControl->load(['form', 'role', 'studyProgram.faculty', 'formPhaseDetails.formPhase']);

        return Inertia::render('FormAccessControls/ShowPage', [
            'formAccessControl' => $formAccessControl,
        ]);
    }

    public function edit(FormAccessControl $formAccessControl)
    {
        $formAccessControl->load(['form', 'role', 'studyProgram.faculty']);

        $forms = Form::where('is_active', true)->orderBy('title')->get(['id', 'title']);
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $faculties = Faculty::with('studyPrograms')->orderBy('name')->get();

        return Inertia::render('FormAccessControls/EditPage', [
            'formAccessControl' => $formAccessControl,
            'forms' => $forms,
            'roles' => $roles,
            'faculties' => $faculties,
        ]);
    }

    public function update(Request $request, FormAccessControl $formAccessControl)
    {
        $request->validate([
            'form_id' => 'required|exists:forms,id',
            'role_id' => 'required|exists:roles,id',
            'study_program_id' => 'required|exists:study_programs,id',
        ]);

        // Check for duplicate combination (excluding current record)
        $existingControl = FormAccessControl::where([
            'form_id' => $request->form_id,
            'role_id' => $request->role_id,
            'study_program_id' => $request->study_program_id,
        ])->where('id', '!=', $formAccessControl->id)->first();

        if ($existingControl) {
            return back()->withErrors([
                'duplicate' => 'Kombinasi Formulir, Role, dan Program Studi ini sudah ada.',
            ]);
        }

        try {
            $formAccessControl->update([
                'form_id' => $request->form_id,
                'role_id' => $request->role_id,
                'study_program_id' => $request->study_program_id,
            ]);

            return redirect()->route('admin.form-access-controls.index')
                ->with('success', 'Kontrol Akses Formulir berhasil diperbarui.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal memperbarui kontrol akses formulir: '.$e->getMessage()]);
        }
    }

    public function destroy(FormAccessControl $formAccessControl)
    {
        try {
            DB::beginTransaction();

            // Check if this access control is being used in form phase details
            $usageCount = $formAccessControl->formPhaseDetails()->count();

            if ($usageCount > 0) {
                return back()->withErrors([
                    'error' => "Tidak dapat menghapus kontrol akses ini. Kontrol akses ini sedang digunakan dalam {$usageCount} detail fase formulir.",
                ]);
            }

            $formAccessControl->delete();

            DB::commit();

            return redirect()->route('admin.form-access-controls.index')
                ->with('success', 'Kontrol Akses Formulir berhasil dihapus.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus kontrol akses formulir: '.$e->getMessage()]);
        }
    }

    public function bulkCreate(Request $request)
    {
        $request->validate([
            'form_id' => 'required|exists:forms,id',
            'combinations' => 'required|array|min:1',
            'combinations.*.role_id' => 'required|exists:roles,id',
            'combinations.*.study_program_id' => 'required|exists:study_programs,id',
        ]);

        try {
            DB::beginTransaction();

            $created = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->combinations as $index => $combination) {
                // Check for existing combination
                $exists = FormAccessControl::where([
                    'form_id' => $request->form_id,
                    'role_id' => $combination['role_id'],
                    'study_program_id' => $combination['study_program_id'],
                ])->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                FormAccessControl::create([
                    'form_id' => $request->form_id,
                    'role_id' => $combination['role_id'],
                    'study_program_id' => $combination['study_program_id'],
                ]);

                $created++;
            }

            DB::commit();

            $message = "Bulk creation completed. Created: {$created}, Skipped (duplicates): {$skipped}";

            return redirect()->route('admin.form-access-controls.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal membuat kontrol akses formulir secara massal: '.$e->getMessage()]);
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:form_access_controls,id',
        ]);

        try {
            DB::beginTransaction();

            $accessControls = FormAccessControl::whereIn('id', $request->ids)->get();
            $cannotDelete = [];
            $deleted = 0;

            foreach ($accessControls as $control) {
                $usageCount = $control->formPhaseDetails()->count();

                if ($usageCount > 0) {
                    $cannotDelete[] = "ID {$control->id} (used in {$usageCount} phase details)";

                    continue;
                }

                $control->delete();
                $deleted++;
            }

            DB::commit();

            $message = "Bulk deletion completed. Deleted: {$deleted}";
            if (!empty($cannotDelete)) {
                $message .= '. Could not delete: '.implode(', ', $cannotDelete);
            }

            return redirect()->route('admin.form-access-controls.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus kontrol akses formulir secara massal: '.$e->getMessage()]);
        }
    }

    public function getStudyPrograms(Request $request)
    {
        $studyPrograms = StudyProgram::where('faculty_id', $request->faculty_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($studyPrograms);
    }
}
