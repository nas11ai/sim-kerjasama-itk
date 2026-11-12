<?php

namespace App\Models;

use App\Data\Request\FormDraftInput;

/**
 * BR-FE-34 — draf field Form beserta opsinya; id field/opsi terbit ikut tersimpan.
 *
 * @phpstan-import-type FormDraftPayload from FormDraftInput
 *
 * @property FormDraftPayload $payload
 */
class FormDraft extends ConfigurationDraft
{
    protected static function draftableType(): string
    {
        return self::FORM;
    }
}
