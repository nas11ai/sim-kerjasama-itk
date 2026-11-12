<?php

namespace App\Actions\Review;

use App\Data\Request\EvaluationDraftInput;
use App\Models\EvaluationFormDraft;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-31 — Terbitkan: isi draf menjadi versi bernomor berikutnya, lalu drafnya dihapus, satu
 * transaksi. Isi versinya diaudit PublishEvaluationFormVersion; di sini hanya draf yang terpakai.
 *
 * @method static ReviewEvaluationFormVersion run(ReviewEvaluationForm $form, User $actor)
 */
class PublishEvaluationFormDraft
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(ReviewEvaluationForm $form, User $actor): ReviewEvaluationFormVersion
    {
        return DB::transaction(function () use ($form, $actor): ReviewEvaluationFormVersion {
            // Kunci draf: dua Terbitkan bersamaan tidak boleh menerbitkan draf yang sama dua kali.
            $draft = EvaluationFormDraft::query()
                ->where(['draftable_id' => $form->id])
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                throw ValidationException::withMessages(['draft' => 'Tidak ada draf untuk diterbitkan.']);
            }

            $version = PublishEvaluationFormVersion::run($form, EvaluationDraftInput::fromPayload($draft->payload)->fields, $actor);
            $draft->delete();
            $form->unsetRelation('draft');
            AuditTrail::record('Draf form evaluasi diterbitkan', $form, $actor, new AuditChange(new: ['number' => $version->number]));

            return $version;
        });
    }
}
