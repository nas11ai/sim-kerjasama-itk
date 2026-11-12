<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property bool $is_active
 * @property int $form_phase_details_count hanya setelah withCount('formPhaseDetails')
 * @property int $submission_count hanya setelah PhaseLibrary
 * @property string|null $submission_form_title hanya setelah PhaseLibrary
 * @property-read SubmissionPeriod|null $submissionPeriod
 * @property-read FormPhaseVersion|null $latestVersion
 * @property-read PhaseDraft|null $draft
 * @property-read bool|null $draft_exists hanya setelah withExists('draft')
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
     * Langkah di semua versi (BR-FE-32) — penjaga hapus BR-FE-20 menghitung pengajuan di mana pun.
     *
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class);
    }

    /** @return HasMany<FormPhaseVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FormPhaseVersion::class)->orderBy('number');
    }

    /** @return HasOne<FormPhaseVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(FormPhaseVersion::class)->latestOfMany('number');
    }

    /** @return HasOne<PhaseDraft, $this> BR-FE-33 — struktur yang sedang disunting, belum terbit */
    public function draft(): HasOne
    {
        return $this->hasOne(PhaseDraft::class, 'draftable_id');
    }

    /**
     * Langkah versi terbaru — yang disunting editor dan dipakai angkatan yang belum terpaku.
     *
     * @return HasMany<FormPhaseDetail, $this>
     */
    public function currentDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class)
            ->inLatestVersion()
            ->orderBy('form_phase_details.order')
            ->orderBy('form_phase_details.id');
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
     * Tahap memakai sebuah Form bila langkah versi terbarunya — atau langkah drafnya, untuk tahap
     * yang belum terbit (BR-FE-33) — memakai aturan akses Form itu (tapis pustaka, R2).
     *
     * @param  Builder<FormPhase>  $query
     * @return Builder<FormPhase>
     */
    public function scopeUsingForm(Builder $query, Form $form): Builder
    {
        $rules = FormAccessControl::query()->select('id')->where('form_id', $form->id);

        return $query->where(fn (Builder $using) => $using
            ->whereHas('currentDetails', fn (Builder $steps) => $steps->whereIn('form_access_control_id', $rules))
            ->orWhereHas('draft', fn (Builder $draft) => $draft->whereExists(fn ($step) => $step
                ->selectRaw('1')
                ->fromRaw("jsonb_array_elements(configuration_drafts.payload->'steps') as step")
                ->whereIn(DB::raw("(step->>'form_access_control_id')::bigint"), $rules))));
    }
}
