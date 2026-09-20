<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Luaran yang **dijanjikan** sebuah pengajuan (BR-SM-14, BR-SM-15).
 *
 * Bedakan dari `ResearchOutput` (**terealisasi**): kecocokan sengaja tidak
 * ditegakkan — memaksanya cocok mendorong peneliti berbohong (register #19).
 *
 * @property int $id
 * @property int $form_submission_id
 * @property string $output_type
 * @property string|null $description
 */
class SubmissionTargetOutput extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['form_submission_id', 'output_type', 'description'];

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /**
     * BR-SM-15 mewajibkan perubahan pasca-APPROVED tercatat lengkap dengan
     * nilai lama dan barunya — itulah gunanya logOnlyDirty + nilai lama.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['output_type', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
