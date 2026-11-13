<?php

namespace App\Models;

use App\Enums\DeadlineType;
use App\Support\Lifecycle\ReferenceGuard;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

/**
 * @property int $id
 * @property int $form_phase_id
 * @property int $form_phase_version_id
 * @property int $form_access_control_id
 * @property int $phase_type_id
 * @property int $order
 * @property bool $needs_review
 * @property DeadlineType $deadline_type BR-FE-07 — `submit` atau `output`
 * @property int|null $deadline_cycle BR-FE-07 — null = pengajuan/luaran, n = laporan Monev n
 * @property int $submission_count hanya setelah PhaseEdit
 * @property int $assignment_count hanya setelah PhaseEdit
 * @property int $evaluation_form_count hanya setelah PhaseEdit
 * @property-read FormPhase|null $formPhase
 * @property-read FormPhaseVersion $formPhaseVersion
 * @property-read FormAccessControl|null $formAccessControl
 * @property-read PhaseType|null $phaseType
 */
class FormPhaseDetail extends Model
{
    use HasFactory;

    /**
     * BR-FE-20 — detail yang dirujuk pengajuan tidak bisa dihapus. BR-FE-22 — begitu pula detail
     * yang form evaluasinya pernah ditugaskan: tanpa penjaga ini form evaluasinya ikut ter-cascade.
     * BR-FE-32 — langkah milik versi terbit, jadi tidak pernah diubah; perubahan = versi baru.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Langkah versi struktur yang terbit tidak dapat diubah.');
        });

        static::deleting(function (FormPhaseDetail $detail): void {
            ReferenceGuard::assertUnreferenced('Detail tahap', [
                'pengajuan' => FormSubmission::query()
                    ->where('form_phase_detail_id', $detail->id)
                    ->count(),
                'penugasan form evaluasi' => ReviewerFormAssignment::query()
                    ->whereHas('submissionReviewer.formSubmission', fn (Builder $q) => $q->where('form_phase_detail_id', $detail->id))
                    ->count(),
            ]);
        });
    }

    protected $fillable = [
        'form_phase_id',
        'form_phase_version_id',
        'form_access_control_id',
        'phase_type_id',
        'order',
        'needs_review',
        'deadline_type',
        'deadline_cycle',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'deadline_type' => DeadlineType::class,
        'deadline_cycle' => 'integer',
    ];

    /** @return BelongsTo<FormPhase, $this> */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    /** @return BelongsTo<FormAccessControl, $this> */
    public function formAccessControl(): BelongsTo
    {
        return $this->belongsTo(FormAccessControl::class);
    }

    /** @return BelongsTo<PhaseType, $this> */
    public function phaseType(): BelongsTo
    {
        return $this->belongsTo(PhaseType::class);
    }

    /** @return BelongsTo<FormPhaseVersion, $this> */
    public function formPhaseVersion(): BelongsTo
    {
        return $this->belongsTo(FormPhaseVersion::class);
    }

    /** @return HasMany<StepEvaluationForm, $this> Formulir Evaluasi pustaka yang terpasang di langkah ini */
    public function stepEvaluationForms(): HasMany
    {
        return $this->hasMany(StepEvaluationForm::class);
    }

    /** @return HasMany<StepEvaluationForm, $this> */
    public function activeStepEvaluationForms(): HasMany
    {
        return $this->stepEvaluationForms()->active()->ordered();
    }

    /** @return HasMany<StepEvaluationForm, $this> */
    public function requiredStepEvaluationForms(): HasMany
    {
        return $this->activeStepEvaluationForms()->required();
    }

    public function hasReviewEvaluationForms(): bool
    {
        return $this->activeStepEvaluationForms()->exists();
    }

    /**
     * BR-MON-01 — pintu pengajuan baru hanya langkah `submit` tanpa siklus (BR-FE-07). Laporan
     * monev (bersiklus) dan Upload Luaran (`output`) lahir dari pengajuan APPROVED.
     */
    public function opensSubmission(): bool
    {
        return $this->deadline_type === DeadlineType::Submit && $this->deadline_cycle === null;
    }

    /** BR-FE-25 — nama tanggal yang dibaca langkah ini di kalender periode, mis. "Batas Laporan Monev I". */
    public function deadlineLabel(): string
    {
        return $this->deadline_type->label($this->deadline_cycle);
    }

    /**
     * BR-FE-17, BR-FE-26 — jendela kerja satu aktor langkah ini di kalender `$period` (periode
     * pengajuan). Tanggal kerja yang belum diatur menutup; Batas Evaluasi yang belum diatur
     * berarti tak bertenggat.
     */
    public function isWithinDeadline(SubmissionPeriod $period, DeadlineType $type = DeadlineType::Submit): bool
    {
        // BR-SCH-07 — force close mengalahkan tanggal — sumbu yang berbeda.
        if (SubmissionPeriodGate::isForceClosed($period, $this)) {
            return false;
        }

        $date = $period->deadlineFor($this, $type);

        if ($date === null) {
            return $type === DeadlineType::Evaluation;
        }

        return now()->lte($date->datetime);
    }

    /**
     * BR-FE-32 — hanya langkah versi terbaru tahapnya (yang disunting editor dan dipakai angkatan
     * yang belum terpaku). Berkorelasi per baris, jadi aman untuk banyak tahap sekaligus.
     *
     * @param  Builder<FormPhaseDetail>  $query
     * @return Builder<FormPhaseDetail>
     */
    public function scopeInLatestVersion(Builder $query): Builder
    {
        return $query->where('form_phase_details.form_phase_version_id', fn (QueryBuilder $latest) => $latest
            ->select('v.id')
            ->from('form_phase_versions as v')
            ->whereColumn('v.form_phase_id', 'form_phase_details.form_phase_id')
            ->orderByDesc('v.number')
            ->limit(1));
    }

    /**
     * @param  Builder<FormPhaseDetail>  $query
     * @return Builder<FormPhaseDetail>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
