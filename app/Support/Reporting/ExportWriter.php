<?php

namespace App\Support\Reporting;

use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Menulis berkas hasil ekspor ke disk dan mengembalikan path-nya. Dipisahkan
 * dari Action-nya supaya jalur sinkron dan background job menghasilkan berkas
 * sama persis — dua penulis akan beda pada kolom pertama yang ditambahkan.
 */
final class ExportWriter
{
    public const DISK = 'local';

    public const DIRECTORY = 'exports';

    public static function write(ExportJob $job, User $user): string
    {
        $rows = ExportType::query($job->type, $user)->get();

        $path = self::DIRECTORY.'/'.$job->type.'-'.$job->id.'-'.now()->format('Ymd-His').'.csv';

        Storage::disk(self::DISK)->put($path, self::toCsv($rows->all()));

        return $path;
    }

    /**
     * @param  array<int, Model>  $rows
     */
    private static function toCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $columns = array_keys($rows[0]->attributesToArray());
        $lines = [self::line($columns)];

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $value = $row->getAttribute($column);
                $values[] = is_scalar($value) ? (string) $value : json_encode($value);
            }

            $lines[] = self::line($values);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, string|false|null>  $values
     */
    private static function line(array $values): string
    {
        return implode(',', array_map(
            static fn (string|false|null $v): string => '"'.str_replace('"', '""', (string) $v).'"',
            $values,
        ));
    }
}
