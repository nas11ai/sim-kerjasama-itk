<?php

namespace App\Actions\Scheme;

use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-04, BR-SCH-10 — memindahkan pilihan `scheme_selector` ke kolom
 * `scheme_id` (ADR 0005).
 *
 * @method static ?Scheme run(FormSubmission $submission)
 */
class SyncSchemeSelection
{
    use AsAction;

    public function handle(FormSubmission $submission): ?Scheme
    {
        $schemeId = $this->selectedSchemeId($submission);

        if ($schemeId === null) {
            return null;
        }

        $scheme = $this->resolveScheme($schemeId, $submission);

        if ($submission->scheme_id !== $scheme->id) {
            $sebelumnya = $submission->scheme_id;

            // Disimpan di kolom, bukan FormFieldResponse: anggaran (BR-BUD-01),
            // anggota (BR-SM-06), luaran (BR-SM-14) menanyakan skema lewat
            // kolom; dari jawaban form, penanya harus tahu fieldnya.
            $submission->update(['scheme_id' => $scheme->id]);

            // BR-RPT-09 — dicatat hanya saat berpindah: entri untuk pemanggilan
            // yang tak mengubah apa pun memenuhi jejak audit dengan baris tanpa
            // cerita dan menenggelamkan yang benar-benar terjadi.
            AuditTrail::record(
                'Skema pengajuan dipilih',
                $submission,
                null,
                new AuditChange(
                    old: ['scheme_id' => $sebelumnya],
                    new: ['scheme_id' => $scheme->id],
                ),
            );
        }

        return $scheme;
    }

    private function selectedSchemeId(FormSubmission $submission): ?int
    {
        $field = FormField::query()
            ->where('form_id', $submission->form_id)
            ->whereHas('fieldType', fn ($q) => $q->where('name', 'scheme_selector'))
            ->first();

        // BR-SCH-04 — skema **sepenuhnya opsional per Form**: tanpa
        // scheme_selector, `scheme_id` pengajuan kosong. Itu bentuk yang sah,
        // bukan kesalahan.
        if ($field === null) {
            return null;
        }

        $response = FormFieldResponse::query()
            ->where('form_submission_id', $submission->id)
            ->where('form_field_id', $field->id)
            ->first();

        if ($response === null || $response->value === null || $response->value === '') {
            return null;
        }

        return (int) $response->value;
    }

    /**
     * @throws ValidationException
     */
    private function resolveScheme(int $schemeId, FormSubmission $submission): Scheme
    {
        $scheme = Scheme::find($schemeId);

        if ($scheme === null) {
            throw ValidationException::withMessages([
                'scheme_id' => 'Skema yang dipilih tidak ditemukan.',
            ]);
        }

        // BR-SCH-10 — skema nonaktif tidak boleh dipakai pengajuan baru.
        // Pengajuan yang sudah terlanjur memakainya tidak diusik: yang ditolak
        // adalah *memilihnya sekarang*, bukan yang sudah berjalan.
        if (!$scheme->is_active && $submission->scheme_id !== $scheme->id) {
            throw ValidationException::withMessages([
                'scheme_id' => 'Skema "'.$scheme->name.'" sudah tidak aktif dan tidak dapat dipilih.',
            ]);
        }

        return $scheme;
    }
}
