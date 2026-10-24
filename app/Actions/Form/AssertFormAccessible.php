<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menolak user yang tidak dicakup satu pun kontrol akses form itu. Hanya membaca dan
 * menolak, **tidak menulis audit (BR-RPT-09).**
 *
 * @method static void run(User $user, Form $form, string $message)
 */
class AssertFormAccessible implements ChangesNoState
{
    use AsAction;

    /** @throws AuthorizationException */
    public function handle(User $user, Form $form, string $message): void
    {
        $accessible = FormAccessControl::query()
            ->where('form_id', $form->id)
            ->accessibleBy($user)
            ->exists();

        if (!$accessible) {
            throw new AuthorizationException($message);
        }
    }
}
