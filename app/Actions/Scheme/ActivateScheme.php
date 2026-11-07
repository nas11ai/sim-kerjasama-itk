<?php

namespace App\Actions\Scheme;

use App\Models\Scheme;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-10 — skema nonaktif ditawarkan kembali pada pengajuan baru (W109).
 *
 * @method static Scheme run(Scheme $scheme)
 */
class ActivateScheme
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(Scheme $scheme): Scheme
    {
        if ($scheme->is_active) {
            throw ValidationException::withMessages(['scheme_id' => 'Skema ini sudah aktif.']);
        }

        return DB::transaction(function () use ($scheme): Scheme {
            $scheme->update(['is_active' => true]);

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record(
                'Skema diaktifkan',
                $scheme,
                change: new AuditChange(old: ['is_active' => false], new: ['is_active' => true]),
            );

            return $scheme;
        });
    }
}
