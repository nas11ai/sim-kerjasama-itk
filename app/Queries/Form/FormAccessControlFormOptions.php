<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Queries\Identity\FacultyOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pilihan form, permission, dan fakultas yang dibaca layar daftar, Buat, dan Ubah kontrol
 * akses. Fakultas membawa prodinya; layar menambah opsi "Seluruh <fakultas>" (ADR 0013).
 *
 * @phpstan-import-type FacultyOption from FacultyOptions
 *
 * @phpstan-type Options array{forms: Collection<int, Form>, permissions: list<string>, faculties: SupportCollection<int, FacultyOption>}
 *
 * @method static Options run()
 */
class FormAccessControlFormOptions implements ChangesNoState
{
    use AsAction;

    /** @return Options */
    public function handle(): array
    {
        return [
            'forms' => Form::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'permissions' => FormAccessControl::ALLOWED_PERMISSIONS,
            'faculties' => FacultyOptions::run(),
        ];
    }
}
