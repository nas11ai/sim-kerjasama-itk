<?php

namespace App\Models;

use App\Enums\ReviewRoundKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-REV-16 — satu putaran review milik pengajuan utama. Hasil review hanya dihitung saat
 * putaran ini disimpulkan, yaitu saat peserta terakhirnya menekan Selesai.
 *
 * @property int $id
 * @property int $form_submission_id
 * @property int $number
 * @property ReviewRoundKind $kind
 * @property CarbonImmutable|null $completed_at
 * @property string|null $outcome
 * @property-read FormSubmission $formSubmission
 * @property-read Collection<int, ReviewRoundParticipant> $participants
 */
class ReviewRound extends Model
{
    public const OUTCOME_NEEDS_REVISION = 'needs_revision';

    public const OUTCOME_READY = 'ready';

    protected $fillable = [
        'form_submission_id',
        'number',
        'kind',
        'completed_at',
        'outcome',
    ];

    protected $casts = [
        'number' => 'integer',
        'kind' => ReviewRoundKind::class,
        'completed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return HasMany<ReviewRoundParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ReviewRoundParticipant::class);
    }

    /**
     * Peserta yang masih ditunggu. `not_required` dihitung selesai tanpa menekan apa pun
     * (BR-REV-16), sama seperti `SubmissionReviewer::SETTLED_EVALUATION`.
     *
     * @return HasMany<ReviewRoundParticipant, $this>
     */
    public function unfinishedParticipants(): HasMany
    {
        return $this->participants()
            ->whereNull('finished_at')
            ->whereHas('submissionReviewer', fn ($seat) => $seat->where('evaluation_status', '!=', 'not_required'));
    }

    public function isRunning(): bool
    {
        return $this->completed_at === null;
    }
}
