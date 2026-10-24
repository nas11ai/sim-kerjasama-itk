<?php

namespace App\Queries\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Arah urutan, ukuran halaman, dan filter aktif dari query string layar daftar Admin.
 * Nilai tak dikenal jatuh ke bawaan, bukan 500, supaya tautan lama tetap terbuka.
 */
trait NormalisesListFilters
{
    /** @return 'asc'|'desc' */
    private function sortOrder(string $order): string
    {
        return $order === 'asc' ? 'asc' : 'desc';
    }

    private function perPage(int $perPage): int
    {
        return $perPage < 1 ? 10 : min($perPage, 100);
    }

    /**
     * Kolom is_active bertipe boolean di PostgreSQL; bandingkan dengan boolean.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function whereActiveState(Builder $query, ?string $state): void
    {
        if ($state === 'active' || $state === 'inactive') {
            $query->where('is_active', $state === 'active');
        }
    }
}
