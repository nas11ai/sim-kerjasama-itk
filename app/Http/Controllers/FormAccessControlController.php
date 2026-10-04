<?php

namespace App\Http\Controllers;

use App\Actions\Form\GrantFormAccess;
use App\Actions\Form\GrantFormAccessInBulk;
use App\Actions\Form\ReviseFormAccess;
use App\Actions\Form\RevokeFormAccess;
use App\Actions\Form\RevokeFormAccessInBulk;
use App\Http\Requests\Form\FormAccessBulkDeleteRequest;
use App\Http\Requests\Form\FormAccessBulkRequest;
use App\Http\Requests\Form\FormAccessControlRequest;
use App\Http\Requests\Form\FormAccessListRequest;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\User;
use App\Queries\Form\FormAccessControlDirectory;
use App\Queries\Form\FormAccessControlFormOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormAccessControlController extends Controller
{
    public function index(FormAccessListRequest $request)
    {
        $directory = FormAccessControlDirectory::run($request->toInput());

        return Inertia::render('FormAccessControls/IndexPage', [
            'groupAccessControls' => $directory['groups'],
            ...FormAccessControlFormOptions::run(),
            'filters' => $directory['filters'],
        ]);
    }

    public function create()
    {
        return Inertia::render('FormAccessControls/CreatePage', FormAccessControlFormOptions::run());
    }

    public function store(FormAccessControlRequest $request)
    {
        GrantFormAccess::run($request->toInput(), $this->actor($request));

        return redirect()->route('admin.form-access-controls.index')
            ->with('success', 'Kontrol Akses Formulir berhasil dibuat.');
    }

    public function show(FormAccessControl $formAccessControl)
    {
        $formAccessControl->load(['form', 'studyProgram.faculty', 'formPhaseDetails.formPhase']);

        return Inertia::render('FormAccessControls/ShowPage', [
            'formAccessControl' => $formAccessControl,
        ]);
    }

    public function edit(FormAccessControl $formAccessControl)
    {
        $formAccessControl->load(['form', 'studyProgram.faculty']);

        return Inertia::render('FormAccessControls/EditPage', [
            'formAccessControl' => $formAccessControl,
            ...FormAccessControlFormOptions::run(),
        ]);
    }

    public function update(FormAccessControlRequest $request, FormAccessControl $formAccessControl)
    {
        // BR-FE-20 — form kontrol akses yang dipakai detail tahap terkunci.
        ReviseFormAccess::run($formAccessControl, $request->toInput(), $this->actor($request));

        return redirect()->route('admin.form-access-controls.index')
            ->with('success', 'Kontrol Akses Formulir berhasil diperbarui.');
    }

    public function destroy(Request $request, FormAccessControl $formAccessControl)
    {
        // BR-FE-20 — penjaga yang sama dengan hapus massal; penolakannya validasi.
        RevokeFormAccess::run($formAccessControl, $this->actor($request));

        return redirect()->route('admin.form-access-controls.index')
            ->with('success', 'Kontrol Akses Formulir berhasil dihapus.');
    }

    public function bulkCreate(FormAccessBulkRequest $request)
    {
        $result = GrantFormAccessInBulk::run($request->toInputs(), $this->actor($request));

        return redirect()->route('admin.form-access-controls.index')->with(
            'success',
            "{$result['created']} kontrol akses formulir dibuat; {$result['skipped']} dilewati karena sudah terdaftar.",
        );
    }

    public function bulkDelete(FormAccessBulkDeleteRequest $request)
    {
        // BR-FE-20 — semua atau tidak; alasannya menyebut kontrol akses yang dipakai.
        $deleted = RevokeFormAccessInBulk::run($request->ids(), $this->actor($request));

        return redirect()->route('admin.form-access-controls.index')
            ->with('success', "{$deleted} kontrol akses formulir berhasil dihapus.");
    }

    public function getStudyPrograms(Request $request)
    {
        $studyPrograms = Organization::query()
            ->where('type', 'study_program')
            ->where('parent_id', $request->faculty_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($studyPrograms);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
