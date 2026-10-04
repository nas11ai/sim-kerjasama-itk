<?php

namespace App\Http\Controllers;

use App\Actions\Form\BuildCompleteForm;
use App\Http\Requests\Form\FormBuilderRequest;
use App\Models\FieldType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\SubmissionPeriod;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CompleteFormBuilderController extends Controller
{
    /**
     * Show the complete form builder page
     */
    public function create()
    {
        return Inertia::render('FormBuilder/CreatePage', [
            'formTypes' => FormType::orderBy('name')->get(),
            'fieldTypes' => FieldType::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'permissions' => FormAccessControl::ALLOWED_PERMISSIONS,
            'faculties' => Organization::facultyOptions(),
            'phaseTypes' => PhaseType::all(),
            'formPhases' => FormPhase::where('is_active', true)
                ->with('formPhaseDetails')
                ->orderBy('title')
                ->get(),
            'submissionPeriods' => SubmissionPeriod::with('submissionDates')
                ->whereHas('submissionDates', function ($query) {
                    $query->where('datetime', '>=', now());
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(FormBuilderRequest $request)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        $form = BuildCompleteForm::run($request->toInput(), $actor);

        return redirect()->route('admin.forms.show', $form->id)
            ->with('success', 'Form dan seluruh konfigurasinya berhasil dibuat!');
    }
}
