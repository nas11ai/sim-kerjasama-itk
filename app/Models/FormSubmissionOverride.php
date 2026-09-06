<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property int $form_phase_detail_id
 * @property int $granted_by
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property-read FormSubmission $formSubmission
 * @property-read FormPhaseDetail $formPhaseDetail
 * @property-read User $grantedBy
 */
class FormSubmissionOverride extends Model
{
    use LogsActivity;

    protected $fillable = [
        'form_submission_id',
        'form_phase_detail_id',
        'granted_by',
        'reason',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return BelongsTo<FormPhaseDetail, $this> */
    public function formPhaseDetail(): BelongsTo
    {
        return $this->belongsTo(FormPhaseDetail::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isValid(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * BR-SM-05 dan BR-FE-09 — override adalah pengecualian terhadap aturan,
     * jadi ia yang paling perlu meninggalkan jejak: siapa yang memberikannya,
     * dengan alasan apa, dan berlaku sampai kapan. Tanpa ini, pengecualian
     * tidak bisa diaudit dan berhenti bisa dipertanggungjawabkan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['form_submission_id', 'form_phase_detail_id', 'granted_by', 'reason', 'expires_at', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
