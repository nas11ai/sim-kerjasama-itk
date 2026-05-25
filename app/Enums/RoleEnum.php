<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPER_ADMIN = 'Super Admin';
    case ADMIN = 'Admin';
    case TENAGA_KEPENDIDIKAN = 'Tenaga Kependidikan';
    case MAHASISWA = 'Mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::TENAGA_KEPENDIDIKAN => 'Tenaga Kependidikan',
            self::MAHASISWA => 'Mahasiswa',
        };
    }
}
