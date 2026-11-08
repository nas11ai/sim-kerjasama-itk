<?php

namespace App\Support\Reporting;

use App\Data\Reporting\ExportFilters;
use App\Enums\ExportFormat;
use App\Models\ExportJob;
use App\Models\User;
use App\Support\Reporting\Exports\TabularSheet;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Menulis berkas hasil ekspor ke disk dan mengembalikan path-nya. Dipisahkan
 * dari Action-nya supaya jalur sinkron dan background job menghasilkan berkas
 * sama persis — dua penulis akan beda pada kolom pertama yang ditambahkan.
 */
final class ExportWriter
{
    public const DISK = 'local';

    public const DIRECTORY = 'exports';

    // W154 — kolom dari definisi jenisnya, format dari permintaan (xlsx bila tak dikenal).
    public static function write(ExportJob $job, User $user): string
    {
        $definition = ExportType::definition($job->type);
        $filters = ExportFilters::fromStored($job->filters);
        $format = ExportFormat::tryFrom($job->format) ?? ExportFormat::Xlsx;

        $path = self::DIRECTORY.'/'.$job->type.'-'.$job->id.'-'.now()->format('Ymd-His').'.'.$format->extension();
        $sheet = new TabularSheet($definition->headings(), $definition->rows($user, $filters));

        Excel::store($sheet, $path, self::DISK, $format->writerType());

        return $path;
    }
}
