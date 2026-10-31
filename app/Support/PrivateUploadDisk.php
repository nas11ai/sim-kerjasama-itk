<?php

namespace App\Support;

// fe-v2-design W15 — unggahan pengguna tidak pernah punya URL publik
final class PrivateUploadDisk
{
    /** Disk `local` (storage/app/private); satu-satunya jalan baca adalah route unduh ber-scope. */
    public const NAME = 'local';
}
