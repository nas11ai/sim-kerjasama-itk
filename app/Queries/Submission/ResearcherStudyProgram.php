<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Program studi peneliti beserta nama fakultasnya, untuk kepala layar peneliti.
 *
 * @phpstan-type StudyProgram array{id: int, name: string, faculty: array{name: string}}
 *
 * @method static StudyProgram|null run(User $user)
 */
class ResearcherStudyProgram implements ChangesNoState
{
    use AsAction;

    /** @return StudyProgram|null */
    public function handle(User $user): ?array
    {
        $organization = $user->organization;

        if ($organization === null) {
            return null;
        }

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'faculty' => ['name' => $organization->parent->name ?? ''],
        ];
    }
}
