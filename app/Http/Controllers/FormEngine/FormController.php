<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\CreateForm;
use App\Actions\Form\DeleteForm;
use App\Actions\Form\ReviseForm;
use App\Actions\Form\SetFormActive;
use App\Data\View\FormEngine\FormHeaderData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormActiveRequest;
use App\Http\Requests\Form\FormDefinitionRequest;
use App\Models\Form;
use App\Queries\Form\FieldTypeOptions;
use App\Queries\Form\FormDirectory;
use App\Queries\Form\FormGeneral;
use App\Queries\Form\FormTypeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pembuat Formulir (ADMIN-08). Route dijaga `can:schemes.manage` (W88).
 */
class FormController extends Controller
{
    use ResolvesActor;

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'type' => $request->string('type')->value(),
            'status' => $request->string('status')->value(),
        ];

        return Inertia::render('FormEngine/FormIndexPage', [
            'filters' => $filters,
            ...FormDirectory::run($filters),
        ]);
    }

    public function create(): Response
    {
        return $this->general(null);
    }

    public function store(FormDefinitionRequest $request): RedirectResponse
    {
        $form = CreateForm::run($request->toInput(), $request->toFields(), $this->actor($request));

        // W86 — Form baru langsung dibuka di halaman suntingnya. BR-FE-34 — field-nya masih draf.
        return redirect()->route('admin.forms.edit', $form)
            ->with('success', 'Formulir dibuat. Susun isiannya lalu terbitkan.');
    }

    public function edit(Form $form): Response
    {
        return $this->general($form);
    }

    // Kartu General saja; field lewat FormDraftController (BR-FE-34).
    public function update(FormDefinitionRequest $request, Form $form): RedirectResponse
    {
        ReviseForm::run($form, $request->toInput(), $this->actor($request));

        // W86 — "Lanjut" menyimpan lalu membuka tab Access Control Form ini.
        $redirect = $request->boolean('continue') ? redirect()->route('admin.forms.access.index', $form) : back();

        return $redirect->with('success', 'Formulir diperbarui.');
    }

    public function setActive(FormActiveRequest $request, Form $form): RedirectResponse
    {
        $active = $request->boolean('active');

        SetFormActive::run($form, $active, $this->actor($request));

        return back()->with('success', $active ? 'Formulir diaktifkan.' : 'Formulir dinonaktifkan.');
    }

    // BR-FE-19 — penjaga rujukan berjalan di Form::booted(); penolakannya sampai apa adanya.
    public function destroy(Request $request, Form $form): RedirectResponse
    {
        DeleteForm::run($form, $this->actor($request));

        return redirect()->route('admin.forms.index')->with('success', 'Formulir dihapus.');
    }

    private function general(?Form $form): Response
    {
        return Inertia::render('FormEngine/FormEditPage', [
            'tab' => 'general',
            'form' => $form === null ? null : FormHeaderData::fromModel($form),
            'general' => FormGeneral::run($form),
            'fieldTypeOptions' => FieldTypeOptions::run(),
            'formTypeOptions' => FormTypeOptions::run($form),
        ]);
    }
}
