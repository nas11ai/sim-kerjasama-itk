<?php

namespace App\Actions\Identity;

use App\Models\InvitationToken;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-16 — Operator menerbitkan undangan, jalan masuk mitra eksternal
 * (BR-IAM-12). Akunnya aktif seketika dengan permission dari token, jadi
 * penerbitan adalah pemberian akses dan wewenangnya dibatasi.
 *
 * @method static InvitationToken run(User $actor, Organization $organization, array{permissions?: list<string>, max_uses?: int|null, expires_at?: string|null} $attributes)
 */
class IssueInvitation
{
    use AsAction;

    /**
     * @param  array{permissions?: list<string>, max_uses?: int|null, expires_at?: string|null}  $attributes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, Organization $organization, array $attributes = []): InvitationToken
    {
        $this->guardIssuable($actor, $organization);

        $maxUses = $this->maxUses($attributes);
        $expiresAt = $this->expiresAt($attributes);

        $tercatat = InvitationToken::create([
            'token' => Str::random(64),
            'organization_id' => $organization->id,
            'permissions' => $attributes['permissions'] ?? [],
            'max_uses' => $maxUses,
            'used_count' => 0,
            'expires_at' => $expiresAt,
            'created_by' => $actor->id,
        ]);

        // BR-RPT-09 — token-nya sendiri tidak ikut dicatat. Jejak audit dibaca
        // lebih banyak orang daripada yang berhak memakai undangannya.
        AuditTrail::record(
            'Undangan registrasi diterbitkan',
            $tercatat,
            $actor,
            new AuditChange(
                new: [
                    'organization_id' => $tercatat->organization_id,
                    'permissions' => $tercatat->permissions,
                    'max_uses' => $tercatat->max_uses,
                    'expires_at' => $tercatat->expires_at?->toDateTimeString(),
                ],
            ),
        );

        return $tercatat;
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    private function guardIssuable(User $actor, Organization $organization): void
    {
        if (!$actor->can('manage', InvitationToken::class)) {
            throw new AuthorizationException('Hanya Operator yang dapat menerbitkan undangan registrasi.');
        }

        // BR-IAM-06 dan BR-IAM-14 — menerbitkan undangan ke unit yang sudah
        // dipensiunkan melahirkan token yang tidak pernah bisa dipakai. Ditolak
        // di sini supaya kegagalannya terlihat penerbitnya, bukan pendaftarnya.
        if (!$organization->is_active) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organisasi ini sudah nonaktif, jadi undangannya tidak akan pernah berlaku.',
            ]);
        }
    }

    /** @param  array{permissions?: list<string>, max_uses?: int|null, expires_at?: string|null}  $attributes */
    private function maxUses(array $attributes): ?int
    {
        $maxUses = $attributes['max_uses'] ?? null;

        if ($maxUses !== null && $maxUses < 1) {
            throw ValidationException::withMessages([
                'max_uses' => 'Kuota pemakaian minimal satu.',
            ]);
        }

        return $maxUses;
    }

    /** @param  array{permissions?: list<string>, max_uses?: int|null, expires_at?: string|null}  $attributes */
    private function expiresAt(array $attributes): ?Carbon
    {
        // isset() sudah menyingkirkan null sekaligus key yang tidak ada;
        // memeriksa null lagi setelahnya adalah perbandingan yang tidak pernah
        // salah, dan itu menyembunyikan maksudnya alih-alih menjaganya.
        $expiresAt = isset($attributes['expires_at'])
            ? Carbon::parse($attributes['expires_at'])
            : null;

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw ValidationException::withMessages([
                'expires_at' => 'Masa berlaku undangan tidak bisa ditetapkan di masa lalu.',
            ]);
        }

        return $expiresAt;
    }
}
