<?php

namespace App\Models;

use App\Enums\ReviewRoundKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-REV-16 — satu reviewer dalam satu putaran, beserta tanda Selesai-nya. Reviewer tidak bisa
 * membatalkannya; `finished_at` hanya dikosongkan saat penilaiannya dibuka ulang (BR-REV-19).
 *
 * @property int $id
 * @property int $review_round_id
 * @property int $submission_reviewer_id
 * @property ReviewRoundKind|null $kind
 * @property CarbonImmutable|null $finished_at
 * @property-read ReviewRound $reviewRound
 * @property-read SubmissionReviewer $submissionReviewer
 */
class ReviewRoundParticipant extends Model
{
    protected $fillable = [
        'review_round_id',
        'submission_reviewer_id',
        'kind',
        'finished_at',
    ];

    protected $casts = [
        'kind' => ReviewRoundKind::class,
        'finished_at' => 'immutable_datetime',
    ];

    /**
     * Menilai atau memeriksa: pemilik catatan bisa bergabung ke putaran penilaian yang masih
     * berjalan saat kirim ulang, dan ia tetap memeriksa. Null = baris lama, ikut jenis putaran.
     */
    public function effectiveKind(): ReviewRoundKind
    {
        return $this->kind ?? $this->reviewRound->kind;
    }

    /** @return BelongsTo<ReviewRound, $this> */
    public function reviewRound(): BelongsTo
    {
        return $this->belongsTo(ReviewRound::class);
    }

    /** @return BelongsTo<SubmissionReviewer, $this> */
    public function submissionReviewer(): BelongsTo
    {
        return $this->belongsTo(SubmissionReviewer::class);
    }
}
