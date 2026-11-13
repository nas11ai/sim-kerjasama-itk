<?php

namespace Database\Seeders\Qa;

use App\Models\Reviewer;
use App\Models\User;

/** Akun satu penguji. `researchers[n]` = pemilik skenario baris n tabel desain (1..12). */
final readonly class QaSet
{
    /**
     * @param  array{0: Reviewer, 1: Reviewer}  $reviewers  penilai internal
     * @param  array<int, User>  $researchers
     */
    public function __construct(
        public int $k,
        public User $operator,
        public array $reviewers,
        public Reviewer $externalReviewer,
        public array $researchers,
        public User $pending,
        public User $rejected,
        public User $member,
    ) {}

    public function tag(): string
    {
        return "QA{$this->k}";
    }
}
