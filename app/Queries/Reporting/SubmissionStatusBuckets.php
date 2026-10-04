<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-06 — peta state pengajuan ke kelompok yang dibaca layar statistik. Draft tanpa
 * kelompok: belum diajukan siapa pun, jadi tidak dihitung di statistik status mana pun.
 * Nama state diambil dari kelasnya, bukan ditulis tangan, agar yang diganti nama tidak lolos.
 *
 * @method static array<string, list<string>> run()
 */
class SubmissionStatusBuckets implements ChangesNoState
{
    use AsAction;

    /** @return array<string, list<string>> kelompok => nama state yang tersimpan */
    public function handle(): array
    {
        return [
            'pending' => [Submitted::$name, Resubmitted::$name],
            'under_review' => [UnderReview::$name],
            'revision' => [NeedsRevision::$name],
            'approved' => [Approved::$name],
            'rejected' => [Rejected::$name],
            'withdrawn' => [Withdrawn::$name],
        ];
    }

    /** @return list<string> semua state yang dihitung, yaitu semua kecuali draft */
    public function countedStates(): array
    {
        return array_merge(...array_values($this->handle()));
    }
}
