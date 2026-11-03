<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $form_id
 * @property int $field_type_id
 * @property string $label
 * @property bool $is_required
 * @property Carbon|null $required_since
 * @property int $order
 * @property int $form_field_responses_count dimuat `withCount` FormGeneral (BR-FE-23)
 * @property-read Form|null $form
 * @property-read FieldType|null $fieldType
 * @property-read Collection<int, FormFieldOption> $formFieldOptions
 * @property-read Collection<int, FormFieldResponse> $formFieldResponses
 */
class FormField extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'form_id',
        'label',
        'is_required',
        'required_since',
        'field_type_id',
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'required_since' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (FormField $field) {

            if ($field->is_required) {
                $field->required_since = Carbon::now();
            }
        });

        // BR-FE-23 — bentuk field terkunci begitu ada jawaban, beda dari
        // BR-FE-11 (kewajiban) dan BR-FE-12 (keberadaan). `FormFieldResponse.value`
        // tanpa tipe: ubah tipe field merusak baca/validasi jawaban lama diam-diam.
        static::updating(function (FormField $field): void {
            if (!$field->isDirty('field_type_id')) {
                return;
            }

            $terjawab = FormFieldResponse::query()->where('form_field_id', $field->id)->count();

            if ($terjawab > 0) {
                throw ValidationException::withMessages([
                    'field_type_id' => "Tipe field ini tidak dapat diubah karena sudah ada {$terjawab} jawaban yang tersimpan. "
                        .'Tambahkan field baru dengan tipe yang diinginkan; field lama tetap terbaca apa adanya.',
                ]);
            }
        });

        static::updating(function (FormField $field) {

            $wasRequired = $field->getOriginal('is_required');
            $isNowRequired = $field->is_required;

            if (!$wasRequired && $isNowRequired) {
                $field->required_since = Carbon::now();
            }
        });
    }

    public function isRequiredFor(FormSubmission $submission): bool
    {
        if ($this->created_at > $submission->created_at) {
            return false;
        }

        if ($this->required_since?->gt($submission->created_at)) {
            return false;
        }

        return $this->is_required;
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<FieldType, $this>
     */
    public function fieldType(): BelongsTo
    {
        return $this->belongsTo(FieldType::class);
    }

    /**
     * @return HasMany<FormFieldOption, $this>
     */
    public function formFieldOptions(): HasMany
    {
        return $this->hasMany(FormFieldOption::class);
    }

    /**
     * @return HasMany<FormFieldResponse, $this>
     */
    public function formFieldResponses(): HasMany
    {
        return $this->hasMany(FormFieldResponse::class);
    }
}
