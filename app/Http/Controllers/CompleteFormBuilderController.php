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
use App\Models\SubmissionDateLabel;
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
                ->usable()
                ->orderBy('name')
                ->get(),
            // Label dari basis data; id-nya tidak boleh ditulis tangan di layar.
            'submissionDateLabels' => SubmissionDateLabel::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(FormBuilderRequest $request)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        $built = BuildCompleteForm::run($request->toInput(), $actor);

        // BR-FE-17 — wizard tidak menanyakan tenggat, jadi Operator diberi tahu apa yang dipakai.
        $deadline = $built->deadline;
        $label = $deadline->submissionDateLabel->name ?? 'tanpa label';

        return redirect()->route('admin.forms.show', $built->form->id)
            ->with('success', 'Form dan seluruh konfigurasinya berhasil dibuat!')
            ->with('info', "Tenggat detail tahap: {$label}, {$deadline->datetime->format('Y-m-d H:i')}. "
                .'Dapat diubah di Edit Tahap.');
    }
}
