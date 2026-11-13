<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-01 — pilihan unit di registrasi mandiri: prodi aktif dikelompokkan per induk langsungnya
 * (fakultas, atau jurusan di bawah fakultas). RegisterResearcher menolak unit nonaktif, jadi prodi
 * yang induk atau fakultasnya nonaktif tidak ditawarkan.
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
            ->whereHas('parent', static fn (Builder $parent) => $parent
                ->where('is_active', true)
                ->where(static fn (Builder $kind) => $kind
                    ->where('type', 'faculty')
                    ->orWhere(static fn (Builder $department) => $department
                        ->where('type', 'department')
                        ->whereHas('parent', static fn (Builder $faculty) => $faculty
                            ->where('type', 'faculty')
                            ->where('is_active', true)))))
            ->with('parent.parent')
            ->orderBy('name')
            ->get();

        return $studyPrograms
            ->groupBy('parent_id')
            ->map(static fn (Collection $members): array => self::groupChoice($members))
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  Collection<int, Organization>  $members  prodi satu induk, sudah urut nama
     * @return FacultyChoice
     */
    private static function groupChoice(Collection $members): array
    {
        // whereHas('parent') di handle() menjamin induknya ada.
        $parent = $members->firstOrFail()->parent ?? throw new LogicException('Prodi tanpa induk.');
        $faculty = $parent->type === 'department' ? $parent->parent : null;

        return [
            'id' => $parent->id,
            'name' => $faculty === null ? $parent->name : "{$parent->name} ({$faculty->name})",
            'study_programs' => array_values($members
                ->map(static fn (Organization $member): array => ['id' => $member->id, 'name' => $member->name])
                ->all()),
        ];
    }
}
