<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W128/W142 — induk yang diketuai atau diikuti (BR-SM-07), APPROVED, atau WITHDRAWN yang masih
 * menyimpan isi lewat `$keptBy` (laporan monev, luaran). Satu bentuk untuk Daftar Monev dan
 * Daftar Luaran, beserta flag sidebar masing-masing.
 *
 * @method static Builder<FormSubmission> run(User $user, string $keptBy)
 */
class TeamParentSubmissions implements ChangesNoState
{
    use AsAction;

    /** @return Builder<FormSubmission> */
    public static function query(User $user, string $keptBy): Builder
    {
        return SubmissionVisibility::ownedOrJoinedBy($user)
            ->whereNull('parent_submission_id')
            ->whereHas('form', fn (Builder $form) => $form->where('form_type_id', '!=', FormType::BIODATA_ID))
            ->where(fn (Builder $status) => $status
                ->whereState('status', Approved::class)
                // Induk tarikan tanpa isi tidak punya apa pun untuk dibaca.
                ->orWhere(fn (Builder $withdrawn) => $withdrawn->whereState('status', Withdrawn::class)->has($keptBy)))
            ->with(['form:id,title', 'scheme:id,name', 'formPhaseDetail'])
            ->latest('id');
    }

    /** @return Builder<FormSubmission> */
    public function handle(User $user, string $keptBy): Builder
    {
        return self::query($user, $keptBy);
    }
}
