<?php

namespace App\Data\Request;

/**
 * BR-IAM-03 — dua dari tiga data tambahan profil (foto diunggah terpisah). Aturan
 * validasinya satu, dipakai Profil Saya dan Lengkapi Profil.
 */
final class ProfileDetailsInput
{
    public function __construct(
        public readonly string $phone,
        public readonly string $expertise,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 \-]{6,30}$/'],
            'expertise' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, tanda hubung, dan + di depan.',
            'expertise.required' => 'Bidang keahlian wajib diisi.',
        ];
    }
}
