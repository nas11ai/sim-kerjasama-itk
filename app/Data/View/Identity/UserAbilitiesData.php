<?php

namespace App\Data\View\Identity;

use App\Models\User;
use Spatie\LaravelData\Data;

/** `can` OPERATOR-08-03 dari UserPolicy; frontend tidak memeriksa peran (fe-v2-design §4). */
final class UserAbilitiesData extends Data
{
    public function __construct(
        public bool $deactivate,
        public bool $changeRoles,
        public bool $managePermissions,
        public bool $appointReviewer,
    ) {}

    public static function for(User $viewer, User $user): self
    {
        return new self(
            deactivate: $viewer->can('deactivate', $user),
            changeRoles: $viewer->can('changeRoles', $user),
            managePermissions: $viewer->can('managePermissions', $user),
            appointReviewer: $viewer->can('reviewers.assign'),
        );
    }
}
