<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read FormType|null $formType
 * @property-read Collection<int, FormField> $formFields
 * @property-read Collection<int, FormAccessControl> $formAccessControls
 * @property-read FormDraft|null $draft
 */
/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $form_type_id
 * @property bool $is_active
 * @property Carbon|null $published_at BR-FE-34 — terbit pertama; null = belum pernah terbit
 * @property int $field_count dimuat `withCount` FormDirectory
 * @property int $active_submission_count dimuat `withCount` FormDirectory
 * @property int $submission_count dimuat `withCount` FormDirectory
 * @property int $step_count dimuat `withCount` FormDirectory
 */
class Form extends Model
{
    use HasFactory;

    /**
     * BR-FE-19 — Form dinonaktifkan, bukan dihapus, begitu ia pernah dipakai.
     * Sebelumnya form_submissions.form_id ber-cascade, memusnahkan riwayat
     * pengajuan lewat delapan tabel — kebijakan sebesar itu tak pernah tertulis.
     */
    protected static function booted(): void
    {
        static::deleting(function (Form $form): void {
            // BR-FE-32 — FK aturan akses ber-cascade ke langkah; langkah versi terbit tidak boleh hilang.
            ReferenceGuard::assertUnreferenced('Formulir', [
                'pengajuan' => $form->formSubmissions()->count(),
                'langkah tahap' => $form->formPhaseDetails()->count(),
            ]);
        });
    }

    protected $fillable = ['title', 'description', 'is_active', 'form_type_id'];

    protected $casts = ['is_active' => 'boolean', 'published_at' => 'datetime'];

    /**
     * @return BelongsTo<FormType, $this>
     */
    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function formFields(): HasMany
    {
        return $this->hasMany(FormField::class);
    }

    /** @return HasOne<FormDraft, $this> BR-FE-34 — field yang sedang disunting, belum terbit */
    public function draft(): HasOne
    {
        return $this->hasOne(FormDraft::class, 'draftable_id');
    }

    /** @return HasManyThrough<FormPhaseDetail, FormAccessControl, $this> langkah tahap di versi mana pun */
    public function formPhaseDetails(): HasManyThrough
    {
        return $this->hasManyThrough(FormPhaseDetail::class, FormAccessControl::class);
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return HasMany<FormAccessControl, $this>
     */
    public function formAccessControls(): HasMany
    {
        return $this->hasMany(FormAccessControl::class);
    }

    /** @return array{title: string, description: string|null, form_type_id: int, is_active: bool, fields: array<int, string>} */
    public function auditSnapshot(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'form_type_id' => $this->form_type_id,
            'is_active' => $this->is_active,
            'fields' => $this->formFields()->orderBy('order')->get()
                ->mapWithKeys(fn (FormField $field): array => [$field->id => $field->label])
                ->all(),
        ];
    }
}
