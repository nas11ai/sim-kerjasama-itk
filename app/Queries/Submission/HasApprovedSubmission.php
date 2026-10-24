<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Apakah user punya pengajuan riset/pengabdian yang disetujui. Biodata tidak dihitung.
 *
 * @method static bool run(User $user)
 */
class HasApprovedSubmission implements ChangesNoState
{
    use AsAction;

    public function handle(User $user): bool
    {
        return FormSubmission::where('submitted_by', $user->id)
            ->whereState('status', Approved::class)
            ->whereHas('form', fn ($query) => $query->where('form_type_id', '!=', FormType::BIODATA_ID))
            ->exists();
    }
}
