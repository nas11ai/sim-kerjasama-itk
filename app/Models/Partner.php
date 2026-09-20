<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-SM-21 — mitra eksternal sebuah pengajuan.
 *
 * Bedakan dari `ResearchOutput` tipe `pks`: di sini yang **dijanjikan**,
 * di sana yang **terwujud** — pola sama dengan `SubmissionTargetOutput`.
 *
 * @property int $id
 * @property int $form_submission_id
 * @property string $institution_name
 * @property string|null $contact_person
 * @property string|null $description
 */
class Partner extends Model
{
    use HasFactory;

    protected $table = 'submission_partners';

    protected $fillable = [
        'form_submission_id',
        'institution_name',
        'contact_person',
        'description',
    ];

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }
}
