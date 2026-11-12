<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * BR-FE-32 — versi struktur tahap yang terbit: langkah, urutan, Form + aturan akses, jenis/siklus
 * tenggat, `needs_review`, dan Form Evaluasi terpasang. Tidak pernah berubah; hapus hanya lewat
 * cascade saat tahapnya dihapus (BR-FE-20 menghitung pengajuan di semua versi).
 *
 * @property int $id
 * @property int $form_phase_id
 * @property int $number
 * @property int|null $published_by
 * @property Carbon $published_at
 * @property-read FormPhase $formPhase
 * @property-read User|null $publisher
 */
class FormPhaseVersion extends Model
{
    protected $fillable = [
        'form_phase_id',
        'number',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'number' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Versi struktur tahap yang terbit tidak dapat diubah.');
        });
    }

    /** @return BelongsTo<FormPhase, $this> */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<FormPhaseDetail, $this> */
    public function formPhaseDetails(): HasMany
    {
        return $this->hasMany(FormPhaseDetail::class)->orderBy('order')->orderBy('id');
    }

    public function label(): string
    {
        return self::labelFor($this->number);
    }

    public static function labelFor(int $number): string
    {
        return "Versi {$number}";
    }
}
