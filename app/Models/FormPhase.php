<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $review_evaluation_forms_count
 * @property int $required_review_evaluation_forms_count
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class FormPhase extends Model
{
    /**
     * BR-FE-20 — perluasan BR-FE-19 ke tahap.
     *
     * Detail yang dirujuk pengajuan sebelumnya gagal di tingkat basis data dan
     * mendarat di layar Admin sebagai SQLSTATE mentah. Integritasnya selamat
     * secara kebetulan, bukan karena aturan.
     */
    protected static function booted(): void
    {
        static::deleting(function (FormPhase $phase): void {
            ReferenceGuard::assertUnreferenced('Tahap formulir', [
                'pengajuan' => FormSubmission::query()
                    ->whereIn('form_phase_detail_id', $phase->formPhaseDetails()->select('id'))
                    ->count(),
            ]);
        });
    }

    protected $fillable = ['title', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
    }

    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }
}
