<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — daftar fakultas dan program studi yang dipakai filter di semua layar statistik.
 *
 * @method static array{faculties: Collection<int, Organization>, studyPrograms: Collection<int, Organization>} run()
 */
class OrganizationReferences implements ChangesNoState
{
    use AsAction;

    /** @return array{faculties: Collection<int, Organization>, studyPrograms: Collection<int, Organization>} */
    public function handle(): array
    {
        return [
            'faculties' => Organization::where('type', 'faculty')
                ->select('id', 'name')->orderBy('name')->get(),
            'studyPrograms' => Organization::where('type', 'study_program')
                ->select('id', 'name', 'parent_id as faculty_id')->orderBy('name')->get(),
        ];
    }
}
