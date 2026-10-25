<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Review\RejectSubmission;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Data\View\Submission\SubmissionAnswerData;
use App\Data\View\Submission\SubmissionBudgetData;
use App\Data\View\Submission\SubmissionDecisionData;
use App\Data\View\Submission\SubmissionDetailData;
use App\Data\View\Submission\SubmissionHistoryEntryData;
use App\Data\View\Submission\SubmissionMemberData;
use App\Data\View\Submission\SubmissionTargetOutputData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\SubmissionTargetOutput;
use App\Models\User;
use App\Queries\Review\VisibleReviewerScores;
use App\States\Submission\Rejected;
use App\Support\PhaseAccessGate;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * RESEARCHER-02-03/02-12 bagi pemilik, anggota, dan penonton lain yang lolos BR-SM-07
 * (dijaga controller sebelum Query ini dipanggil).
 *
 * @phpstan-import-type Score from VisibleReviewerScores
 *
 * @phpstan-type PhaseAccess array{is_open: bool, deadline: string|null, form_phase_detail_id: int|null, override: array{id: int, status: string, expires_at: string|null, reason: string|null}|null}
 * @phpstan-type Detail array{submission: SubmissionDetailData, answers: list<SubmissionAnswerData>, members: list<SubmissionMemberData>, targetOutputs: list<SubmissionTargetOutputData>, budget: SubmissionBudgetData, history: list<SubmissionHistoryEntryData>, decision: SubmissionDecisionData|null, scores: array{visible: bool, reason: string|null, scores: list<Score>}, phaseAccess: PhaseAccess, printableDocuments: list<array{value: string, label: string}>, can: SubmissionAbilitiesData}
 *
 * @method static Detail run(User $viewer, FormSubmission $submission)
 */
class ResearcherSubmissionDetail implements ChangesNoState
{
    use AsAction;

    private const ROLE_LABELS = [
        'lead_researcher' => 'Ketua',
        ResearchMember::ROLE_CO_INVESTIGATOR => 'Co-Investigator',
        ResearchMember::ROLE_MEMBER => 'Anggota',
        'viewer' => 'Tidak terlibat',
    ];

    /** @return Detail */
    public function handle(User $viewer, FormSubmission $submission): array
    {
        $submission->loadMissing(['form:id,title', 'scheme:id,name', 'submissionPeriod', 'trl', 'submittedBy:id,name', 'formPhaseDetail']);
        $detail = $submission->getFormPhaseDetail();
        $history = SubmissionHistory::run($submission);

        return [
            'submission' => $this->header($viewer, $submission, $detail, $history),
            'answers' => ReadableSubmissionAnswers::run($submission),
            'members' => $this->members($submission),
            'targetOutputs' => $this->targetOutputs($submission),
            'budget' => SubmissionBudgetSummary::run($viewer, $submission),
            'history' => $history,
            'decision' => $this->decision($submission, $history),
            // BR-REV-11/12 — dijaga di payload, bukan di CSS: skor yang sampai ke browser tak bisa
            // disembunyikan tampilan dengan aman. Alasannya ikut supaya layar bisa menjelaskan.
            'scores' => VisibleReviewerScores::run($viewer, $submission),
            // BR-FE-08/15/16 — peneliti melihat *status* perpanjangan tanpa alasannya; siapa boleh
            // membaca alasan diputus Policy.
            'phaseAccess' => PhaseAccessGate::presentFor($submission, $detail, $viewer->can('viewOverrideReason', $submission)),
            'printableDocuments' => PrintableDocumentChoices::run($viewer, $submission),
            'can' => SubmissionAbilitiesData::forUser($viewer, $submission),
        ];
    }

