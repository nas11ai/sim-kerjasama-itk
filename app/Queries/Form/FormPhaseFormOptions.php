<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\PhaseType;
use App\Queries\Identity\FacultyOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Role;

/**
 * Pilihan untuk layar Buat dan Ubah tahap formulir; kedua layar membaca prop yang sama.
 * Layar Ubah mengirim tahapnya, karena pilihan tenggat bergantung padanya (BR-FE-17).
 *
 * @phpstan-import-type FacultyOption from FacultyOptions
 *
 * @phpstan-type Options array{forms: Collection<int, Form>, roles: Collection<int, Role>, permissions: list<string>, faculties: SupportCollection<int, FacultyOption>, phaseTypes: Collection<int, PhaseType>, formAccessControls: Collection<int, FormAccessControl>, submissionDates: list<array{id: int, label: string}>}
 *
 * @method static Options run(?FormPhase $phase = null)
 */
class FormPhaseFormOptions implements ChangesNoState
{
    use AsAction;

    /** @return Options */
    public function handle(?FormPhase $phase = null): array
    {
        return [
            'forms' => Form::where('is_active', true)->get(['id', 'title']),
            'roles' => Role::all(['id', 'name']),
            'permissions' => FormAccessControl::ALLOWED_PERMISSIONS,
            'faculties' => FacultyOptions::run(),
            'phaseTypes' => PhaseType::all(['id', 'name']),
            'formAccessControls' => FormAccessControl::with(['form', 'studyProgram'])->get(),
            'submissionDates' => SubmissionDateOptions::run($phase),
        ];
    }
}
