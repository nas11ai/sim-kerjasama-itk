<?php

namespace App\Models;

use App\Enums\DeadlineType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $submission_period_id
 * @property DeadlineType $type
 * @property int|null $cycle
 * @property \Illuminate\Support\Carbon $datetime
 * @property int $form_phase_details_count
 * @property int $evaluation_phase_details_count
 * @property int $revision_phase_details_count
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class SubmissionDate extends Model
{
    /** Tiga kolom tenggat `form_phase_details` yang menunjuk tanggal ini (BR-FE-26). */
    public const PHASE_DETAIL_RELATIONS = ['formPhaseDetails', 'evaluationPhaseDetails', 'revisionPhaseDetails'];

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
     * BR-FE-26 — detail yang memakai tanggal ini sebagai Batas Evaluasi.
     *
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function evaluationPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class, 'evaluation_date_id');
    }

    /**
     * BR-FE-26 — detail yang memakai tanggal ini sebagai Batas Revisi.
     *
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function revisionPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class, 'revision_date_id');
    }

    /**
     * BR-FE-21 — tanggal yang tidak menggerbangi detail mana pun, lewat kolom tenggat mana pun.
     *
     * @param  Builder<SubmissionDate>  $query
     * @return Builder<SubmissionDate>
     */
    public function scopeWithoutPhaseDetail(Builder $query): Builder
    {
        return $query->whereDoesntHave('formPhaseDetails')
            ->whereDoesntHave('evaluationPhaseDetails')
            ->whereDoesntHave('revisionPhaseDetails');
    }

    /**
     * Banyak detail yang digerbangi tanggal ini (BR-FE-07, BR-FE-26). Daftar periode memuat
     * hitungannya sekaligus (`withCount(PHASE_DETAIL_RELATIONS)`); selain itu dimuat di sini.
     */
    public function phaseDetailCount(): int
    {
        if (!array_key_exists('form_phase_details_count', $this->getAttributes())) {
            $this->loadCount(self::PHASE_DETAIL_RELATIONS);
        }

        return $this->form_phase_details_count
            + $this->evaluation_phase_details_count
            + $this->revision_phase_details_count;
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
