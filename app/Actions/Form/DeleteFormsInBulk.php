<?php

namespace App\Actions\Form;

use App\Models\Form;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-19 — hapus massal semua-atau-tidak: satu Form yang pernah dipakai menolak seluruh
 * pilihan sebelum apa pun disentuh. Dulu Form sebelum yang ditolak terlanjur terhapus.
 *
 * @method static int run(list<int> $ids, User $actor)
 */
class DeleteFormsInBulk
{
    use AsAction;

    /**
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    public function handle(array $ids, User $actor): int
    {
        return DB::transaction(function () use ($ids, $actor): int {
            $forms = Form::query()->whereKey($ids)->lockForUpdate()->withCount('formSubmissions')->get();
            $used = $forms->filter(fn (Form $form): bool => $form->form_submissions_count > 0);

            if ($used->isNotEmpty()) {
                $names = $used->map(fn (Form $form): string => "{$form->title} ({$form->form_submissions_count} pengajuan)");

                throw ValidationException::withMessages([
                    'ids' => 'Tidak ada yang dihapus: formulir berikut masih dirujuk pengajuan — '
                        .$names->implode(', ').'. Nonaktifkan saja agar tidak dipakai pengajuan baru.',
                ]);
            }

            foreach ($forms as $form) {
                AuditTrail::record('Formulir dihapus', $form, $actor, new AuditChange(old: $form->auditSnapshot()));
                // Lewat model: penjaga BR-FE-19 di `Form::booted()` tetap berjalan.
                $form->delete();
            }

            return $forms->count();
        });
    }
}
