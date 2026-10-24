# AGENTS.md — SIMPAS v2

Instruksi untuk coding agent (Claude Code, `dsc`/DeepSeek, dan lainnya) yang
bekerja di repo ini. Aturan lengkap ada di
[`.github/CODING_STANDARDS.md`](.github/CODING_STANDARDS.md) — file ini memuat hal
yang **tidak bisa kamu simpulkan sendiri dari membaca kode**.

---

## Stack

Laravel 13 · PHP 8.4 · PostgreSQL · Inertia · Vue 3 `<script setup>` · TypeScript ·
Tailwind · Pest · Vitest.

Domain-nya sistem pengajuan penelitian & pengabdian ITK. **Baca
[`docs/requirement-analysis/ddd/`](docs/requirement-analysis/ddd/) sebelum menyentuh
business logic** — di sana ada bounded context, business rule bernomor (BR-XX-NN),
dan ubiquitous language. Kode harus memakai istilah dari
[`02_ubiquitous_language.md`](docs/requirement-analysis/ddd/02_ubiquitous_language.md).

Skenario perilaku yang mengikat ada di
[`docs/requirement-analysis/bdd/`](docs/requirement-analysis/bdd/). Kalau kamu
mengerjakan sebuah business rule, **cari dulu skenarionya di sana** — itu yang
menetapkan perilaku yang teramati, sementara DDD menetapkan aturannya. Saat kamu
mengimplementasikan sebuah skenario, sebut ID-nya di deskripsi test
(`it('[FE-TEMPORAL-01] ...')`) lalu hapus ID itu dari `pending.txt` dan turunkan
plafonnya. `pending.txt` dan `uncovered.txt` **hanya boleh menyusut**.

Kalau kamu mengimplementasikan sebuah business rule, **sebut kodenya di komentar**
(mis. `// BR-SM-04 — hanya submitted_by yang boleh submit`).

---

## Verifikasi — tidak boleh dilewati

**Jangan pernah mengklaim pekerjaan selesai tanpa menjalankan perintah di bawah dan
membaca hasilnya.** Klaim tanpa bukti adalah pelanggaran paling serius di repo ini.

```bash
composer run quality   # pint + phpstan (2 tier) + pest
npm run quality        # eslint + prettier + vue-tsc + vitest
```

Kalau ada yang gagal, laporkan kegagalannya apa adanya. Jangan melaporkan
"selesai" untuk pekerjaan yang separuh jadi, dan jangan menyembunyikan step yang
kamu lewati.

### Kalau PHP host bukan 8.4

`composer install` **gagal di PHP 8.5** (`phpoffice/phpspreadsheet` membatasi
`<8.5.0`). Pest juga butuh `pdo_pgsql`. Pakai container:

```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  php:8.4-cli ./vendor/bin/pest
```

`phpunit.xml` meng-hardcode `DB_PORT=5432` / `DB_PASSWORD=root`, tapi environment
variable menimpanya. Sesuaikan dengan PostgreSQL lokalmu.

---

## Aturan yang paling sering dilanggar

**1. Status hanya berubah lewat `transitionTo()`.**

```php
$submission->update(['status' => Approved::class]);   // ❌
$submission->status->transitionTo(Approved::class);   // ✅
```

**2. Setiap query yang mengembalikan data submission wajib di-scope.**
Mengecek `FormAccessControl` saja tidak cukup — itu access control level Form,
bukan level Submission (BR-SM-07).

```php
abort_unless($submission->isAccessibleBy($request->user()), 403);
```

**3. Business logic tidak boleh di Controller.** Controller hanya: FormRequest →
DTO → Action → response. Kalau kamu menulis lebih dari beberapa baris logic di
controller, pindahkan ke `app/Actions/`.

**4. PostgreSQL, bukan MySQL.** Tidak ada `YEAR()`, tidak ada
`SUM(cond = 'x')`, tidak ada `LIKE` untuk pencarian case-insensitive (pakai
`whereLike` / `ILIKE`). Kolom non-agregat wajib ikut `GROUP BY`.

**5. Jangan menyiasati PHPStan.** Dilarang `@phpstan-ignore`, entri baseline,
`assert()`, inline `@var`, cast, atau melebarkan tipe supaya error hilang. Error
di tier strict hampir selalu bug asli — perbaiki penyebabnya.

**6. Jangan ada percabangan khusus testing di production code.**
`if (app()->environment('testing'))` dilarang. Test yang sulit ditulis = sinyal
desain perlu diperbaiki.

**7. Tidak ada `any` di TypeScript, tidak ada `console.log`.**
`console.error` dan `console.warn` boleh.

**8. Pakai guard clause, bukan `if` bersarang.** Tangani kondisi gagal lebih dulu
lalu keluar, sehingga jalur normal tetap rata kiri. Satu Action = satu use case;
kalau namanya mengandung "dan", itu dua Action. Jangan membangun abstraksi untuk
kebutuhan yang belum ada.

Kosakata smell dan teknik refactoring yang dipakai di repo ini ada di
[`.github/CODING_STANDARDS.md`](.github/CODING_STANDARDS.md) bagian *Kualitas Kode
di Dalam Satu Unit*, mengikuti katalog refactoring.guru. Sebutkan nama tekniknya
saat mengusulkan perubahan bentuk kode.

---

## Di mana meletakkan kode baru

| Jenis                    | Lokasi                  | Catatan                          |
| ------------------------ | ----------------------- | -------------------------------- |
| Business use case        | `app/Actions/`          | otomatis tier strict             |
| Kueri baca (tanpa ubah state) | `app/Queries/`          | `implements ChangesNoState`; otomatis tier strict |
| Authorization            | `app/Policies/`         | otomatis tier strict             |
| Domain event & listener  | `app/Events/`, `app/Listeners/` | otomatis tier strict     |
| Notifikasi               | `app/Notifications/`    | Laravel Notification, bukan Mail custom |
| DTO                      | `app/Data/`             | `spatie/laravel-data`            |
| Enum                     | `app/Enums/`            |                                  |
| Tipe untuk Inertia props | `resources/js/types/`   |                                  |

Direktori di `app/` **selain** `Http`, `Models`, `Services`, `Providers`,
`Console`, `Exports` otomatis kena tier strict. Level-nya ditetapkan
`phpstan-strict.neon`, bukan berkas ini — supaya tidak ada dua angka yang
berbeda saat level dinaikkan. Jangan menambah pengecualian baru di sana:
daftarnya hanya boleh menyusut.

---

## Kode legacy

Repo ini fork dari sim-kerjasama-itk. Sebagian controller sangat gemuk
(`SubmissionViewController` ~1000 baris, `ReviewController` ~780 baris) dan ada
business logic yang **bertentangan dengan dokumen DDD** — misalnya auto-reject
yang melanggar BR-REV-07.

Pendekatan yang disepakati adalah **strangler**: saat kamu menyentuh sebuah flow,
ekstrak logic-nya ke Action + Policy beserta test-nya. Jangan melakukan refactor
besar yang tidak diminta, dan jangan pula menyalin pola legacy yang salah ke kode
baru.

---

## Batas

- Jangan menjalankan migration terhadap database berisi data sungguhan.
- Jangan commit atau push kecuali diminta.
- Jangan menaikkan/menurunkan versi dependency sebagai efek samping pekerjaan lain.
- Jangan mengedit `docs/requirement-analysis/ddd/` tanpa diminta — itu dokumen
  kesepakatan, bukan catatan implementasi.
- Kalau sebuah perintah gagal karena environment (versi PHP, ekstensi hilang,
  database mati), **katakan** — jangan diam-diam melewatinya lalu melaporkan sukses.
