<?php

namespace App\Models;

use App\Notifications\SubmissionPeriodClosedNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * @property bool $is_active
 * @property string $status
 * @property bool $is_force_closed
 * @property Carbon|string|null $start_date
 * @property Carbon|string|null $end_date
 * @property mixed $form_phases
 * @property int|null $days_remaining
 * @property int $user_submissions_count
 * @property int $user_draft_count
 * @property int $user_submitted_count
 * @property int $total_submissions
 * @property int $approved_submissions
 * @property int $pending_review
 * @property-read Collection<int, SubmissionDate> $submissionDates
 * @property-read Collection<int, SubmissionPeriodPhase> $submissionPeriodPhases
 */
class SubmissionPeriod extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_force_closed'];

    protected $casts = [
        'is_force_closed' => 'boolean',
    ];

    /** @return HasMany<SubmissionDate, $this> */
    public function submissionDates(): HasMany
    {
        return $this->hasMany(SubmissionDate::class);
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
     * BR-SM-10 — menutup periode dan memberi tahu pemilik draft yang
     * terdampak.
     *
     * Menutup periode membekukan draft, tidak menghapusnya. Pemberitahuannya
     * bagian dari aksi itu: tanpa ini peneliti baru sadar saat mencoba
     * menyunting dan ditolak.
     */
    public function closeAndNotifyDraftOwners(): void
    {
        $this->update(['is_force_closed' => true]);

        $owners = User::query()
            ->whereIn(
                'id',
                FormSubmission::query()
                    ->where('submission_period_id', $this->id)
                    ->where('is_submitted', false)
                    ->select('submitted_by')
            )
            ->get();

        Notification::send($owners, new SubmissionPeriodClosedNotification($this));
    }
}
