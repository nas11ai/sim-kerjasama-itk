<?php

namespace App\Models;

use App\Enums\DeadlineType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_period_id
 * @property DeadlineType $type
 * @property int|null $cycle
 * @property \Illuminate\Support\Carbon $datetime
 * @property int $needing_step_count hanya setelah addSelect needingStepCount()
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class SubmissionDate extends Model
{
    protected $fillable = [
        'type',
        'cycle',
        'datetime',
        'submission_period_id',
    ];

    protected $casts = [
        'type' => DeadlineType::class,
        'cycle' => 'integer',
        'datetime' => 'datetime',
    ];

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** BR-FE-25 — label diturunkan dari jenis dan siklus, tidak disimpan. */
    public function label(): string
    {
        return $this->type->label($this->cycle);
    }

    /**
     * BR-FE-25 — satu periode punya paling banyak satu tanggal per (jenis, siklus).
     *
     * @param  Builder<SubmissionDate>  $query
     * @return Builder<SubmissionDate>
     */
    public function scopeOfType(Builder $query, DeadlineType $type, ?int $cycle = null): Builder
    {
        return $query->where('type', $type)
            ->when(
                $cycle === null,
                fn (Builder $q) => $q->whereNull('cycle'),
                fn (Builder $q) => $q->where('cycle', $cycle),
            );
    }

    /**
     * BR-FE-21 + BR-FE-26 (ADR 0019) — tanggal yang tidak dibutuhkan langkah mana pun dari fase yang
     * ditautkan periodenya. Tanggal yang dibutuhkan tidak boleh hilang saat periode direvisi: ia
     * masih menggerbangi langkah itu.
     *
     * @param  Builder<SubmissionDate>  $query
     * @return Builder<SubmissionDate>
     */
    public function scopeUnneededByLinkedPhases(Builder $query): Builder
    {
        return $query->whereNotExists(self::stepsNeedingIt()->selectRaw('1')->toBase());
    }

    /**
     * Subquery banyak langkah fase tertaut yang membaca tanggal ini; dimuat sebagai
     * `needing_step_count` (`addSelect`), juga lewat relasi `submissionDates`.
     *
     * @return Builder<FormPhaseDetail>
     */
    public static function needingStepCount(): Builder
    {
        return self::stepsNeedingIt()->selectRaw('count(*)');
    }

    /**
     * Langkah fase tertaut periode tanggal ini yang membacanya lewat `SubmissionPeriod::deadlineFor`:
     * submit/output pada jenis + siklus langkah, Batas Evaluasi dan Batas Revisi pada siklus langkah.
     * Subquery berkorelasi ke `submission_dates`.
     *
     * @return Builder<FormPhaseDetail>
     */
    private static function stepsNeedingIt(): Builder
    {
        return FormPhaseDetail::query()
            ->join('submission_period_phases', 'submission_period_phases.form_phase_id', '=', 'form_phase_details.form_phase_id')
            ->whereColumn('submission_period_phases.submission_period_id', 'submission_dates.submission_period_id')
            ->whereRaw('form_phase_details.deadline_cycle IS NOT DISTINCT FROM submission_dates.cycle')
            ->where(fn (Builder $q) => $q->whereColumn('form_phase_details.deadline_type', 'submission_dates.type')
                ->orWhereIn('submission_dates.type', [DeadlineType::Evaluation->value, DeadlineType::Revision->value]));
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
