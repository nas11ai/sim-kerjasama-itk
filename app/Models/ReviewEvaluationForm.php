<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_phase_detail_id
 * @property string $title
 * @property string|null $description
 * @property bool $is_required
 * @property bool $is_active
 * @property int $order
 * @property-read FormPhaseDetail|null $formPhaseDetail
 * @property-read int|null $reviewer_form_assignments_count hanya setelah withCount
 */
class ReviewEvaluationForm extends Model
{
    use HasFactory;

    /**
     * BR-FE-22 — form evaluasi yang pernah ditugaskan tidak bisa dihapus.
     *
     * Form evaluasi adalah dasar sebuah keputusan review; menghapusnya berarti
     * menghapus alasan kenapa sebuah pengajuan dinilai sebagaimana ia dinilai.
     */
    protected static function booted(): void
    {
        static::deleting(function (ReviewEvaluationForm $form): void {
            ReferenceGuard::assertUnreferenced('Form evaluasi', [
                'penugasan reviewer' => $form->reviewerFormAssignments()->count(),
            ]);
        });
    }

    protected $fillable = [
        'title',
        'description',
        'form_phase_detail_id', // Changed from form_phase_id
        'is_required',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<FormPhaseDetail, $this> */
    public function formPhaseDetail(): BelongsTo
    {
        return $this->belongsTo(FormPhaseDetail::class, 'form_phase_detail_id');
    }

    /** @return HasMany<ReviewFormField, $this> */
    public function reviewFormFields(): HasMany
    {
        return $this->hasMany(ReviewFormField::class);
    }

    /**
     * Get the reviewer form assignments for the evaluation form.
     *
     * @return HasMany<ReviewerFormAssignment, $this>
     */
    public function reviewerFormAssignments(): HasMany
    {
        return $this->hasMany(ReviewerFormAssignment::class);
    }

    /**
     * Scope a query to only include active forms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include required forms.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to order by the order column.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /** Form evaluasi baru diletakkan paling akhir di antara form evaluasi detail yang sama. */
    public static function nextOrderFor(int $formPhaseDetailId): int
    {
        $last = static::query()->where('form_phase_detail_id', $formPhaseDetailId)->orderByDesc('order')->first();

        return ($last->order ?? 0) + 1;
    }

    /** @return array{title: string, description: string|null, form_phase_detail_id: int, is_required: bool, is_active: bool, fields: array<int, string>} */
    public function auditSnapshot(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'form_phase_detail_id' => $this->form_phase_detail_id,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'fields' => $this->reviewFormFields()->orderBy('order')->get()
                ->mapWithKeys(fn (ReviewFormField $field): array => [$field->id => $field->label])
                ->all(),
        ];
    }
}
