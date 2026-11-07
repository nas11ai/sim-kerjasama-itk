<?php

namespace App\Data\Result;

/**
 * Penghuni sebuah unit dan subtree-nya. Satu definisi untuk layar ADMIN-08-02 dan penjaga
 * DeactivateOrganization (BR-IAM-14) / DeleteOrganization (BR-IAM-08).
 */
final class OrganizationCensusData
{
    public function __construct(
        public readonly int $directProfiles,
        public readonly int $directActiveUsers,
        public readonly int $subtreeProfiles,
        public readonly int $subtreeActiveUsers,
        public readonly int $unitsWithActiveUsers,
        public readonly int $pendingInvitations,
    ) {}

    /** BR-IAM-14 — ambang deaktivasi adalah user aktif. */
    public function blocksDeactivation(): bool
    {
        return $this->subtreeActiveUsers > 0;
    }

    /** BR-IAM-08 — ambang penghapusan adalah profil apa pun. */
    public function blocksDeletion(): bool
    {
        return $this->subtreeProfiles > 0;
    }
}
