<?php

namespace App\Models;

use App\Data\Request\PhaseDraftInput;

/**
 * BR-FE-33 — draf struktur tahap: langkah beserta Form Evaluasi terpasangnya.
 *
 * @phpstan-import-type PhaseDraftPayload from PhaseDraftInput
 *
 * @property PhaseDraftPayload $payload
 */
class PhaseDraft extends ConfigurationDraft
{
    protected static function draftableType(): string
    {
        return self::PHASE;
    }
}
