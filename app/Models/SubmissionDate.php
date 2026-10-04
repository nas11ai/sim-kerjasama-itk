<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon $datetime
 * @property-read SubmissionDateLabel|null $submissionDateLabel
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class SubmissionDate extends Model
{
    protected $fillable = [
        'submission_date_label_id',
        'datetime',
        'submission_period_id',
    ];

    protected $casts = [
        'datetime' => 'datetime',
    ];

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<SubmissionDateLabel, $this> */
    public function submissionDateLabel(): BelongsTo
    {
        return $this->belongsTo(SubmissionDateLabel::class);
    }

    /**
     * FormPhaseDetail yang menggerbangi fasenya dengan tanggal ini. Dipakai
     * menolak penghapusan tanggal yang masih jadi batas fase — foreign key
     * sudah menjaga di database, tapi menabraknya menggagalkan seluruh periode.
     *
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
    }

    /**
     * BR-FE-17 (ADR 0014) — tenggat yang boleh dipasang ke detail tahap: milik periode yang
     * memakai tahap itu. Tahap yang belum dipakai periode mana pun (mis. saat dibuat) boleh
     * memakai tenggat periode yang belum ditutup paksa.
     *
     * @param  Builder<SubmissionDate>  $query
     * @return Builder<SubmissionDate>
     */
    public function scopeSelectableFor(Builder $query, ?FormPhase $phase): Builder
    {
        if ($phase === null || !$phase->submissionPeriodPhases()->exists()) {
            return $query->whereHas(
                'submissionPeriod',
                fn (Builder $period) => $period->where('is_force_closed', false),
            );
        }

        return $query->whereIn(
            'submission_period_id',
            $phase->submissionPeriodPhases()->select('submission_period_id'),
        );
    }

    public function isPassed(): bool
    {
        return Carbon::now()->gt($this->datetime);
    }

    public function isToday(): bool
    {
        return Carbon::now()->isSameDay($this->datetime);
    }

    public function isUpcoming(): bool
    {
        return Carbon::now()->lt($this->datetime);
    }

    public function getDaysFromNow(): int
    {
        return (int) Carbon::now()->diffInDays($this->datetime, false);
    }
}
