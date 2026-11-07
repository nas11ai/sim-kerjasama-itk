<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Monev\MonevSubmissionData;
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
 * W128 — Daftar Monev peneliti (RESEARCHER-05-01): induk yang diketuai atau diikuti
 * (BR-SM-07), APPROVED, atau WITHDRAWN yang sudah punya laporan (MON-CYCLE-11).
 * Tanpa paginasi: jumlah per peneliti kecil.
 *
 * @method static list<MonevSubmissionData> run(User $user)
 */
class ResearcherMonevList implements ChangesNoState
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
                // Induk tarikan tanpa laporan tidak punya isi untuk dibaca.
                ->orWhere(fn (Builder $withdrawn) => $withdrawn->whereState('status', Withdrawn::class)->has('children')));
    }

    /** @return list<MonevSubmissionData> */
    public function handle(User $user): array
    {
        return array_values(self::query($user)
            ->with(['form:id,title', 'scheme:id,name', 'formPhaseDetail'])
            ->latest('id')
            ->get()
            ->map(fn (FormSubmission $parent): MonevSubmissionData => new MonevSubmissionData(
                id: $parent->id,
                title: $parent->form->title,
                scheme: $parent->scheme?->name,
                status: SubmissionStatusData::of($parent),
                canFill: $user->can('startMonevReport', $parent),
                cycles: MonevCycles::run($parent),
            ))
            ->all());
    }
}
