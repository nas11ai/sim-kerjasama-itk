<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * W65 — foto profil (SHARED-01-06/07): JPG/PNG maks 2 MB. Isinya diperiksa
 * `File::image()`, bukan sekadar ekstensinya, sehingga SVG atau PDF berganti nama ditolak.
 */
class ProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Satu aturan foto, dipakai juga oleh Lengkapi Profil. */
    public static function photoRule(): File
    {
        return File::image()->types(['jpg', 'jpeg', 'png'])->max(2048);
    }

    /** @return array<string, string> */
    public static function photoMessages(): array
    {
        return [
            'photo.required' => 'Pilih foto profil.',
            'photo.image' => 'Foto harus berformat JPG atau PNG.',
            'photo.mimes' => 'Foto harus berformat JPG atau PNG.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }

    /** @return array<string, list<string|File>> */
    public function rules(): array
    {
        return ['photo' => ['required', self::photoRule()]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::photoMessages();
    }

    public function photo(): UploadedFile
    {
        $file = $this->file('photo');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
