<?php

namespace App\Actions\Identity;

use App\Models\Reviewer;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-09 — mengubah masa tugas reviewer lewat satu pintu beraudit. Nonaktifkan memakai
 * end_date kemarin (inklusif per hari); aktifkan mengosongkannya, hal yang tak bisa
 * dilakukan AppointReviewer karena ia mengabaikan nilai null saat memperbarui.
 *
 * @method static Reviewer run(Reviewer $reviewer, string $start, ?string $end)
 */
class ChangeReviewerTenure
{
    use AsAction;

    public function handle(Reviewer $reviewer, string $start, ?string $end): Reviewer
    {
        $before = $this->tenureOf($reviewer);

        $reviewer->update(['start_date' => $start, 'end_date' => $end]);
        $reviewer->refresh();

        // BR-RPT-09 — nonaktifkan/aktifkan sebelumnya tanpa jejak, padahal itu yang
        // menentukan siapa boleh ditugaskan (BR-REV-09).
        AuditTrail::record(
            'Masa tugas reviewer diubah',
            $reviewer,
            change: new AuditChange($before, $this->tenureOf($reviewer)),
        );

        return $reviewer;
    }

    /** @return array{start_date: string|null, end_date: string|null} */
    private function tenureOf(Reviewer $reviewer): array
    {
        return [
            'start_date' => $reviewer->start_date?->toDateString(),
            'end_date' => $reviewer->end_date?->toDateString(),
        ];
    }
}
