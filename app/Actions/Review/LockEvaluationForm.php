<?php

namespace App\Actions\Review;

use App\Models\ReviewFormResponse;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mengunci satu jawaban evaluasi, biasanya karena tenggatnya lewat.
 * Mengunci yang sudah terkunci bukan galat — ia cuma tidak melakukan apa-apa.
 *
 * @method static bool run(ReviewFormResponse $response)
 */
class LockEvaluationForm
{
    use AsAction;

    public function handle(ReviewFormResponse $response): bool
    {
        if ($response->isLocked()) {
            return false;
        }

        $response->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        AuditTrail::record('Evaluasi dikunci', $response);

        return true;
    }
}
