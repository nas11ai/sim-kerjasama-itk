<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $form_field_id
 * @property string $label
 * @property string|null $value
 * @property int|null $order
 */
class FormFieldOption extends Model
{
    protected $fillable = ['form_field_id', 'label'];

    public function formField()
    {
        return $this->belongsTo(FormField::class);
    }
}
