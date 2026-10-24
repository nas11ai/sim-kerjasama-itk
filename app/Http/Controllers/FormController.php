<?php

namespace App\Http\Controllers;

use App\Actions\Form\CreateForm;
use App\Actions\Form\DeleteForm;
use App\Actions\Form\DeleteFormsInBulk;
use App\Actions\Form\DuplicateForm;
use App\Actions\Form\NotifyFormChange;
use App\Actions\Form\ReviseForm;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Form\FormBulkDeleteRequest;
use App\Http\Requests\Form\FormDefinitionRequest;
use App\Http\Requests\Form\FormListRequest;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormType;
use App\Queries\Form\FormDirectory;
use App\Support\AffectedDraftOwners;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormController extends Controller
{
    use ResolvesActor;

    public function index(FormListRequest $request)
    {
        $directory = FormDirectory::run($request->toInput());

        return Inertia::render('Forms/IndexPage', [
            'forms' => $directory['forms'],
            'formTypes' => FormType::orderBy('name')->get(),
            'filters' => $directory['filters'],
        ]);
    }

    public function create()
    {
        return Inertia::render('Forms/CreatePage', [
            'formTypes' => FormType::orderBy('name')->get(),
            'fieldTypes' => FieldType::orderBy('name')->get(),
        ]);
    }

    public function store(FormDefinitionRequest $request)
    {
        CreateForm::run($request->toInput(), $this->actor($request));

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil dibuat!');
    }

    public function show(Form $form)
    {
        $form->load(['formType', 'formFields.fieldType', 'formFields.formFieldOptions']);

        return Inertia::render('Forms/ShowPage', [
            'form' => $form,
        ]);
    }

    public function edit(Form $form)
    {
        $form->load(['formType', 'formFields.fieldType', 'formFields.formFieldOptions']);

        return Inertia::render('Forms/EditPage', [
            'form' => $form,
            'formTypes' => FormType::orderBy('name')->get(),
            'fieldTypes' => FieldType::orderBy('name')->get(),
            // BR-FE-13 — angkanya terlihat **sebelum** Admin menyimpan, saat keputusan
            // mengirim notifikasi diambil. Sesudah menyimpan tidak menolong siapa pun.
            'affectedDrafts' => AffectedDraftOwners::countForForm($form),
        ]);
    }

    public function update(FormDefinitionRequest $request, Form $form)
    {
        ReviseForm::run($form, $request->toInput(), $this->actor($request));

        // BR-FE-13 — pemberitahuan ke peneliti ditawarkan, bukan dikirim diam-diam.
        // Tanpa draft berjalan, tidak ada peringatan sama sekali.
        $terdampak = NotifyFormChange::run($form, $request->boolean('notify_affected'));

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil diperbarui!')
            ->with('affectedDrafts', $terdampak);
    }

    public function destroy(Request $request, Form $form)
    {
        DeleteForm::run($form, $this->actor($request));

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil dihapus!');
    }

    public function duplicate(Request $request, Form $form)
    {
        DuplicateForm::run($form, $this->actor($request));

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil diduplikasi!');
    }

    public function bulkDelete(FormBulkDeleteRequest $request)
    {
        // BR-FE-19 — semua atau tidak; alasannya menyebut formulir yang masih dipakai.
        $deleted = DeleteFormsInBulk::run($request->ids(), $this->actor($request));

        return redirect()->route('admin.forms.index')
            ->with('success', "{$deleted} formulir berhasil dihapus!");
    }
}
