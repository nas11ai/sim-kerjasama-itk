<?php

namespace App\Data\View\Monev;

use App\Data\View\Submission\Manage\ManagedReviewerData;
use App\Data\View\Submission\Manage\OverrideTargetData;
use App\Queries\Submission\ResearcherSubmissionDetail;
use Spatie\LaravelData\Data;

/**
 * Satu siklus di OPERATOR-05-02. Kelayakan tombol dihitung backend (`MonevCycleManagement`)
 * dengan Policy dan gerbang yang sama dengan endpoint-nya. Tanpa laporan, panel perpanjangan
 * dan reviewer kosong.
 *
 * @phpstan-import-type PhaseAccess from ResearcherSubmissionDetail
 */
final class MonevManagedCycleData extends Data
{
    /**
     * @param  list<ManagedReviewerData>  $reviewers
     * @param  PhaseAccess|null  $phaseAccess
     */
    public function __construct(
        public MonevCycleData $cycle,
        public array $reviewers,
        public ?array $phaseAccess,
        public ?OverrideTargetData $overrideTarget,
        public bool $canOpen,
        public ?string $openHint,
        public bool $canAssign,
        public bool $canExtend,
    ) {}
}
