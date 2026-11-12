<?php

namespace App\Actions\Form;

use App\Data\Request\FormDraftInput;
use App\Models\Form;
use App\Models\FormDraft;
use App\Models\User;
use App\Services\Form\FormFieldWriter;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SchemeFieldGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-34 — Terbitkan: field draf ditulis ke baris hidup saat ini, jadi `created_at`,
 * `required_since`, dan soft delete (BR-FE-11/12/18) bertanggal terbit. Penolakan menggulung
 * transaksi, jadi draf tetap. Pemberitahuan BR-FE-13 dipanggil sesudahnya (NotifyFormChange).
 *
 * @method static void run(Form $form, User $actor)
 */
class PublishFormDraft
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Form $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            // Kunci draf: dua Terbitkan bersamaan tidak boleh menulis draf yang sama dua kali.
            $draft = FormDraft::query()->where('draftable_id', $form->id)->lockForUpdate()->first();

            if ($draft === null) {
                throw ValidationException::withMessages(['draft' => 'Tidak ada draf untuk diterbitkan.']);
            }

            $input = FormDraftInput::fromPayload($draft->payload);
            SchemeFieldGuard::assertTrlHasSchemeSelector($input->fieldTypeIds());

            $old = $form->auditSnapshot();
            // BR-FE-23/24 — penjaga tipe terkunci dan id asing berjalan di sini, saat field hidup ditulis.
            FormFieldWriter::sync($form, $input->fields);
            $form->published_at ??= now();
            $form->save();
            $draft->delete();
            $form->unsetRelation('draft');

            AuditTrail::record('Formulir diterbitkan', $form, $actor, new AuditChange($old, $form->auditSnapshot()));
        });
    }
}
