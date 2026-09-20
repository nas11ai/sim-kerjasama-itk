<?php

namespace App\Actions\ResearchOutput;

use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\ResearchOutputFile;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use App\Support\ResearchOutputGuard;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RO-01..05, BR-RO-09 — menambah atau menyunting luaran terealisasi.
 *
 * Kecocokan dengan target yang dijanjikan sengaja **tidak** diperiksa (register #19): penelitian memang
 * menyimpang, dan memaksanya cocok mendorong peneliti berbohong di target atau menyembunyikan luaran.
 *
 * @method static ResearchOutput run(FormSubmission $submission, User $actor, array{output_type: string, metadata?: array<string, mixed>, files?: list<array{file_path: string, file_url?: string|null}>} $data, ?ResearchOutput $output = null)
 */
class SaveResearchOutput
{
    use AsAction;

    /**
     * Seluruh syaratnya ada di `ResearchOutputGuard`, dipanggil sekali di sini: keadaan induk,
     * taksonomi, kesesuaian jenis pengajuan, kelengkapan metadata, dan keberadaan berkas. Menyebarnya
     * ke controller membuat jalur lain — impor, seeder, koreksi Operator — menegakkan separuhnya saja.
     *
     * @param  array{output_type: string, metadata?: array<string, mixed>, files?: list<array{file_path: string, file_url?: string|null}>}  $data
     */
    public function handle(
        FormSubmission $submission,
        User $actor,
        array $data,
        ?ResearchOutput $output = null
    ): ResearchOutput {
        $metadata = $data['metadata'] ?? [];
        $incomingFiles = $data['files'] ?? [];

        // Berkas yang sudah tersimpan ikut dihitung: menyunting judul luaran
        // HKI tidak boleh menuntut mengunggah ulang lampirannya.
        $fileCount = count($incomingFiles) + ($output?->files()->count() ?? 0);

        ResearchOutputGuard::assertValid($submission, $data['output_type'], $metadata, $fileCount);

        $tercatat = DB::transaction(function () use ($submission, $actor, $data, $metadata, $incomingFiles, $output): ResearchOutput {
            $attributes = [
                'form_submission_id' => $submission->id,
                'output_type' => $data['output_type'],
                'metadata' => $metadata,
            ];

            if ($output === null) {
                $output = ResearchOutput::create($attributes + ['created_by' => $actor->id]);
            } else {
                $output->update($attributes);
            }

            foreach ($incomingFiles as $file) {
                ResearchOutputFile::create([
                    'research_output_id' => $output->id,
                    'file_path' => $file['file_path'],
                    'file_url' => $file['file_url'] ?? null,
                ]);
            }

            return $output->refresh();
        });

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Luaran penelitian disimpan', $tercatat, $actor);

        return $tercatat;
    }
}
