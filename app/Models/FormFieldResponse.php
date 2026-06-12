<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $form_submission_id
 * @property int $form_field_id
 * @property string|null $value
 * @property-read FormSubmission|null $formSubmission
 * @property-read FormField|null $formField
 */
class FormFieldResponse extends Model
{
    protected $fillable = [
        'form_submission_id',
        'form_field_id',
        'value',
    ];

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }
}
