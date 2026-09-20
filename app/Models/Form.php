<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read FormType|null $formType
 * @property-read Collection<int, FormField> $formFields
 * @property-read Collection<int, FormAccessControl> $formAccessControls
 */
/**
 * @property int $id
 * @property string $title
 * @property bool $is_active
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
            ReferenceGuard::assertUnreferenced('Formulir', [
                'pengajuan' => $form->formSubmissions()->count(),
            ]);
        });
    }

    protected $fillable = ['title', 'description', 'is_active', 'form_type_id'];

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

    /**
     * @return HasMany<FormAccessControl, $this>
     */
    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function formAccessControls(): HasMany
    {
        return $this->hasMany(FormAccessControl::class);
    }
}
