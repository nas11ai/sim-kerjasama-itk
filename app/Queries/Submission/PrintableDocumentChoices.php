<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Reporting\PrintableDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-11 — hanya dokumen yang memang boleh dicetak orang ini yang ditawarkan. Menawarkan
 * semuanya lalu menolak saat ditekan membocorkan bahwa dokumennya ada, dan itu sudah setengah
 * jawaban yang ditahan BR-REV-12.
 *
 * @method static list<array{value: string, label: string}> run(User $user, FormSubmission $submission)
 */
class PrintableDocumentChoices implements ChangesNoState
{
    use AsAction;

    /** @return list<array{value: string, label: string}> */
    public function handle(User $user, FormSubmission $submission): array
    {
        $choices = [];

        foreach (PrintableDocument::all() as $document) {
            if ($this->printable($document, $user, $submission)) {
                $choices[] = ['value' => $document, 'label' => PrintableDocument::label($document)];
            }
        }

        return $choices;
    }

    private function printable(string $document, User $user, FormSubmission $submission): bool
    {
        try {
            PrintableDocument::assertPrintable($document, $user, $submission);
        } catch (AuthorizationException) {
            return false;
        }

        return true;
    }
}
