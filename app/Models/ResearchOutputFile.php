<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-RO-04 — berkas bukti luaran.
 *
 * Tipe `ip` dan `prototype` wajib punya minimal satu; tipe lain boleh tanpa.
 *
 * @property int $id
 * @property int $research_output_id
 * @property string $file_path
 * @property string|null $file_url
 * @property string|null $original_name nama asli unggahan (W141); null untuk baris lama
 * @property int|null $size byte
 */
class ResearchOutputFile extends Model
{
    protected $fillable = ['research_output_id', 'file_path', 'file_url', 'original_name', 'size'];

    /** @var array<string, string> */
    protected $casts = ['size' => 'integer'];

    /** @return BelongsTo<ResearchOutput, $this> */
    public function researchOutput(): BelongsTo
    {
        return $this->belongsTo(ResearchOutput::class);
    }
}
