<?php

namespace App\Actions\ResearchOutput;

use App\Data\Request\ResearchOutputInput;
use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\ResearchOutputFile;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\PrivateUploadDisk;
use App\Support\ResearchOutputGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * BR-RO-01..05, BR-RO-09 — menambah atau menyunting luaran terealisasi. Kecocokan dengan target
 * yang dijanjikan sengaja **tidak** diperiksa (register #19): penelitian memang menyimpang, dan
 * memaksanya cocok mendorong peneliti berbohong di target atau menyembunyikan luaran.
 *
 * @method static ResearchOutput run(FormSubmission $submission, User $actor, ResearchOutputInput $input, ?ResearchOutput $output = null)
 */
class SaveResearchOutput
{
    use AsAction;

    /** W141 — berkas bukti di disk privat, dikelompokkan per pengajuan. */
    private const DIRECTORY = 'research-outputs';

    /**
     * Seluruh syaratnya ada di `ResearchOutputGuard`: keadaan induk, taksonomi, kesesuaian
     * jenis pengajuan, kelengkapan metadata, dan keberadaan berkas. Menyebarnya ke controller
     * membuat jalur lain — impor, seeder, koreksi Operator — menegakkan separuhnya saja.
     */
    public function handle(
        FormSubmission $submission,
        User $actor,
        ResearchOutputInput $input,
        ?ResearchOutput $output = null
    ): ResearchOutput {
        // Berkas yang sudah tersimpan ikut dihitung: menyunting judul luaran
        // HKI tidak boleh menuntut mengunggah ulang lampirannya.
        $fileCount = count($input->files) + ($output?->files()->count() ?? 0);

        // Yang disimpan metadata bersih Guard, bukan kiriman klien (W140).
        $metadata = ResearchOutputGuard::assertValid($submission, $input->outputType, $input->metadata, $fileCount);

        $stored = [];

        try {
            $tercatat = DB::transaction(function () use ($submission, $actor, $input, $metadata, $output, &$stored): ResearchOutput {
                $saved = $this->persist($output, $actor, [
                    'form_submission_id' => $submission->id,
                    'output_type' => $input->outputType,
                    'metadata' => $metadata,
                ]);

                foreach ($input->files as $file) {
                    $stored[] = $this->attach($saved, $file);
                }

                return $saved->refresh();
            });
        } catch (Throwable $failure) {
            // Transaksi yang gagal tidak boleh meninggalkan berkas yatim di disk privat.
            Storage::disk(PrivateUploadDisk::NAME)->delete($stored);

            throw $failure;
        }

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Luaran penelitian disimpan', $tercatat, $actor, new AuditChange(new: [
            'files_added' => count($input->files),
        ]));

        return $tercatat;
    }

    /** @param  array{form_submission_id: int, output_type: string, metadata: array<string, string|int|null>}  $attributes */
    private function persist(?ResearchOutput $output, User $actor, array $attributes): ResearchOutput
    {
        if ($output === null) {
            return ResearchOutput::create($attributes + ['created_by' => $actor->id]);
        }

        $output->update($attributes);

        return $output;
    }

    /** @return string path berkas yang tersimpan */
    private function attach(ResearchOutput $output, UploadedFile $file): string
    {
        $path = $file->store(self::DIRECTORY.'/'.$output->form_submission_id, PrivateUploadDisk::NAME);

        if ($path === false) {
            throw new RuntimeException('Berkas bukti gagal disimpan.');
        }

        ResearchOutputFile::create([
            'research_output_id' => $output->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        return $path;
    }
}
