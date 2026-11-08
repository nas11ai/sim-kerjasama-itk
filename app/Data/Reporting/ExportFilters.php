<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * W154 — filter ekspor tabular, disimpan di `export_jobs.filters` dengan kunci snake_case.
 * Tahun = tahun submit pertama (jenis berbasis pengajuan) atau waktu entri (Audit Log).
 */
final class ExportFilters extends Data
{
    public const YEAR = 'year';

    public const SCHEME = 'scheme';

    public const STATUS = 'status';

    public function __construct(
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?int $scheme = null,
        public ?string $status = null,
    ) {}

    /** @param  array<string, int|string>|null  $stored  isi `export_jobs.filters`; kunci lain (mis. cetak) diabaikan */
    public static function fromStored(?array $stored): self
    {
        $status = $stored['status'] ?? null;

        return new self(
            self::integer($stored['year_from'] ?? null),
            self::integer($stored['year_to'] ?? null),
            self::integer($stored['scheme'] ?? null),
            is_string($status) ? $status : null,
        );
    }

    /** @return array<string, int|string> kunci bernilai null dibuang */
    public function toStored(): array
    {
        return array_filter(
            ['year_from' => $this->yearFrom, 'year_to' => $this->yearTo, 'scheme' => $this->scheme, 'status' => $this->status],
            fn (int|string|null $value): bool => $value !== null,
        );
    }

    /** @param  list<string>  $keys  `filterKeys()` jenis ekspornya */
    public function restrictedTo(array $keys): self
    {
        $year = in_array(self::YEAR, $keys, true);

        return new self(
            $year ? $this->yearFrom : null,
            $year ? $this->yearTo : null,
            in_array(self::SCHEME, $keys, true) ? $this->scheme : null,
            in_array(self::STATUS, $keys, true) ? $this->status : null,
        );
    }

    private static function integer(int|string|null $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
