<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BR-FE-32 — versi struktur yang dipakai satu angkatan (Periode × Tahap), dipaku saat pengajuan
 * pertama angkatan itu dibuat. Unik per (periode, tahap).
 *
 * @property int $id
 * @property int $submission_period_id
 * @property int $form_phase_id
 * @property int $form_phase_version_id
 * @property Carbon $pinned_at
 * @property-read SubmissionPeriod $submissionPeriod
 * @property-read FormPhase $formPhase
 * @property-read FormPhaseVersion $formPhaseVersion
 */
class PhaseVersionPin extends Model
{
    protected $fillable = [
        'submission_period_id',
        'form_phase_id',
        'form_phase_version_id',
        'pinned_at',
    ];

    protected $casts = [
        'pinned_at' => 'datetime',
    ];

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<FormPhaseVersion, $this> */
    public function formPhaseVersion(): BelongsTo
    {
        return $this->belongsTo(FormPhaseVersion::class);
    }

    /** @return BelongsTo<FormPhase, $this> */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }
}
