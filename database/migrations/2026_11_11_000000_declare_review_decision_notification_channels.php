<?php

use Illuminate\Database\Migrations\Migration;

/**
 * BR-NOT-06, ADR 0018 — deklarasi kanal lima event keputusan review:
 * SubmissionReadyForDecision, RevisionRequested, RecheckRequested,
 * ProposalApproved, ProposalRejected. Tanpa barisnya, kesimpulan putaran dan
 * keputusan Operator menjawab 500 di database yang sudah hidup.
 *
 * Pengisinya dipinjam dari migration 2026_10_04, bukan disalin: ia hanya
 * menyisipkan event katalog yang belum punya baris, jadi aman dijalankan ulang
 * dan tidak menyentuh suntingan Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $declareMissing = require __DIR__.'/2026_10_04_100000_declare_missing_notification_channels.php';
        $declareMissing->up();
    }

    /** Sengaja kosong, dengan alasan yang sama seperti migration yang dipinjamnya. */
    public function down(): void {}
};
