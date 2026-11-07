<?php

namespace App\Data\View\ResearchOutput;

use App\Models\ResearchOutput;
use App\Models\ResearchOutputFile;
use Spatie\LaravelData\Data;

/** W141 — berkas bukti sebagaimana ditampilkan: nama asli, ukuran, dan route unduh ber-scope. */
final class OutputFileData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $size,
        public string $url,
    ) {}

    /** @return list<self> */
    public static function many(ResearchOutput $output): array
    {
        return array_values($output->files()->orderBy('id')->get()
            ->map(fn (ResearchOutputFile $file): self => new self(
                id: $file->id,
                // Baris sebelum W141 tak punya nama asli: nama berkas di disk yang tampil.
                name: $file->original_name ?? basename($file->file_path),
                size: $file->size,
                url: route('research-outputs.files.show', ['output' => $output->id, 'file' => $file->id]),
            ))
            ->all());
    }
}
