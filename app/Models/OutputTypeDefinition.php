<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Taksonomi tipe luaran — dipakai bersama target yang dijanjikan (BR-SM-14)
 * dan luaran terealisasi (BR-RO-05).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class OutputTypeDefinition extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean'];

    /** Kode yang boleh dipakai saat ini. @return list<string> */
    public static function activeCodes(): array
    {
        return self::query()->where('is_active', true)->pluck('code')->all();
    }
}
