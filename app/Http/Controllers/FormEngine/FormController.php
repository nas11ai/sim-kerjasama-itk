<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\DeleteForm;
use App\Actions\Form\SetFormActive;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormActiveRequest;
use App\Models\Form;
use App\Models\User;
use App\Queries\Form\FormDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Form Builder (ADMIN-08). Route dijaga `can:schemes.manage` (W88).
 */
class FormController extends Controller
{
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

    public function setActive(FormActiveRequest $request, Form $form): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $active = $request->boolean('active');

        SetFormActive::run($form, $active, $actor);

        return back()->with('success', $active ? 'Form diaktifkan.' : 'Form dinonaktifkan.');
    }

    // BR-FE-19 — penjaga rujukan berjalan di Form::booted(); penolakannya sampai apa adanya.
    public function destroy(Request $request, Form $form): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DeleteForm::run($form, $actor);

        return redirect()->route('admin.forms.index')->with('success', 'Form dihapus.');
    }
}
