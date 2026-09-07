<?php

namespace App\Models;

use Carbon\Carbon;
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
     * FormPhaseDetail yang menggerbangi fasenya dengan tanggal ini.
     *
     * Dipakai untuk menolak penghapusan tanggal yang masih menjadi batas
     * sebuah fase — foreign key sudah menjaganya di database, tapi menabraknya
     * berarti seluruh penyuntingan periode gagal alih-alih menyisakan yang
     * memang masih dipakai.
     *
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
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
