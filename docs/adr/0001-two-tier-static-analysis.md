# 0001 — Static analysis dua tier

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

PHPStan berjalan di level 5 untuk seluruh `app/` dan lolos bersih. Menaikkan
level secara global tidak mungkin — hasil pengukuran pada 2026-08-16:

| Level | Error |
| ----- | ----- |
| 5     | 0     |
| 6     | 349   |
| 7     | 401   |
| 8     | 605   |
| 9     | 703   |
| 10    | 1211  |

Tetapi utangnya sangat terkonsentrasi. Pada level 9, per direktori:

| Direktori      | Error | File |
| -------------- | ----- | ---- |
| `app/Http`     | 487   | 39   |
| `app/Models`   | 173   | 44   |
| `app/Services` | 32    | 2    |
| `app/Exports`  | 2     | 1    |
| sisanya        | 5     | 12   |

Seluruh kode M0–M5 akan lahir di direktori baru (`app/Actions`, `app/Policies`,
`app/Events`, dst.) yang saat ini nyaris bersih di level 9.

Ada juga `phpstan-baseline.neon` sepanjang 1165 baris berisi 194 error yang
**tidak pernah di-`include`** oleh `phpstan.neon` — file mati.

## Keputusan

Dua konfigurasi:

- `phpstan.neon` — level 5, seluruh `app/`.
- `phpstan-strict.neon` — level 9, seluruh `app/` **kecuali** `app/Console`,
  `app/Exports`, `app/Http`, `app/Mail`, `app/Models`, `app/Providers`,
  `app/Services`.

Keduanya dijalankan CI sebagai step terpisah. `phpstan-baseline.neon` dihapus.

Rancangannya sengaja dibalik: yang didaftarkan adalah direktori legacy yang
**dikecualikan**, bukan direktori yang ikut ketat. Setiap folder baru otomatis
kena level 9 tanpa perlu ada yang ingat mendaftarkannya.

`excludePaths` hanya boleh menyusut. Menambah entri = menurunkan standar, wajib
dibahas eksplisit di PR.

## Konsekuensi

- Kode baru tidak bisa turun kualitas; utang legacy tidak memblokir siapa pun.
- Utang teknis jadi terlihat sebagai daftar yang bisa dihitung, bukan perasaan.
- Lima error di tier strict diperbaiki lebih dulu — dua di antaranya bug asli
  (`transitionTo()` dipanggil pada nilai nullable).
- Menyentuh file legacy tidak otomatis menuntut level 9; itu baru terjadi kalau
  direktorinya dikeluarkan dari daftar.

## Alternatif yang ditolak

**Naikkan level global + regenerate baseline.** Baseline menyembunyikan error
sampai file disentuh, tapi tidak membedakan kode baru dari kode lama — file baru
di `app/Http` akan ikut terlindungi baseline. Juga menambah 600+ entri yang harus
di-review orang.

**Biarkan level 5.** Melewatkan justru kelas bug yang paling relevan untuk Action
dan Policy: nullable, tipe iterable, dan generic.
