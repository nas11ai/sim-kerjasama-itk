<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use Illuminate\Validation\ValidationException;

/**
 * BR-RO-01, BR-RO-02, BR-RO-04, BR-RO-05, BR-RO-09 — syarat sebuah luaran sah.
 *
 * Empat pemeriksaan yang menjawab pertanyaan berbeda, dan urutannya dipilih
 * supaya pesan yang muncul adalah yang paling menjelaskan:
 *
 *   1. **Keadaan induk** — luaran adalah laporan atas kegiatan yang sudah
 *      berjalan; sebelum disetujui, belum ada yang bisa dilaporkan.
 *   2. **Taksonomi** — tipe datang dari `output_type_definitions`, bukan teks
 *      bebas. Menambah tipe adalah perubahan data, bukan perubahan kode.
 *   3. **Kesesuaian jenis pengajuan** — PKS bentuk luaran pengabdian, bukan
 *      penelitian.
 *   4. **Isi** — taksonomi yang dibatasi baru menjaga separuh: tipenya
 *      terkendali, isinya tidak. Luaran berisi objek kosong lolos ke pelaporan
 *      sebagai kegiatan yang seolah terlaporkan.
 */
final class ResearchOutputGuard
{
    /**
     * @param  array<string, mixed>  $metadata
     *
     * @throws ValidationException
     */
    public static function assertValid(
        FormSubmission $submission,
        string $outputType,
        array $metadata,
        int $fileCount
    ): OutputTypeDefinition {
        self::assertSubmissionEligible($submission);

        $definition = OutputTypeDefinition::query()
            ->where('code', $outputType)
            ->where('is_active', true)
            ->first();

        // BR-RO-05
        if ($definition === null) {
            throw ValidationException::withMessages([
                'output_type' => 'Tipe luaran "'.$outputType.'" belum terdaftar. '
                    .'Minta Admin mendaftarkannya lebih dulu.',
            ]);
        }

        self::assertMatchesSubmissionType($submission, $definition);
        self::assertMetadataComplete($definition, $metadata);
        self::assertFilesPresent($definition, $fileCount);

        return $definition;
    }

    /**
     * BR-RO-01 — hanya pengajuan APPROVED. Yang sudah ditarik dipisahkan
     * pesannya: luarannya tetap terbaca (BR-RO-06), yang berhenti hanya
     * penambahan baru.
     */
    public static function assertSubmissionEligible(FormSubmission $submission): void
    {
        if ($submission->status instanceof Withdrawn) {
            throw ValidationException::withMessages([
                'form_submission_id' => 'Pengajuan ini sudah ditarik. Luaran yang sudah ada tetap '
                    .'terbaca, tapi tidak ada yang bisa ditambahkan lagi.',
            ]);
        }

        if (!$submission->status instanceof Approved) {
            throw ValidationException::withMessages([
                'form_submission_id' => 'Luaran baru dapat ditambahkan setelah pengajuan disetujui.',
            ]);
        }
    }

    /** BR-RO-02 — mis. `pks` hanya untuk CommunityService. */
    private static function assertMatchesSubmissionType(
        FormSubmission $submission,
        OutputTypeDefinition $definition
    ): void {
        $restrictedTo = $definition->submission_type;

        if ($restrictedTo === null) {
            return;
        }

        $actual = $submission->submissionType()?->name;

        if ($actual === $restrictedTo) {
            return;
        }

        throw ValidationException::withMessages([
            'output_type' => 'Tipe "'.$definition->name.'" hanya tersedia untuk pengajuan '
                .$restrictedTo.'.',
        ]);
    }

    /**
     * BR-RO-09 — field yang ditandai `required` di definisi tipenya.
     *
     * Yang tidak ditandai boleh kosong, termasuk `end_date` untuk PKS yang
     * masih berjalan (BR-RO-03) — memaksanya terisi membuat peneliti mengarang
     * tanggal.
     *
     * @param  array<string, mixed>  $metadata
     */
    private static function assertMetadataComplete(
        OutputTypeDefinition $definition,
        array $metadata
    ): void {
        foreach ($definition->requiredMetadataKeys() as $key) {
            if (!blank($metadata[$key] ?? null)) {
                continue;
            }

            throw ValidationException::withMessages([
                'metadata.'.$key => 'Isian "'.$key.'" wajib diisi untuk tipe '.$definition->name.'.',
            ]);
        }
    }

    /** BR-RO-04 — `ip` dan `prototype`, dua tipe, bukan satu. */
    private static function assertFilesPresent(OutputTypeDefinition $definition, int $fileCount): void
    {
        if (!$definition->requires_files || $fileCount > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'files' => 'Luaran bertipe '.$definition->name.' wajib punya minimal satu berkas bukti.',
        ]);
    }
}
