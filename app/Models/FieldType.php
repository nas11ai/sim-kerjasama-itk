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

    /** W103 — tipe yang boleh dibatasi panjang jawabannya (min/max_length). */
    public const LENGTH_LIMIT_TYPES = ['text', 'textarea', 'email', 'url'];

    /** W103 — tipe yang boleh dibatasi nilai jawabannya (min/max_value), mis. skor 1–7. */
    public const VALUE_LIMIT_TYPES = ['number'];

    protected $fillable = ['name'];
}
