<?php

namespace App\Models;

use App\Support\Reporting\ExportRetention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu permintaan ekspor beserta nasib berkasnya.
 *
 * @property int $id
 * @property string $type
 * @property string $status
 * @property int $row_count
 * @property int $attempts
 * @property string|null $file_path
 * @property string|null $failure_reason
 * @property Carbon|null $completed_at
 * @property Carbon|null $file_deleted_at
 * @property-read User|null $requestedBy
 */
class ExportJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'requested_by',
        'type',
        'filters',
        'status',
        'row_count',
        'attempts',
        'file_path',
        'failure_reason',
        'completed_at',
        'file_deleted_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'row_count' => 'integer',
        'attempts' => 'integer',
        'completed_at' => 'datetime',
        'file_deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** BR-RPT-12 — berkasnya sudah dihapus penjadwal retensi. */
    public function fileIsGone(): bool
    {
        return $this->file_deleted_at !== null || $this->file_path === null;
    }

    /**
     * Sudah lewat masa simpan tapi belum sempat dihapus penjadwal.
     *
     * Dibedakan dari `fileIsGone()` supaya penolakannya tetap benar di
     * jendela antara jatuh tempo dan jalannya scheduler — pengguna yang
     * mengunduh pada menit itu tetap harus ditolak.
     */
    public function fileIsExpired(): bool
    {
        return $this->completed_at !== null
            && $this->completed_at->addHours(ExportRetention::HOURS)->isPast();
    }

    /**
     * @param  Builder<ExportJob>  $query
     * @return Builder<ExportJob>
     */
    public function scopeDueForCleanup(Builder $query): Builder
    {
        return $query
            ->whereNotNull('file_path')
            ->whereNull('file_deleted_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subHours(ExportRetention::HOURS));
    }
}
