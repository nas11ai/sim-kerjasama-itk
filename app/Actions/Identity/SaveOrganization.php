<?php

namespace App\Actions\Identity;

use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-17 — membuat unit organisasi, mengganti namanya, dan memindah
 * induknya.
 *
 * Tiga aksi di satu Action karena ketiganya satu use case: menyusun pohon
 * organisasi. Yang memisahkannya bukan operasinya melainkan penjagaannya, dan
 * penjagaan yang paling menentukan hanya berlaku pada pemindahan.
 *
 * Pemindahan **ditolak** kalau menjadikan sebuah unit keturunan dirinya
 * sendiri. Access control BR-FE-02 menelusuri rantai leluhur lewat CTE
 * rekursif, dan pohon bersiklus membuat penelusuran itu tidak berhenti —
 * kegagalannya muncul sebagai timeout di layar peneliti yang tidak bersalah,
 * jauh dari tempat kesalahannya dibuat.
 *
 * @method static Organization run(User $actor, array{name?: string, type?: string, parent_id?: int|null} $attributes, ?Organization $organization = null)
 */
class SaveOrganization
{
    use AsAction;

    /**
     * @param  array{name?: string, type?: string, parent_id?: int|null}  $attributes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, array $attributes, ?Organization $organization = null): Organization
    {
        if (!$actor->can('users.manage')) {
            throw new AuthorizationException('Hanya Operator yang dapat menyusun struktur organisasi.');
        }

        $parentId = array_key_exists('parent_id', $attributes) ? $attributes['parent_id'] : $organization?->parent_id;

        if ($parentId !== null) {
            $this->guardParent($parentId, $organization);
        }

        if ($organization === null) {
            return $this->create($actor, $attributes, $parentId);
        }

        return $this->update($actor, $organization, $attributes, $parentId);
    }

    /** @param  array{name?: string, type?: string, parent_id?: int|null}  $attributes */
    private function create(User $actor, array $attributes, ?int $parentId): Organization
    {
        $tercatat = Organization::create([
            'name' => $attributes['name'] ?? '',
            'type' => $attributes['type'] ?? 'unit',
            'parent_id' => $parentId,
            'is_active' => true,
        ]);

        AuditTrail::record(
            'Unit organisasi dibuat',
            $tercatat,
            $actor,
            new AuditChange(
                new: [
                    'name' => $tercatat->name,
                    'parent_id' => $tercatat->parent_id,
                ],
            ),
        );

        return $tercatat;
    }

    /** @param  array{name?: string, type?: string, parent_id?: int|null}  $attributes */
    private function update(User $actor, Organization $organization, array $attributes, ?int $parentId): Organization
    {
        $sebelum = ['name' => $organization->name, 'parent_id' => $organization->parent_id];

        $organization->update(array_filter([
            'name' => $attributes['name'] ?? null,
            'type' => $attributes['type'] ?? null,
        ], static fn (?string $nilai): bool => $nilai !== null) + ['parent_id' => $parentId]);

        $tercatat = $organization->refresh();

        AuditTrail::record(
            'Unit organisasi diperbarui',
            $tercatat,
            $actor,
            new AuditChange(
                old: $sebelum,
                new: [
                    'name' => $tercatat->name,
                    'parent_id' => $tercatat->parent_id,
                ],
            ),
        );

        return $tercatat;
    }

    /**
     * @throws ValidationException
     */
    private function guardParent(int $parentId, ?Organization $organization): void
    {
        $parent = Organization::find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Unit induk yang dipilih tidak ditemukan.',
            ]);
        }

        if ($organization === null) {
            return;
        }

        if ($parentId === $organization->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Sebuah unit tidak bisa menjadi induk dirinya sendiri.',
            ]);
        }

        // Induk baru yang berada di dalam subtree unit ini akan menutup pohon
        // jadi lingkaran.
        if (in_array($parentId, Organization::subtreeIds($organization->id), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Unit ini tidak bisa dipindah ke bawah turunannya sendiri — '
                    .'pohon organisasi akan menjadi lingkaran dan penelusuran akses tidak pernah berhenti.',
            ]);
        }
    }
}
