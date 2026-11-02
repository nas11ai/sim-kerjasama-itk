<?php

namespace App\Enums;

use App\Models\Reviewer;
use App\Queries\Review\ReviewersWithinTenure;
use Illuminate\Database\Eloquent\Builder;

/**
 * BR-REV-09, BR-IAM-05 — kedudukan reviewer di OPERATOR-08-04. Akun nonaktif didahulukan:
 * yang berhenti adalah orangnya, bukan periodenya, dan masa tugasnya tidak bisa diubah.
 * Masa tugas memakai definisi inklusif yang sama dengan penugasan (ReviewersWithinTenure).
 */
enum ReviewerStanding: string
{
    case Active = 'active';

    case NotStarted = 'not_started';

    case Ended = 'ended';

    case AccountInactive = 'account_inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::NotStarted => 'Belum Mulai',
            self::Ended => 'Berakhir',
            self::AccountInactive => 'Akun Nonaktif',
        };
    }

    /** @return array{value: string, label: string} */
    public function option(): array
    {
        return ['value' => $this->value, 'label' => $this->label()];
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $standing): array => $standing->option(), self::cases());
    }

    public static function of(Reviewer $reviewer): self
    {
        if (!($reviewer->user->is_active ?? false)) {
            return self::AccountInactive;
        }

        if ($reviewer->isWithinTenure()) {
            return self::Active;
        }

        return $reviewer->start_date !== null && $reviewer->start_date->isFuture() ? self::NotStarted : self::Ended;
    }

    /** @param  Builder<Reviewer>  $query */
    public static function scope(Builder $query, self $standing): void
    {
        if ($standing === self::AccountInactive) {
            $query->whereHas('user', fn (Builder $user) => $user->where('is_active', false));

            return;
        }

        $query->whereHas('user', fn (Builder $user) => $user->where('is_active', true));
        $withinTenure = ReviewersWithinTenure::run()->select('id');

        match ($standing) {
            self::Active => $query->whereIn('id', $withinTenure),
            self::NotStarted => $query->whereDate('start_date', '>', today()),
            self::Ended => $query->whereNotIn('id', $withinTenure)
                ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', today())),
        };
    }
}
