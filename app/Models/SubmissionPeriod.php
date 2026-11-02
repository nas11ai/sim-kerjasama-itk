<?php

namespace App\Models;

use App\Notifications\SubmissionPeriodClosedNotification;
use App\Queries\Period\PeriodDraftOwners;
use App\Queries\Period\SubmissionPeriodReferences;
use App\Support\Lifecycle\ReferenceGuard;
use App\Support\Notification\Deliver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * @property bool $is_active
 * @property string $status
 * @property bool $is_force_closed
 * @property Carbon|string|null $start_date
 * @property Carbon|string|null $end_date
 * @property int|null $days_remaining
 * @property int $user_submissions_count
 * @property int $user_draft_count
 * @property int $user_submitted_count
 * @property int $total_submissions
 * @property int $approved_submissions
 * @property int $pending_review
 * @property int $submitted_count hasil withCount SubmissionPeriodList
 * @property-read Collection<int, SubmissionDate> $submissionDates
 * @property-read Collection<int, SubmissionPeriodPhase> $submissionPeriodPhases
 */
class SubmissionPeriod extends Model
{
    use HasFactory;

    /**
     * BR-FE-21 — perluasan BR-FE-19 ke periode; SubmissionDate miliknya ikut
     * terlindungi: BR-FE-07 mewajibkan setiap FormPhaseDetail punya deadline,
     * jadi menghapus tanggalnya membuat aturan Form Engine tak terpenuhi.
     */
    protected static function booted(): void
    {
        static::deleting(function (SubmissionPeriod $period): void {
            ReferenceGuard::assertUnreferenced('Periode pengajuan', SubmissionPeriodReferences::run($period));
        });
    }

    protected $fillable = ['name', 'is_force_closed'];

    protected $casts = [
        'is_force_closed' => 'boolean',
    ];

    /**
     * BR-SCH-07 — periode yang masih bisa dipakai tahap baru: belum ditutup paksa dan
     * masih punya tanggal di depan.
     *
     * @param  Builder<SubmissionPeriod>  $query
     * @return Builder<SubmissionPeriod>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_force_closed', false)
            ->whereHas('submissionDates', fn (Builder $dates) => $dates->where('datetime', '>', now()));
    }

    /** @return HasMany<SubmissionDate, $this> */
    public function submissionDates(): HasMany
    {
        return $this->hasMany(SubmissionDate::class);
    }

    /** @return HasMany<FormSubmission, $this> */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return HasMany<SubmissionPeriodPhase, $this> */
    public function submissionPeriodPhases(): HasMany
    {
        return $this->hasMany(SubmissionPeriodPhase::class);
    }

    public function forceClose(): void
    {
        $this->update([
            'is_force_closed' => true,
        ]);

        Log::info('Submission period force closed', [
            'submission_period_id' => $this->id,
            'submission_period_name' => $this->name,
            'closed_at' => now(),
        ]);
    }

    /**
     * BR-SM-10 — menutup periode dan memberi tahu pemilik draft terdampak.
     *
     * Menutup periode membekukan draft, tidak menghapusnya. Pemberitahuan
     * bagian aksi ini: tanpa itu peneliti baru sadar saat gagal menyunting.
     */
    public function closeAndNotifyDraftOwners(): void
    {
        $this->update(['is_force_closed' => true]);

        $owners = PeriodDraftOwners::run($this);

        Deliver::to($owners, new SubmissionPeriodClosedNotification($this));
    }
}
