<?php

namespace App\Support;

/**
 * BR-IAM-01 — domain `@itk.ac.id` wajib untuk registrasi mandiri, dan **hanya**
 * di jalur itu: jalur undangan tidak dibatasi domain, jadi memasang pengecekan
 * ini di lapis yang salah membuat mitra eksternal tak pernah masuk (BR-IAM-12).
 */
final class EmailDomainGuard
{
    // Subdomain seperti `@student.itk.ac.id` ikut ditolak: jalur mandiri memang
    // untuk dosen, dan mahasiswa bukan pengaju di sistem ini.
    public const INTERNAL_DOMAIN = 'itk.ac.id';

    public static function isInternal(string $email): bool
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = strtolower(substr($email, $at + 1));

        // Perbandingan domain penuh, bukan `str_contains` yang meloloskan
        // `budi@itk.ac.id.example.com` (bisa didaftarkan siapa saja), bukan
        // pula `str_ends_with` yang meloloskan `budi@notitk.ac.id`.
        return $domain === self::INTERNAL_DOMAIN;
    }
}
