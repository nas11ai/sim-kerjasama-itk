<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/** Pengguna yang melakukan aksi, untuk jejak audit Action (BR-RPT-09). */
trait ResolvesActor
{
    private function actor(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
