<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
class FieldType extends Model
{
    /** Tipe yang jawabannya id salah satu opsi field, bukan teks bebas. */
    public const OPTION_TYPES = ['select', 'radio'];

    protected $fillable = ['name'];
}
