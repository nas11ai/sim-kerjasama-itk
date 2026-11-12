<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseDraftInput;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseVersion;
use App\Models\PhaseDraft;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-33 — Terbitkan: isi draf divalidasi ulang dan menjadi versi bernomor berikutnya
 * (BR-FE-32), lalu drafnya dihapus, satu transaksi. Penolakan menggulung transaksi, jadi draf tetap.
 *
 * @method static FormPhaseVersion run(FormPhase $phase, User $actor)
 */
class PublishPhaseDraft
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhase $phase, User $actor): FormPhaseVersion
    {
        return DB::transaction(function () use ($phase, $actor): FormPhaseVersion {
            // Kunci tahap lalu draf, urutan yang sama dengan simpan/buang draf (B13); dua Terbitkan
            // bersamaan tidak menerbitkan draf yang sama dua kali.
            FormPhase::query()->whereKey($phase->id)->lockForUpdate()->firstOrFail();
            $draft = PhaseDraft::query()->where('draftable_id', $phase->id)->lockForUpdate()->first();

            if ($draft === null) {
                throw ValidationException::withMessages(['draft' => 'Tidak ada draf untuk diterbitkan.']);
            }

            $input = PhaseDraftInput::fromPayload($draft->payload);
            $this->assertAccessRulesExist($input->steps);

            $version = PublishPhaseVersion::run($phase, $input->toStructure(), $actor);
            $draft->delete();
            $phase->unsetRelation('draft');
            AuditTrail::record('Draf tahap diterbitkan', $phase, $actor, new AuditChange(new: ['version' => $version->number]));

            return $version;
        });
    }

    /**
     * Draf tidak ber-FK: aturan akses bisa dicabut sesudah draf disimpan (AssertFormAccessUnused
     * hanya melihat langkah terbit). Tanpa penjaga ini galat foreign key yang sampai ke Admin.
     *
     * @param  list<FormPhaseDetailInput>  $steps
     *
     * @throws ValidationException
     */
    private function assertAccessRulesExist(array $steps): void
    {
        $existing = FormAccessControl::query()
            ->whereIn('id', array_map(fn (FormPhaseDetailInput $step): int => $step->formAccessControlId, $steps))
            ->pluck('id')
            ->all();
        $errors = [];

        foreach ($steps as $index => $step) {
            if (!in_array($step->formAccessControlId, $existing, true)) {
                $errors["phase_details.{$index}.form_access_control_id"] = 'Aturan akses langkah ini sudah dihapus. Pilih aturan akses lain lalu simpan draf.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
