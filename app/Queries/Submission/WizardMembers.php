<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Wizard\WizardMemberRowData;
use App\Data\View\Submission\Wizard\WizardMembersData;
use App\Data\View\Submission\Wizard\WizardPartnerRowData;
use App\Data\View\Submission\Wizard\WizardStudentRowData;
use App\Models\FormSubmission;
use App\Models\Partner;
use App\Models\ResearchMember;
use App\Models\StudentMember;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Langkah 4 wizard (RESEARCHER-02-08). Batas dibaca dari Scheme yang sama dengan
 * AddResearchMember/AddStudentMember, supaya kuota di layar tidak berselisih dengan penolakannya.
 *
 * @method static WizardMembersData run(User $viewer, FormSubmission $submission)
 */
class WizardMembers implements ChangesNoState
{
    use AsAction;

    public function handle(User $viewer, FormSubmission $submission): WizardMembersData
    {
        $submission->loadMissing(['scheme', 'submittedBy.userProfile.organization']);
        $roleOptions = [];

        foreach (ResearchMember::ROLE_LABELS as $value => $label) {
            $roleOptions[] = ['value' => $value, 'label' => $label];
        }

        return new WizardMembersData(
            members: $this->members($viewer, $submission),
            students: array_values($submission->studentMembers()->orderBy('id')->get()
                ->map(fn (StudentMember $s): WizardStudentRowData => new WizardStudentRowData($s->id, $s->name, $s->student_id, $s->study_program))
                ->all()),
            partners: array_values($submission->partners()->orderBy('id')->get()
                ->map(fn (Partner $p): WizardPartnerRowData => new WizardPartnerRowData($p->id, $p->institution_name, $p->contact_person))
                ->all()),
            memberLimit: $submission->scheme?->memberLimit(),
            studentLimit: $submission->scheme?->studentMemberLimit(),
            roleOptions: $roleOptions,
        );
    }

    /** @return list<WizardMemberRowData> */
    private function members(User $viewer, FormSubmission $submission): array
    {
        $lead = $submission->submittedBy;
        // BR-SM-18 — ketua tidak bisa dikeluarkan; kepemilikan dialihkan lewat Operator.
        $rows = [new WizardMemberRowData(0, $lead->name, 'lead_researcher', 'Ketua', $lead->userProfile?->organization?->name, $lead->userProfile?->nidn, false)];
        // Sumbu yang sama dengan RemoveResearchMember.
        $removable = $viewer->can('manageResearchMembers', $submission);
        $active = $submission->researchMembers()->where('status', 'active')
            ->with('userProfile.user:id,name', 'userProfile.organization:id,name')->orderBy('id')->get();

        foreach ($active as $member) {
            $rows[] = $this->memberRow($member, $removable);
        }

        return $rows;
    }

    private function memberRow(ResearchMember $member, bool $removable): WizardMemberRowData
    {
        $profile = $member->userProfile;

        return new WizardMemberRowData(
            id: $member->id,
            name: $profile->user->name ?? '—',
            role: $member->role,
            roleLabel: ResearchMember::ROLE_LABELS[$member->role] ?? ResearchMember::ROLE_LABELS[ResearchMember::ROLE_MEMBER],
            unit: $profile->organization?->name,
            nidn: $profile->nidn,
            removable: $removable,
        );
    }
}
