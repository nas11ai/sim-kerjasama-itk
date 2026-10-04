<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $reviewer_type
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property bool $is_active
 * @property int $total_reviews
 * @property int $pending_reviews
 * @property int $completed_reviews
 * @property-read User|null $user
 * @property-read Collection<int, SubmissionReviewer> $submissionReviewers
 */
class Reviewer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reviewer_type',
        'start_date',
        'end_date',
    ];

    /**
     * Tanpa cast ini, `start_date`/`end_date` kembali sebagai string meski
     * PHPDoc menjanjikan CarbonInterface: pembanding tanggal (BR-REV-09) pecah
     * setelah reload, bukan di memori — lolos unit test, gagal di jalur HTTP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * BR-REV-09 — masa tugas inklusif per hari: end_date adalah hari terakhir
     * bertugas, jam tidak ikut dihitung. Versi query: ReviewersWithinTenure.
     */
    public function isWithinTenure(?CarbonInterface $on = null): bool
    {
        $day = ($on ?? now())->toDateString();

        $started = $this->start_date === null || $this->start_date->toDateString() <= $day;
        $notEnded = $this->end_date === null || $this->end_date->toDateString() >= $day;

        return $started && $notEnded;
    }

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
