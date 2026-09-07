<?php

namespace App\Support;

/**
 * BR-IAM-01 — domain `@itk.ac.id` wajib untuk registrasi mandiri.
 *
 * Pencocokannya **domain persis, case-insensitive**. Ditulis sebagai
 * perbandingan domain penuh, bukan `str_ends_with` maupun `str_contains`,
 * karena keduanya meloloskan hal yang berbeda dan sama berbahayanya:
 *
 *   - `str_contains`  → `budi@itk.ac.id.example.com` lolos; domain itu bisa
 *                       didaftarkan siapa saja.
 *   - `str_ends_with` → `budi@notitk.ac.id` lolos.
 *
 * Subdomain seperti `@student.itk.ac.id` juga ditolak: jalur mandiri memang
 * untuk dosen, dan mahasiswa bukan pengaju di sistem ini.
 *
 * Batas ini berlaku **hanya** pada jalur mandiri. Jalur undangan tidak
 * dibatasi domain — memasang pengecekan ini di lapis yang salah membuat mitra
 * eksternal tidak pernah bisa masuk (BR-IAM-12).
 */
final class EmailDomainGuard
{
    public const INTERNAL_DOMAIN = 'itk.ac.id';

    public static function isInternal(string $email): bool
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = strtolower(substr($email, $at + 1));

        return $domain === self::INTERNAL_DOMAIN;
    }
}
