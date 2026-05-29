<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Database\Eloquent\Collection<int, FormField> $formFields
 */
class Form extends Model
{
    use HasFactory;
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
    public function formFields()
    {
        return $this->hasMany(FormField::class);
    }

    /**
     * @return HasMany<FormAccessControl, $this>
     */
    public function formAccessControls()
    {
        return $this->hasMany(FormAccessControl::class);
    }
}
