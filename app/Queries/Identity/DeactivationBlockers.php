<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-10, BR-SM-09 — pengajuan aktif yang ketuanya belum boleh dinonaktifkan, beserta
 * kolom `has_active_co_investigator` (calon penerima kepemilikan). Definisi tunggal untuk
 * DeactivateUser dan layar detail user (SM-OWNER-03/04).
 *
 * @method static Collection<int, FormSubmission> run(User $user)
 */
class DeactivationBlockers implements ChangesNoState
{
    use AsAction;

    /** Status yang tidak lagi menuntut penanggung jawab. */
    private const SETTLED = [Rejected::class, Withdrawn::class];

    /** @return Collection<int, FormSubmission> */
    public function handle(User $user): Collection
    {
        return FormSubmission::query()
            ->where('submitted_by', $user->id)
            // whereNotState, bukan whereNotIn: kolomnya menyimpan morph name
            // ("rejected"), jadi membandingkan class-string tidak pernah cocok.
            ->whereNotState('status', self::SETTLED)
            ->with('form:id,title')
            ->withExists(['researchMembers as has_active_co_investigator' => fn (Builder $member) => $member
                ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
                ->where('status', 'active')
                // SM-OWNER-07 — co-investigator berakun nonaktif bukan calon penerima.
                ->whereHas('userProfile.user', fn (Builder $user) => $user->where('is_active', true))])
            ->orderBy('id')
            ->get();
    }
}
