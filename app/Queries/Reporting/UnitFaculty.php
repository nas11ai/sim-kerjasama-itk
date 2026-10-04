<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — pasangan (unit_id, faculty_id): fakultas memetakan dirinya, unit di bawahnya
 * memetakan fakultas leluhurnya; unit di luar fakultas tak punya baris. Aktif atau tidak:
 * statistik menghitung menurut posisi di pohon, jadi kartu fakultas dan prodi sepakat.
 *
 * @method static Builder run()
 */
class UnitFaculty implements ChangesNoState
{
    use AsAction;

    public function handle(): Builder
    {
        return DB::query()->fromSub("
            WITH RECURSIVE unit_faculty AS (
                SELECT id AS unit_id, id AS faculty_id FROM organizations WHERE type = 'faculty'
                UNION
                SELECT o.id, uf.faculty_id FROM organizations o
                INNER JOIN unit_faculty uf ON o.parent_id = uf.unit_id
            )
            SELECT unit_id, faculty_id FROM unit_faculty
        ", 'unit_faculty');
    }
}
