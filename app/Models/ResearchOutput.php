<?php

namespace App\Models;

use App\Support\Lifecycle\DeletionNotPermitted;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * BR-RO-01 s/d BR-RO-09 — luaran yang benar-benar terwujud.
 *
 * Bedakan dari `SubmissionTargetOutput` (BR-SM-14/15) yang **dijanjikan** —
 * kecocokan sengaja tidak ditegakkan, supaya peneliti tak didorong berbohong.
 *
 * @property int $id
 * @property int $form_submission_id
 * @property string $output_type
 * @property array<string, string|int|null>|null $metadata hanya ditulis `SaveResearchOutput` lewat Guard (W140)
 * @property int $created_by
 * @property-read FormSubmission $formSubmission
 * @property-read Collection<int, ResearchOutputFile> $files
 */
class ResearchOutput extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * BR-RO-06 — luaran tidak pernah dihapus, bukan hanya yang WITHDRAWN:
     * ia dasar pelaporan LPPM yang tidak boleh surut diam-diam. Koreksi
     * lewat penyuntingan (BR-RO-07) meninggalkan jejak; penghapusan tidak.
     */
    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new DeletionNotPermitted(
                'Luaran tidak dapat dihapus — ia dasar pelaporan dan tetap tersimpan '
                .'sebagai data historis (BR-RO-06). Luaran yang keliru diperbaiki '
                .'lewat penyuntingan, bukan dihilangkan.'
            );
        });
    }

    protected $fillable = [
        'form_submission_id',
        'output_type',
        'metadata',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['metadata' => 'array'];

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return HasMany<ResearchOutputFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ResearchOutputFile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Luaran memasok pelaporan LPPM, dan Operator boleh mengoreksinya
     * (BR-RO-07, register #35) — jejaknya harus menyebut siapa mengubah apa,
     * supaya koreksi staf tidak terbaca sebagai laporan peneliti.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['output_type', 'metadata'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
