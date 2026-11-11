<?php

namespace App\Models\Concerns;

use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rujukan tanggal lama di langkah fase (`submission_date_id`, `evaluation_date_id`,
 * `revision_date_id`) selama expand R1 (ADR 0019). Penulis aplikasi tidak lagi mengisinya; hanya
 * fixture lama yang masih menulis kolom ini. Trait ini dihapus utuh di Task 8 bersama ketiga kolomnya.
 *
 * @mixin FormPhaseDetail
 */
trait HasStepDateReferences
{
    /**
     * BR-FE-07 — fixture yang masih memilih `submission_date_id` ikut mengisi jenis + siklus
     * langkah dari tanggal itu.
     */
    protected static function bootHasStepDateReferences(): void
    {
        static::saving(function (FormPhaseDetail $detail): void {
            if ($detail->submission_date_id === null || !$detail->isDirty('submission_date_id')) {
                return;
            }

            $date = SubmissionDate::query()->findOrFail($detail->submission_date_id);
            $detail->deadline_type = $date->type;
            $detail->deadline_cycle = $date->cycle;
        });
    }

    /** @return BelongsTo<SubmissionDate, $this> */
    public function submissionDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class);
    }

    /**
     * BR-FE-26 — Batas Evaluasi reviewer form evaluasi di detail ini; wajib bila `needs_review`.
     *
     * @return BelongsTo<SubmissionDate, $this>
     */
    public function evaluationDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class, 'evaluation_date_id');
    }

    /**
     * BR-FE-26 — Batas Revisi pengisi saat NEEDS_REVISION; tanpanya revisi memakai Batas Submit.
     *
     * @return BelongsTo<SubmissionDate, $this>
     */
    public function revisionDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class, 'revision_date_id');
    }

    /**
     * BR-FE-26 — tenggat detail ini untuk satu jenis. Batas Revisi yang kosong kembali ke
     * Batas Submit (perilaku sebelum E1b); Batas Evaluasi yang kosong berarti tak bertenggat.
     */
    public function deadlineOf(DeadlineType $type): ?SubmissionDate
    {
        return match ($type) {
            // W139 — detail Upload Luaran memegang Batas Akhir di kolom Batas Submit-nya.
            DeadlineType::Submit, DeadlineType::Output => $this->submissionDate,
            DeadlineType::Evaluation => $this->evaluationDate,
            DeadlineType::Revision => $this->revisionDate ?? $this->submissionDate,
        };
    }

    /**
     * BR-FE-21 — detail yang digerbangi salah satu tanggal ini, lewat kolom tenggat mana pun.
     *
     * @param  Builder<FormPhaseDetail>  $query
     * @param  Builder<SubmissionDate>  $dates  subquery `select id`
     * @return Builder<FormPhaseDetail>
     */
    public function scopeDeadlinedBy(Builder $query, Builder $dates): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereIn('submission_date_id', $dates)
            ->orWhereIn('evaluation_date_id', $dates)
            ->orWhereIn('revision_date_id', $dates));
    }
}
