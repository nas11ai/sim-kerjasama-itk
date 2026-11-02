<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\ChangeOwnPassword;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    use ResolvesActor;

    /** W66 — ganti kata sandi sendiri (SHARED-01-07), dicatat lewat ChangeOwnPassword (BR-RPT-09). */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        ChangeOwnPassword::run($this->actor($request), $validated['password']);

        return back()->with('success', 'Kata sandi Anda berhasil diperbarui.');
    }
}
