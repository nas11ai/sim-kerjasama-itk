<?php

namespace App\Http\Controllers;

use App\Actions\Form\BuildCompleteForm;
use App\Http\Requests\Form\FormBuilderRequest;
use App\Models\FieldType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormType;
use App\Models\PhaseType;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Queries\Identity\FacultyOptions;
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
            'faculties' => FacultyOptions::run(),
            'phaseTypes' => PhaseType::all(),
            'formPhases' => FormPhase::where('is_active', true)
                ->with('formPhaseDetails')
                ->orderBy('title')
                ->get(),
            // K8g — wizard hanya memilih periode yang ada; labelnya diturunkan (BR-FE-25).
            'submissionPeriods' => SubmissionPeriod::with(['submissionDates' => fn ($dates) => $dates->orderBy('datetime')])
                ->usable()
                ->orderBy('name')
                ->get()
                ->map(fn (SubmissionPeriod $period): array => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'submission_dates' => $period->submissionDates->map(fn (SubmissionDate $date): array => [
                        'id' => $date->id,
                        'label' => $date->label(),
                        'datetime' => $date->datetime->toIso8601String(),
                    ])->all(),
                ]),
        ]);
    }

    public function store(FormBuilderRequest $request)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        $built = BuildCompleteForm::run($request->toInput(), $actor);

        // BR-FE-17 — wizard tidak menanyakan tenggat, jadi Operator diberi tahu apa yang dipakai.
        $deadline = $built->deadline;
        $label = $deadline->label();

        return redirect()->route('admin.forms.show', $built->form->id)
            ->with('success', 'Form dan seluruh konfigurasinya berhasil dibuat!')
            ->with('info', "Tenggat detail tahap: {$label}, {$deadline->datetime->format('Y-m-d H:i')}. "
                .'Dapat diubah di Edit Tahap.');
    }
}
