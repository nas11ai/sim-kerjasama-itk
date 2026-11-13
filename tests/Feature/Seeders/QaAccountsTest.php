<?php

// Akun satu set penguji (docs/design/2026-11-13-seeder-data-uji-qa-design.md, "Akun per set").

use App\Enums\VerificationStatus;
use App\Models\InvitationToken;
use App\Models\Reviewer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Qa\QaAccounts;
use Illuminate\Support\Facades\Storage;

it('membuat akun set dengan peran dan status verifikasi yang benar', function () {
    Storage::fake(config('filesystems.default'));
    $this->seed(DatabaseSeeder::class);

    $set = QaAccounts::build(2);

    $researchers = collect($set->researchers);
    expect($researchers->keys()->all())->toBe(range(1, 12))
        ->and($set->researchers[7]->email)->toBe('qa2-peneliti07@simpas.test')
        ->and($researchers->every(fn (User $u): bool => $u->hasRole('researcher')
            && $u->userProfile->verification_status === VerificationStatus::Active
            && $u->userProfile->isComplete()))->toBeTrue()
        ->and($set->pending->userProfile->verification_status)->toBe(VerificationStatus::Pending)
        ->and($set->rejected->userProfile->verification_status)->toBe(VerificationStatus::Rejected)
        ->and($set->member->userProfile->verification_status)->toBe(VerificationStatus::Active)
        ->and($set->operator->hasRole('operator'))->toBeTrue()
        ->and($set->reviewers[0]->reviewer_type)->toBe('internal')
        ->and($set->externalReviewer->reviewer_type)->toBe('external')
        ->and(Reviewer::query()->count())->toBe(3)
        ->and(collect([...$set->reviewers, $set->externalReviewer])->every(fn (Reviewer $r): bool => $r->isWithinTenure(now())))->toBeTrue()
        ->and(InvitationToken::query()->count())->toBe(2)
        ->and(InvitationToken::query()->whereNotNull('revoked_at')->count())->toBe(1);

    $accounts = User::where('email', 'like', 'qa2-%')->get();
    // operator + 3 penilai + 12 peneliti + anggota + peneliti-baru + peneliti-ditolak
    expect($accounts)->toHaveCount(19)
        ->and($accounts->every(fn (User $u): bool => str_ends_with($u->email, '@simpas.test')))->toBeTrue()
        ->and($accounts->every(fn (User $u): bool => str_starts_with($u->name, 'QA2 ')))->toBeTrue();
});