    /** @param  list<SubmissionHistoryEntryData>  $history */
    private function header(User $viewer, FormSubmission $submission, ?FormPhaseDetail $detail, array $history): SubmissionDetailData
    {
        $role = $this->viewerRole($viewer, $submission);
        $trl = $submission->trl;

        return new SubmissionDetailData(
            id: $submission->id,
            title: $submission->form->title,
            scheme: $submission->scheme?->name,
            period: $submission->submissionPeriod?->name,
            trl: $trl === null ? null : "TRL {$trl->level} — {$trl->name}",
            leadName: $submission->submittedBy->name,
            status: SubmissionStatusData::of($submission),
            // Riwayat terurut terbaru dulu; submit pertama adalah entri submit terakhir di daftar.
            submittedAt: $this->firstMatching(array_reverse($history), SubmitFormSubmission::AUDIT_DESCRIPTION)?->at,
            isArchived: $submission->resolveIsArchived(),
            viewerRole: $role,
            viewerRoleLabel: self::ROLE_LABELS[$role],
            editUrl: $this->editUrl($viewer, $submission, $detail),
        );
    }

    /** @return 'lead_researcher'|'co_investigator'|'member'|'viewer' */
    private function viewerRole(User $viewer, FormSubmission $submission): string
    {
        if ($submission->submitted_by === $viewer->id) {
            return 'lead_researcher';
        }

        $role = $submission->researchMembers()
            ->where('status', 'active')
            ->whereHas('userProfile', fn (Builder $q) => $q->where('user_id', $viewer->id))
            ->value('role');

        return match ($role) {
            ResearchMember::ROLE_CO_INVESTIGATOR => ResearchMember::ROLE_CO_INVESTIGATOR,
            ResearchMember::ROLE_MEMBER => ResearchMember::ROLE_MEMBER,
            default => 'viewer',
        };
    }

    private function editUrl(User $viewer, FormSubmission $submission, ?FormPhaseDetail $detail): ?string
    {
        if (!$viewer->can('update', $submission) || $submission->submission_period_id === null || $detail === null) {
            return null;
        }

        return route('user.form-phase', [$submission->submission_period_id, $detail->form_phase_id]);
    }

    /** @return list<SubmissionMemberData> */
    private function members(FormSubmission $submission): array
    {
        $members = [new SubmissionMemberData($submission->submittedBy->name, self::ROLE_LABELS['lead_researcher'])];
        $active = $submission->researchMembers()->where('status', 'active')->with('userProfile.user:id,name')->orderBy('id')->get();

        foreach ($active as $member) {
            $members[] = new SubmissionMemberData(
                name: $member->userProfile->user->name ?? '—',
                roleLabel: self::ROLE_LABELS[$member->role] ?? self::ROLE_LABELS[ResearchMember::ROLE_MEMBER],
            );
        }

        return $members;
    }

    /** @return list<SubmissionTargetOutputData> */
    private function targetOutputs(FormSubmission $submission): array
    {
        $targets = $submission->targetOutputs()->orderBy('id')->get(['id', 'output_type', 'description']);
        $names = [];

        foreach (OutputTypeDefinition::query()->whereIn('code', $targets->pluck('output_type'))->get(['code', 'name']) as $type) {
            $names[$type->code] = $type->name;
        }

        return array_values($targets->map(fn (SubmissionTargetOutput $target): SubmissionTargetOutputData => new SubmissionTargetOutputData(
            type: $names[$target->output_type] ?? $target->output_type,
            description: $target->description,
        ))->all());
    }

    /** @param  list<SubmissionHistoryEntryData>  $history */
    private function decision(FormSubmission $submission, array $history): ?SubmissionDecisionData
    {
        if (!$submission->status instanceof Rejected) {
            return null;
        }

        $entry = $this->firstMatching($history, RejectSubmission::AUDIT_DESCRIPTION);

        return new SubmissionDecisionData($submission->rejection_reason, $entry?->actor, $entry?->at);
    }

    /** @param  list<SubmissionHistoryEntryData>  $history */
    private function firstMatching(array $history, string $description): ?SubmissionHistoryEntryData
    {
        foreach ($history as $entry) {
            if ($entry->description === $description) {
                return $entry;
            }
        }

        return null;
    }
}
