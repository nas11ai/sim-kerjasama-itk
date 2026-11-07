<?php

namespace App\Models;

use App\Data\View\ResearchOutput\OutputFieldSpecData;
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
 * @property array<int, array{key?: string, label?: string, type?: string, required?: bool, options?: list<array{value: string, label: string}>, hint?: string, empty_label?: string}>|null $fields
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
     * Tautannya lewat `code`, bukan foreign key — makanya kode tidak boleh
     * berubah setelah dibuat, sementara nama bebas diganti (BR-SC-03).
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
     * BR-RO-09, W140 — spesifikasi field metadata tipe ini. Spesifikasinya tinggal di tabel,
     * bukan config kode: kalau menambah tipe masih menuntut menyunting kode, janji BR-RO-05
     * baru terpenuhi separuh. Entri tanpa `key` dilewati; tanpa `label`, kuncinya yang tampil.
     *
     * @return list<OutputFieldSpecData>
     */
    public function fieldSpecs(): array
    {
        return array_values(array_filter(array_map(OutputFieldSpecData::fromEntry(...), $this->fields ?? [])));
    }

    /** Kunci field pertama: judul baris luaran di daftar. Tanpa spesifikasi, null. */
    public function headlineKey(): ?string
    {
        return $this->fieldSpecs()[0]->key ?? null;
    }

    /** Kode yang boleh dipakai saat ini. @return list<string> */
    public static function activeCodes(): array
    {
        return self::query()->where('is_active', true)->pluck('code')->all();
    }
}
