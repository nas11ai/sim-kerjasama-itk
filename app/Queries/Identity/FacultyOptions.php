<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pohon fakultas → prodi, dibentuk seperti payload lama Faculty::with('studyPrograms').
 *
 * @phpstan-type StudyProgramOption array{id: int, name: string, faculty_id: int}
 * @phpstan-type FacultyOption array{id: int, name: string, study_programs: array<int, StudyProgramOption>}
 *
 * @method static Collection<int, FacultyOption> run()
 */
class FacultyOptions implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, FacultyOption> */
    public function handle(): Collection
    {
        return Organization::query()
            ->where('type', 'faculty')
            ->orderBy('name')
            ->with(['children' => static fn (Relation $query) => $query->where('type', 'study_program')->orderBy('name')])
            ->get()
            ->map(static fn (Organization $faculty): array => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'study_programs' => $faculty->children
                    ->map(static fn (Organization $studyProgram): array => [
                        'id' => $studyProgram->id,
                        'name' => $studyProgram->name,
                        'faculty_id' => $faculty->id,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();
    }
}
