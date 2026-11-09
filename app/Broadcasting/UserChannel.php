<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * RT1 — `App.Models.User.{id}`: hanya pemilik akun yang mendengar `sessions.ended` miliknya.
 * Parameter kanal datang sebagai teks dari nama kanal; PHP mengubahnya ke int di pemanggil.
 */
class UserChannel
{
    public function join(User $user, int $id): bool
    {
        return $user->id === $id;
    }
}
