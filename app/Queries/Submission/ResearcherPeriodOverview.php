<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormAccessControl;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Periode yang punya fase terbuka bagi peneliti, beserta jumlah pengajuannya di tiap periode.
 * Hitungan memakai FK periode pengajuan; fase yang sama boleh dipakai beberapa periode.
 *
 * @phpstan-type StudyProgram array{id: int, name: string, faculty: array{name: string}}
 *
 * @method static array{submissionPeriods: Collection<int, SubmissionPeriod>, userRole: string|null, studyProgram: StudyProgram|null} run(User $user)
 */
class ResearcherPeriodOverview implements ChangesNoState
{
    use AsAction;

    /** @return array{submissionPeriods: Collection<int, SubmissionPeriod>, userRole: string|null, studyProgram: StudyProgram|null} */
    public function handle(User $user): array
    {
        $phaseIds = FormPhaseDetail::query()
            ->whereIn('form_access_control_id', FormAccessControl::query()->accessibleBy($user)->select('id'))
            ->select('form_phase_id');

        $periods = SubmissionPeriod::query()
            ->with([
                'submissionDates.submissionDateLabel',
                'submissionPeriodPhases.formPhase' => fn (Relation $q) => $q->whereIn('id', $phaseIds),
            ])
            ->whereHas('submissionPeriodPhases', fn (Builder $q) => $q->whereIn('form_phase_id', $phaseIds))
            ->orderBy('created_at', 'desc')
            ->get();

        $this->attachCounts($periods, $user);

        return [
            'submissionPeriods' => $periods,
            'userRole' => $this->firstRole($user),
            'studyProgram' => $this->studyProgram($user),
        ];
    }

    /** @param  Collection<int, SubmissionPeriod>  $periods */
    private function attachCounts(Collection $periods, User $user): void
    {
        $mine = FormSubmission::query()
            ->where('submitted_by', $user->id)
            ->whereIn('submission_period_id', $periods->map(fn (SubmissionPeriod $p): int => $p->id)->all())
            ->get(['submission_period_id', 'is_submitted']);

        foreach ($periods as $period) {
            $own = $mine->where('submission_period_id', $period->id);
            $period->setAttribute('user_submissions_count', $own->count());
            $period->setAttribute('user_draft_count', $own->where('is_submitted', false)->count());
            $period->setAttribute('user_submitted_count', $own->where('is_submitted', true)->count());
        }
    }

    private function firstRole(User $user): ?string
    {
        $role = $user->getRoleNames()->first();

        return is_string($role) ? $role : null;
    }

    /** @return StudyProgram|null */
    private function studyProgram(User $user): ?array
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
