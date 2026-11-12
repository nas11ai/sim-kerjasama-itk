<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Form Evaluasi pustaka (BR-REV-18): judul dan deskripsi tak berversi; isinya ada di
 * ReviewEvaluationFormVersion, dipasang ke langkah lewat StepEvaluationForm.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property-read ReviewEvaluationFormVersion|null $latestVersion
 * @property-read ConfigurationDraft|null $draft
 * @property-read int|null $reviewer_form_assignments_count hanya setelah withCount
 * @property-read int|null $step_evaluation_forms_count hanya setelah withCount
 * @property-read bool|null $draft_exists hanya setelah withExists
 */
class ReviewEvaluationForm extends Model
{
    use HasFactory;

    /**
     * BR-FE-22 — form evaluasi yang pernah ditugaskan atau masih terpasang tidak bisa dihapus.
     *
     * Form evaluasi adalah dasar sebuah keputusan review; menghapusnya berarti
     * menghapus alasan kenapa sebuah pengajuan dinilai sebagaimana ia dinilai.
     */
    protected static function booted(): void
    {
        static::deleting(function (ReviewEvaluationForm $form): void {
            ReferenceGuard::assertUnreferenced('Form evaluasi', [
                'penugasan reviewer' => $form->reviewerFormAssignments()->count(),
                'pemasangan di langkah' => $form->stepEvaluationForms()->count(),
            ]);
        });
    }

    protected $fillable = [
        'title',
        'description',
    ];

    /** @return HasMany<ReviewEvaluationFormVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ReviewEvaluationFormVersion::class)->orderBy('number');
    }

    /** @return HasOne<ReviewEvaluationFormVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(ReviewEvaluationFormVersion::class)->latestOfMany('number');
    }

    /** @return HasOne<ConfigurationDraft, $this> BR-FE-31 — isi yang sedang disunting, belum terbit */
    public function draft(): HasOne
    {
        return $this->hasOne(ConfigurationDraft::class, 'draftable_id')
            ->where('configuration_drafts.draftable_type', ConfigurationDraft::EVALUATION_FORM);
    }

    /** @return HasMany<StepEvaluationForm, $this> */
    public function stepEvaluationForms(): HasMany
    {
        return $this->hasMany(StepEvaluationForm::class);
    }

    /** @return HasMany<ReviewerFormAssignment, $this> */
    public function reviewerFormAssignments(): HasMany
    {
        return $this->hasMany(ReviewerFormAssignment::class);
    }

    /** @return array{title: string, description: string|null} */
    public function auditSnapshot(): array
    {
        return ['title' => $this->title, 'description' => $this->description];
    }
}
