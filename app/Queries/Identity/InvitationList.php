<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Identity\InvitationRowData;
use App\Enums\InvitationPreset;
use App\Models\InvitationToken;
use App\Models\Organization;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ADMIN-08-01 — seluruh undangan, terbaru dulu, beserta pilihan form terbit.
 *
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{invitations: list<InvitationRowData>, organizationOptions: list<Option>, presetOptions: list<Option>} run()
 */
class InvitationList implements ChangesNoState
{
    use AsAction;

    /** @return array{invitations: list<InvitationRowData>, organizationOptions: list<Option>, presetOptions: list<Option>} */
    public function handle(): array
    {
        $invitations = InvitationToken::query()
            ->with('organization:id,name,is_active')
            ->latest('id')
            ->get();

        return [
            'invitations' => array_values($invitations->map(InvitationRowData::fromModel(...))->all()),
            // BR-IAM-14 — unit nonaktif tidak bisa menerima undangan baru.
            'organizationOptions' => array_values(Organization::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Organization $organization): array => ['value' => (string) $organization->id, 'label' => $organization->name])
                ->all()),
            'presetOptions' => InvitationPreset::options(),
        ];
    }
}
