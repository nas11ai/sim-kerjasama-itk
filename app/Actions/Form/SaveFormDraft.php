<?php

namespace App\Actions\Form;

use App\Data\Request\FormDraftInput;
use App\Models\Form;
use App\Models\FormDraft;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SchemeFieldGuard;
use App\Support\SubmissionTitleField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-34 — menyimpan field Form sebagai draf (satu per Form, ditimpa tiap simpan). Draf tidak
 * terlihat pengisi; Terbitkan yang menulisnya ke field hidup dan menjalankan penjaga BR-FE-23/24.
 *
 * @method static FormDraft run(Form $form, FormDraftInput $input, User $actor)
 */
class SaveFormDraft
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Form $form, FormDraftInput $input, User $actor): FormDraft
    {
        // BR-SCH-05 — galatnya sudah terlihat saat simpan; Terbitkan memeriksanya lagi.
        SchemeFieldGuard::assertTrlHasSchemeSelector($input->fieldTypeIds());
        // BR-SM-22 — paling banyak satu field judul pengajuan per Form.
        SubmissionTitleField::assertAtMostOne($input->fieldTypeIds());

        return DB::transaction(function () use ($form, $input, $actor): FormDraft {
            // B13 — Terbitkan memegang kunci ini sambil menghapus draf; tanpanya simpan yang
            // berpapasan memperbarui 0 baris tapi tetap melapor "Draf disimpan."
            Form::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $draft = FormDraft::query()->updateOrCreate(
                ['draftable_id' => $form->id],
                ['payload' => $input->toPayload(), 'updated_by' => $actor->id],
            );
            $form->unsetRelation('draft');

            AuditTrail::record('Draf formulir disimpan', $form, $actor, new AuditChange(new: ['fields' => count($input->fields)]));

            return $draft;
        });
    }
}
