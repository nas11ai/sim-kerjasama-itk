<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BR-FE-31 / BR-FE-33 / BR-FE-34 (K2) — isi konfigurasi yang sedang disunting dan belum berlaku, satu per
 * objek. G3-W7 — basis membaca semua jenis; subkelas memaku `draftable_type` dan bentuk payload-nya.
 *
 * @property int $id
 * @property string $draftable_type
 * @property int $draftable_id
 * @property int|null $updated_by
 * @property Carbon $updated_at
 */
class ConfigurationDraft extends Model
{
    public const EVALUATION_FORM = 'review_evaluation_form';

    public const PHASE = 'form_phase';

    public const FORM = 'form';

    protected $table = 'configuration_drafts';

    protected $fillable = [
        'draftable_type',
        'draftable_id',
        'payload',
        'updated_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /** Jenis yang dipaku subkelas; null = basis, semua jenis. */
    protected static function draftableType(): ?string
    {
        return null;
    }

    protected static function booted(): void
    {
        $type = static::draftableType();

        if ($type === null) {
            return;
        }

        static::addGlobalScope('draftable_type', fn (Builder $query) => $query->where('configuration_drafts.draftable_type', $type));
        static::creating(fn (ConfigurationDraft $draft) => $draft->draftable_type = $type);
    }
}
