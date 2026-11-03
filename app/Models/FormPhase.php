<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property bool $is_active
 * @property int $form_phase_details_count hanya setelah withCount('formPhaseDetails')
 * @property int $submission_count hanya setelah FormPhaseCards
 * @property-read SubmissionPeriod|null $submissionPeriod
 */
class FormPhase extends Model
{
    /**
     * BR-FE-20 — perluasan BR-FE-19 ke tahap. Sebelumnya, detail yang dirujuk
     * pengajuan gagal di tingkat basis data dan mendarat di layar Admin
     * sebagai SQLSTATE mentah — integritasnya selamat kebetulan, bukan aturan.
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

    /** @return HasMany<SubmissionPeriodPhase, $this> */
    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /**
     * W92 — tahap milik sebuah Form bila salah satu detailnya memakai aturan akses Form itu.
     *
     * @param  Builder<FormPhase>  $query
     * @return Builder<FormPhase>
     */
    public function scopeUsingForm(Builder $query, Form $form): Builder
    {
        return $query->whereHas(
            'formPhaseDetails.formAccessControl',
            fn (Builder $rules) => $rules->where('form_id', $form->id),
        );
    }

    public function usesForm(Form $form): bool
    {
        return self::query()->whereKey($this->id)->usingForm($form)->exists();
    }
}
