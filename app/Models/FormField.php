<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $label
 * @property bool $is_required
 * @property int $order
 * @property string|null $helper_text
 * @property-read FieldType $fieldType
 * @property-read Collection<int, FormFieldOption> $formFieldOptions
 *
 * @return HasMany<FormFieldOption>
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

        if (
            $this->required_since !== null &&
            $this->required_since > $submission->created_at
        ) {
            return false;
        }

        return $this->is_required;
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function fieldType(): BelongsTo
    {
        return $this->belongsTo(FieldType::class);
    }

    /**
     * @return HasMany<FormFieldOption, $this>
     */
    public function formFieldOptions()
    {
        return $this->hasMany(FormFieldOption::class);
    }

    public function formFieldResponses()
    {
        return $this->hasMany(FormFieldResponse::class);
    }
}
