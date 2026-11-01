<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Manage\OwnershipCandidateData;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-09 — hanya co_investigator aktif yang boleh menerima kepemilikan; disaring di sini
 * supaya UI tidak menawarkan orang yang pasti ditolak Action.
 *
 * @method static list<OwnershipCandidateData> run(FormSubmission $submission)
 */
class OwnershipCandidates implements ChangesNoState
{
    use AsAction;

    /** @return list<OwnershipCandidateData> */
    public function handle(FormSubmission $submission): array
    {
        $candidates = [];
        $members = $submission->researchMembers()
            ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
            ->where('status', 'active')
            ->with(['userProfile.user:id,name', 'userProfile.organization:id,name'])
            ->orderBy('id')
            ->get();

        foreach ($members as $member) {
            $user = $member->userProfile->user;

            if ($user !== null) {
                $candidates[] = new OwnershipCandidateData($user->id, $user->name, $member->userProfile->organization?->name);
            }
        }

        return $candidates;
    }
}
