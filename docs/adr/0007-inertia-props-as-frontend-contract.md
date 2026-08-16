# 0007 — Kontrak frontend berupa Inertia props + tipe TypeScript

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Backend dikerjakan lebih dulu supaya tim frontend tinggal mengerjakan tampilan.
Wireframe sudah lengkap (57 frame, semua peran) di repo `simpas-wireframe`.
Pertanyaannya: bentuk serah-terima backend → frontend seperti apa.

Codebase sudah memakai Inertia + Vue 3 + TypeScript. Menambahkan lapisan REST API
berarti menyimpang dari arsitektur yang ada dan menambah pekerjaan yang tidak
dibutuhkan (token auth, CORS, state management per resource).

## Keputusan

Controller mengembalikan `Inertia::render()` dengan props yang sudah final,
disertai:

1. **Interface TypeScript** di `resources/js/types/` untuk setiap halaman.
2. **Stub `.vue` minimal** per route yang merender data mentah, supaya frontend
   tinggal mengganti isi `<template>` memakai wireframe.
3. **Objek `can`** di setiap props halaman berisi hasil Policy, supaya frontend
   bisa menyembunyikan UI tanpa menebak aturan otorisasi.

Props disusun memakai `spatie/laravel-data`, yang ditambahkan bersamaan dengan
keputusan ini karena `CONTRIBUTING.md` sudah mewajibkan DTO memakai paket itu
padahal paketnya belum terpasang. DTO yang sama dipakai sebagai input Action dan
output props, sehingga bentuk data punya satu definisi.

## Konsekuensi

- Frontend tidak menebak bentuk data; kontraknya eksplisit dan bertipe.
- Otorisasi tetap di backend — frontend hanya menyembunyikan, tidak menjaga.
- Stub `.vue` harus dijaga tetap minimal; begitu frontend mengisinya, backend
  tidak menyentuhnya lagi.
- Enum `SubmissionStatus` di frontend saat ini (`PENDING`, `UNDER_REVIEW`,
  `NEEDS_REVISION`, `APPROVED`, `REJECTED`) **tidak cocok** dengan state backend
  (delapan state, termasuk `DRAFT`, `SUBMITTED`, `RESUBMITTED`, `WITHDRAWN`).
  Wireframe memakai kosakata yang benar. Enum frontend harus diselaraskan saat
  kontrak disusun.

## Alternatif yang ditolak

**REST API ber-versi + OpenAPI.** Menyimpang dari arsitektur Inertia yang sudah
berjalan, menambah ~2000 baris spec dan lapisan auth baru, tanpa kebutuhan yang
menuntutnya. Bisa ditinjau ulang kalau nanti ada konsumen selain web app.

**Controller + test saja, tanpa stub.** Paling cepat bagi backend, paling banyak
tebak-tebakan bagi frontend — bertentangan dengan tujuan keputusan ini.
