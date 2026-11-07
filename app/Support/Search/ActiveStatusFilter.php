<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar admin atas kolom `is_active` dan id relasi dari query string. Nilai
 * yang tak dikenal berarti tanpa filter, bukan galat.
 */
final class ActiveStatusFilter
{
    /** @var list<array{value: string, label: string}> */
    public const OPTIONS = [
        ['value' => 'active', 'label' => 'Aktif'],
        ['value' => 'inactive', 'label' => 'Nonaktif'],
    ];

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function apply(Builder $query, string $status): void
    {
        match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }

    /** Selain angka (termasuk 'all') atau lebih dari 9 digit (di luar rentang int) berarti tanpa filter. */
    public static function id(string $value): ?int
    {
        return ctype_digit($value) && strlen($value) <= 9 ? intval($value) : null;
    }
}
