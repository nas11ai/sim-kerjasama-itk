<?php

namespace App\Support\Lifecycle;

use RuntimeException;

/**
 * Entitas yang tidak pernah boleh hilang menolak penghapusannya di sini.
 *
 * Dilempar dari `booted()` model, bukan dari Policy, dengan alasan yang sama
 * yang sudah dipakai jejak audit (BR-RPT-04): Policy hanya dilewati kalau ada
 * yang memanggilnya, sedangkan penjagaan di model ikut menutup setiap jalur
 * yang memakai Eloquent — termasuk tinker, seeder, dan penghapusan massal
 * lewat relasi.
 *
 * Bedakan dari penolakan bersyarat seperti BR-SC-01 dan BR-FE-19, yang
 * menolak **selama masih dirujuk** dan karena itu memakai ValidationException:
 * penolakan di sana adalah keadaan yang bisa berubah, sedangkan penolakan di
 * sini adalah sifat entitasnya.
 */
final class DeletionNotPermitted extends RuntimeException {}
