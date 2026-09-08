<?php

namespace App\Actions\Scheme;

use App\Models\FormField;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-04, BR-SCH-10 — memindahkan pilihan `scheme_selector` ke kolom
 * `scheme_id` (ADR 0005).
 *
 * Skema **sepenuhnya opsional per Form**. Form tanpa field `scheme_selector`
 * berjalan tanpa skema, dan pengajuannya berakhir dengan `scheme_id` kosong —
 * bukan kesalahan, melainkan bentuk yang sah.
 *
 * Alasan pilihan itu tidak dibiarkan hidup sebagai FormFieldResponse belaka:
 * batas anggaran (BR-BUD-01), batas anggota (BR-SM-06), dan tipe luaran yang
 * diizinkan (BR-SM-14) semuanya menanyakan skema lewat kolom. Membacanya dari
 * jawaban form berarti setiap penanya harus tahu field mana yang menyimpannya.
 *
 * @method static ?Scheme run(FormSubmission $submission)
 */
class SyncSchemeSelection
{
    use AsAction;

    public function handle(FormSubmission $submission): ?Scheme
    {
        $field = FormField::query()
            ->where('form_id', $submission->form_id)
            ->whereHas('fieldType', fn ($q) => $q->where('name', 'scheme_selector'))
            ->first();

        // BR-SCH-04 — Form tanpa scheme_selector memang tidak berskema.
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

        $scheme = Scheme::find((int) $response->value);

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

        if ($submission->scheme_id !== $scheme->id) {
            $sebelumnya = $submission->scheme_id;
            $submission->update(['scheme_id' => $scheme->id]);

            // BR-RPT-09 — dicatat hanya saat memang berpindah. Menulis entri
            // untuk pemanggilan yang tidak mengubah apa pun akan memenuhi
            // jejak audit dengan baris yang tidak menceritakan kejadian apa
            // pun, dan itu menenggelamkan yang benar-benar terjadi.
            AuditTrail::record(
                'Skema pengajuan dipilih',
                $submission,
                null,
                ['scheme_id' => $sebelumnya],
                ['scheme_id' => $scheme->id],
            );
        }

        return $scheme;
    }
}
