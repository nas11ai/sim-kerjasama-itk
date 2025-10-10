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
            static::SUPER_ADMIN => 'Super Admin',
            static::ADMIN => 'Admin',
            static::TENAGA_KEPENDIDIKAN => 'Tenaga Kependidikan',
            static::MAHASISWA => 'Mahasiswa',
        };
    }
}
