<?php

namespace App\Queries\ResearchOutput;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\ResearchOutput\OutputSubmissionGroupData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W142 — Daftar Luaran peneliti (RESEARCHER-06-01): induk yang diketuai atau diikuti (BR-SM-07),
 * APPROVED, atau WITHDRAWN yang punya luaran (RO-OUTPUT-10). Tanpa paginasi (preseden W128).
 *
 * @method static list<OutputSubmissionGroupData> run(User $user)
 */
class ResearcherOutputList implements ChangesNoState
{
    use AsAction;

    /** @return Builder<FormSubmission> */
    public static function query(User $user): Builder
    {
        return SubmissionVisibility::ownedOrJoinedBy($user)
            ->whereNull('parent_submission_id')
            ->whereHas('form', fn (Builder $form) => $form->where('form_type_id', '!=', FormType::BIODATA_ID))
            ->where(fn (Builder $status) => $status
                ->whereState('status', Approved::class)
                // Pengajuan tarikan tanpa luaran tidak punya isi untuk dibaca.
                ->orWhere(fn (Builder $withdrawn) => $withdrawn->whereState('status', Withdrawn::class)->has('researchOutputs')));
    }

    /** @return list<OutputSubmissionGroupData> */
    public function handle(User $user): array
    {
        return array_values(self::query($user)
            ->with(['form:id,title', 'scheme:id,name', 'formPhaseDetail'])
            ->latest('id')
            ->get()
            ->map(fn (FormSubmission $submission): OutputSubmissionGroupData => new OutputSubmissionGroupData(
                submissionId: $submission->id,
                title: $submission->form->title,
                scheme: $submission->scheme?->name,
                status: SubmissionStatusData::of($submission),
                access: OutputWriteAccess::for($user, $submission),
                outputs: OutputRows::forSubmission($submission),
            ))
            ->all());
    }
}
