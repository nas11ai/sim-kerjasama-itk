<?php

namespace App\Actions\Scheme;

use App\Models\FormField;
use App\Models\FormSubmission;
use App\Queries\Submission\ColumnSelectorAnswer;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-05, BR-SCH-09 — memindahkan pilihan `trl_selector` ke kolom `trl_id`, setelah skema
 * disinkron: izin TRL dibaca dari skema yang baru terpilih, bukan yang lama.
 *
 * @method static void run(FormSubmission $submission)
 */
class SyncTrlSelection
{
    use AsAction;

    /** @throws ValidationException */
    public function handle(FormSubmission $submission): void
    {
        $answer = ColumnSelectorAnswer::run($submission, 'trl_selector');

        // Form tanpa trl_selector tidak mengenal TRL; kolomnya dibiarkan apa adanya.
        if ($answer === null) {
            return;
        }

        $trlId = $answer['value'] === null ? null : $this->allowedTrlId($submission, $answer['field'], $answer['value']);

        if ($submission->trl_id === $trlId) {
            return;
        }

        $sebelumnya = $submission->trl_id;
        $submission->update(['trl_id' => $trlId]);

        // BR-RPT-09 — dicatat hanya saat berpindah, sama dengan SyncSchemeSelection.
        AuditTrail::record('TKT pengajuan dipilih', $submission, null, new AuditChange(
            old: ['trl_id' => $sebelumnya],
            new: ['trl_id' => $trlId],
        ));
    }

    /**
     * Tanpa skema tidak ada TRL yang diizinkan. Ditolak, bukan dikosongkan diam-diam: TRL lama
     * yang bertahan setelah skema berganti adalah pilihan yang tak pernah disetujui skemanya.
     *
     * @throws ValidationException
     */
    private function allowedTrlId(FormSubmission $submission, FormField $field, string $value): int
    {
        $trlId = filter_var($value, FILTER_VALIDATE_INT);
        $allowed = is_int($trlId)
            && $submission->scheme()->first()?->allowedTrls()->whereKey($trlId)->exists() === true;

        if (!$allowed) {
            throw ValidationException::withMessages([
                'field_'.$field->id => 'TKT ini tidak diizinkan skema terpilih.',
            ]);
        }

        return $trlId;
    }
}
