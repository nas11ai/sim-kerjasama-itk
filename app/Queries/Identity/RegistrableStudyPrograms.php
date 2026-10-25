<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-01 — pilihan unit di registrasi mandiri: prodi aktif dikelompokkan per fakultas
 * aktif. RegisterResearcher menolak unit nonaktif, jadi unit itu tidak ditawarkan.
 *
 * @phpstan-type StudyProgramChoice array{id: int, name: string}
 * @phpstan-type FacultyChoice array{id: int, name: string, study_programs: list<StudyProgramChoice>}
 *
 * @method static Collection<int, FacultyChoice> run()
 */
class RegistrableStudyPrograms implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, FacultyChoice> */
    public function handle(): Collection
    {
        $studyPrograms = Organization::query()
            ->where('type', 'study_program')
            ->where('is_active', true)
            ->whereHas('parent', static fn (Builder $faculty) => $faculty
                ->where('type', 'faculty')
                ->where('is_active', true))
            ->with('parent')
            ->orderBy('name')
            ->get();

        return $studyPrograms
            ->groupBy('parent_id')
            ->map(static fn (Collection $members): array => self::facultyChoice($members))
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  Collection<int, Organization>  $members  prodi satu fakultas, sudah urut nama
     * @return FacultyChoice
     */
    private static function facultyChoice(Collection $members): array
    {
        // whereHas('parent') di handle() menjamin induknya ada.
        $faculty = $members->firstOrFail()->parent ?? throw new LogicException('Prodi tanpa fakultas.');

        return [
            'id' => $faculty->id,
            'name' => $faculty->name,
            'study_programs' => array_values($members
                ->map(static fn (Organization $member): array => ['id' => $member->id, 'name' => $member->name])
                ->all()),
        ];
    }
}
