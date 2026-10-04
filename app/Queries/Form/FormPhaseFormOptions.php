<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\Organization;
use App\Models\PhaseType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Role;

/**
 * Pilihan untuk layar Buat dan Ubah tahap formulir; kedua layar membaca prop yang sama.
 *
 * @phpstan-type Options array{forms: Collection<int, Form>, roles: Collection<int, Role>, permissions: list<string>, faculties: SupportCollection<int, array{id: int, name: string, study_programs: array<int, array{id: int, name: string, faculty_id: int}>}>, phaseTypes: Collection<int, PhaseType>, formAccessControls: Collection<int, FormAccessControl>, submissionDates: list<array{id: int, label: string}>}
 *
 * @method static Options run()
 */
class FormPhaseFormOptions implements ChangesNoState
{
    use AsAction;

    /** @return Options */
    public function handle(): array
    {
        return [
            'forms' => Form::where('is_active', true)->get(['id', 'title']),
            'roles' => Role::all(['id', 'name']),
            'permissions' => FormAccessControl::ALLOWED_PERMISSIONS,
            'faculties' => Organization::facultyOptions(),
            'phaseTypes' => PhaseType::all(['id', 'name']),
            'formAccessControls' => FormAccessControl::with(['form', 'studyProgram'])->get(),
            'submissionDates' => SubmissionDateOptions::run(),
        ];
    }
}
