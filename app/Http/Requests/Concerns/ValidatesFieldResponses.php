<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Isian field Form Engine `responses.*` beserta berkasnya, dipakai wizard pengajuan dan laporan
 * monev. Berkas dikirim di tempat jawabannya, `responses.*.value`.
 */
trait ValidatesFieldResponses
{
    use ReadsFieldAnswers;

    private int $maxUploadBytes = 10 * 1024 * 1024;

    /** @return array<string, string> */
    private function responseRules(): array
    {
        return [
            'responses' => 'required|array',
            'responses.*.form_field_id' => 'required|exists:form_fields,id',
            'responses.*.value' => 'nullable',
            // BR-FE-14 — simpan dan submit membawa cap waktu yang dilihat penyunting. `nullable`, bukan
            // `required`: wajibnya ditegakkan OptimisticLock **setelah** baris diresolusi, supaya "belum
            // ada baris" tetap terbaca 409 dan tidak tertukar jadi 422 ([SM-STATE-04]).
            'last_updated_at' => 'nullable|date',
        ];
    }

    /**
     * Batas berkas tak bisa ditulis `max:` karena field yang sama memuat teks, dan bagi teks `max`
     * berarti jumlah karakter. Dulu hanya submit yang membatasinya.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->uploadedFiles() as $index => $file) {
                if ($file->getSize() > $this->maxUploadBytes) {
                    $validator->errors()->add("responses.{$index}.value", 'Ukuran file terlalu besar. Maksimal 10MB.');
                }
            }
        }];
    }

    /** @return array<int, UploadedFile> id field => berkas */
    private function uploadsByField(): array
    {
        $uploads = [];

        foreach ($this->uploadedFiles() as $index => $file) {
            $uploads[$this->integer("responses.{$index}.form_field_id")] = $file;
        }

        return $uploads;
    }

    private function lastUpdatedAt(): ?string
    {
        return $this->filled('last_updated_at') ? $this->string('last_updated_at')->toString() : null;
    }

    /** @return array<array-key, UploadedFile> indeks jawaban => berkas */
    private function uploadedFiles(): array
    {
        $files = [];

        foreach (array_keys($this->array('responses')) as $index) {
            $file = $this->file("responses.{$index}.value");

            if ($file instanceof UploadedFile && $file->isValid()) {
                $files[$index] = $file;
            }
        }

        return $files;
    }
}
