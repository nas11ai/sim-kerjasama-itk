<?php

namespace App\Models;

use App\Data\Request\EvaluationDraftInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BR-FE-31 (K2) — isi konfigurasi yang sedang disunting dan belum berlaku, satu per objek.
 * `draftable_type` konstanta di kelas ini (G2-W7), bukan nama kelas. Satu-satunya jenis kini Form
 * Evaluasi, jadi bentuk payload-nya bentuk draf Form Evaluasi; R6 melebarkannya bila jenis bertambah.
 *
 * @phpstan-import-type DraftPayload from EvaluationDraftInput
 *
 * @property int $id
 * @property string $draftable_type
 * @property int $draftable_id
 * @property DraftPayload $payload
 * @property int|null $updated_by
 * @property Carbon $updated_at
 */
class ConfigurationDraft extends Model
{
    public const EVALUATION_FORM = 'review_evaluation_form';

    protected $fillable = [
        'draftable_type',
        'draftable_id',
        'payload',
        'updated_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
