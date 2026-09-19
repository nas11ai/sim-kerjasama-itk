<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Taksonomi tipe luaran — dipakai bersama target yang dijanjikan (BR-SM-14)
 * dan luaran terealisasi (BR-RO-05).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property array<int, array{key?: string, required?: bool}>|null $fields
 * @property bool $requires_files
 * @property string|null $submission_type
 */
class OutputTypeDefinition extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'fields', 'requires_files', 'submission_type'];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'requires_files' => 'boolean',
        'fields' => 'array',
    ];

    /**
     * BR-SC-01 — dua tabel yang menautkan diri ke taksonomi ini.
     *
     * Tautannya lewat `code`, bukan foreign key: itulah sebabnya kode tidak
     * pernah boleh berubah setelah dibuat, sementara namanya bebas diganti
     * (BR-SC-03).
     *
     * @return HasMany<ResearchOutput, $this>
     */
    public function researchOutputs(): HasMany
    {
        return $this->hasMany(ResearchOutput::class, 'output_type', 'code');
    }

    /**
     * @return HasMany<SubmissionTargetOutput, $this>
     */
    public function submissionTargetOutputs(): HasMany
    {
        return $this->hasMany(SubmissionTargetOutput::class, 'output_type', 'code');
    }

    /**
     * BR-RO-09 — kunci metadata yang wajib diisi untuk tipe ini.
     *
     * Spesifikasinya tinggal di tabel, bukan config kode: kalau menambah tipe
     * masih menuntut menyunting kode untuk mendefinisikan field-nya, janji
     * BR-RO-05 ("tipe baru langsung bisa dipakai") baru terpenuhi separuh.
     *
     * @return list<string>
     */
    public function requiredMetadataKeys(): array
    {
        $keys = [];

        foreach ($this->fields ?? [] as $field) {
            // Bentuk tiap entri sudah dinyatakan docblock `fields`; yang perlu
            // dijaga adalah entri yang tidak menandai dirinya wajib atau tidak
            // menyebut kuncinya — keduanya data yang belum lengkap, bukan tipe
            // yang salah.
            if (($field['required'] ?? false) !== true || !isset($field['key'])) {
                continue;
            }

            $keys[] = $field['key'];
        }

        return $keys;
    }

    /** Kode yang boleh dipakai saat ini. @return list<string> */
    public static function activeCodes(): array
    {
        return self::query()->where('is_active', true)->pluck('code')->all();
    }
}
