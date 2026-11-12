<?php

namespace App\Models;

use App\Data\Request\EvaluationDraftInput;

/**
 * BR-FE-31 — draf isi Form Evaluasi.
 *
 * @phpstan-import-type DraftPayload from EvaluationDraftInput
 *
 * @property DraftPayload $payload
 */
class EvaluationFormDraft extends ConfigurationDraft
{
    protected static function draftableType(): string
    {
        return self::EVALUATION_FORM;
    }
}
