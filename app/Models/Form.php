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
     *
     * Sebelum aturan ini, form_submissions.form_id ber-cascade: menghapus satu
     * Form menghapus setiap pengajuannya, lalu delapan tabel ikut cascade dari
     * form_submission_id. Satu klik Admin memusnahkan seluruh riwayat sebuah
     * form beserta anggota, anggaran, evaluasi, dan luarannya — kebijakan
     * sebesar itu tidak pernah ditulis sebagai aturan mana pun.
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
