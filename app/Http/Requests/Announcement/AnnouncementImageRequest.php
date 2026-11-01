<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * fe-v2-design §4.7 K6a — gambar sisipan: JPG/PNG (SVG dan WebP ditolak), maks 2 MB dan
 * 4096 px per sisi. Isinya diperiksa `File::image()`, bukan sekadar ekstensinya.
 */
class AnnouncementImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|File>> */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png'])
                    ->max(2048)
                    ->dimensions(Rule::dimensions()->maxWidth(4096)->maxHeight(4096)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.required' => 'Pilih berkas gambar.',
            'image.image' => 'Berkas harus berupa gambar JPG atau PNG.',
            'image.mimes' => 'Berkas harus berupa gambar JPG atau PNG.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.dimensions' => 'Dimensi gambar maksimal 4096 × 4096 piksel.',
        ];
    }

    public function image(): UploadedFile
    {
        $file = $this->file('image');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
