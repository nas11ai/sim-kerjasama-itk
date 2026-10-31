<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\FormSubmission\DeleteTargetOutput;
use App\Actions\FormSubmission\SaveTargetOutput;
use App\Data\View\Submission\TargetOutputChangeData;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\User;
use App\Support\Audit\AuditScope;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-15, BR-RPT-05 — jejak perubahan target luaran satu pengajuan, terbaru dulu. Dibaca dari
 * jejak pengajuan, bukan dari target yang masih ada, supaya penghapusan tetap terlihat.
 *
 * @method static list<TargetOutputChangeData> run(User $viewer, FormSubmission $submission)
 */
class TargetOutputHistory implements ChangesNoState
{
    use AsAction;

    private const DESCRIPTIONS = [
        SaveTargetOutput::AUDIT_CREATED,
        SaveTargetOutput::AUDIT_UPDATED,
        DeleteTargetOutput::AUDIT_DESCRIPTION,
    ];

    /** @return list<TargetOutputChangeData> */
    public function handle(User $viewer, FormSubmission $submission): array
    {
        $entries = AuditScope::forUser($viewer)
            ->where('subject_type', FormSubmission::class)
            ->where('subject_id', $submission->id)
            ->whereIn('description', self::DESCRIPTIONS)
            ->with('causer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $names = [];

        foreach (OutputTypeDefinition::query()->get(['code', 'name']) as $type) {
            $names[$type->code] = $type->name;
        }

        $nameOf = function (AuditEntry $entry, string $key) use ($names): ?string {
            $code = $entry->getExtraProperty("{$key}.output_type");

            return is_string($code) ? ($names[$code] ?? $code) : null;
        };

        return array_values($entries->map(fn (AuditEntry $entry): TargetOutputChangeData => new TargetOutputChangeData(
            description: $entry->description,
            actor: $entry->causer instanceof User ? $entry->causer->name : null,
            at: $entry->created_at?->toIso8601String(),
            from: $nameOf($entry, 'old'),
            to: $nameOf($entry, 'new'),
        ))->all());
    }
}
