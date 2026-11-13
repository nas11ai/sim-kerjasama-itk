# Data Uji QA

Data uji untuk 3–4 penguji yang menguji serentak di database yang sama. Setiap penguji memakai **set**-nya
sendiri (`qa1` sampai `qa4`), jadi tidak ada yang saling menimpa. Desain: `docs/design/2026-11-13-seeder-data-uji-qa-design.md`.

## Mengisi atau mengembalikan ke awal

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=QaDataSeeder
```

- Kedua perintah **menghapus seluruh isi database**. Jangan jalankan di database berisi data sungguhan.
- Seeder menolak berjalan di `APP_ENV=production` dan menolak berjalan dua kali. Untuk mengulang, selalu mulai
  dari `migrate:fresh --seed`.
- Berkas (foto profil, proposal, laporan monev) **tidak** mengikuti `FILESYSTEM_DISK`: unggahan pengguna selalu
  disimpan di disk privat `local` (`storage/app/private`) milik mesin yang menjalankan seeder. Jalankan seeder di
  mesin yang sama dengan aplikasi yang dipakai QA; bila seeder dijalankan dari laptop lain ke database bersama,
  tautan berkasnya akan 404 di mesin QA (lihat B27 di catatan bug).
- Lama proses sekitar satu menit di database lokal dengan `BCRYPT_ROUNDS=4` (ukuran test). Dengan cost bcrypt
  bawaan dan database jarak jauh seperti Neon, perkirakan beberapa kali lebih lama.
- Tahun mengikuti tanggal saat seeder dijalankan: "tahun ini" dan "tahun lalu".

## Akun

Kata sandi setiap akun = alamat emailnya. Semua email berakhiran `@simpas.test` dan tidak pernah terkirim ke
orang sungguhan.

| Akun | Peran |
|---|---|
| `qa-admin@simpas.test` | Admin (bersama, satu untuk semua penguji) |
| `qa{k}-operator@simpas.test` | Operator LPPM set k |
| `qa{k}-penilai1@`, `qa{k}-penilai2@` | Penilai internal set k |
| `qa{k}-penilai-luar@` | Penilai eksternal set k (juga penilai laporan monev) |
| `qa{k}-peneliti01@` … `qa{k}-peneliti12@` | Peneliti; nomor = nomor skenario di bawah |
| `qa{k}-anggota@` | Peneliti yang menjadi anggota tim di pengajuan skenario 9 sampai 12 dan riwayat |
| `qa{k}-peneliti-baru@` | Peneliti yang NIDN-nya **menunggu verifikasi** (antrean verifikasi Operator) |
| `qa{k}-peneliti-ditolak@` | Peneliti yang NIDN-nya **ditolak** |

Nama tampilan setiap akun berawalan `QA{k}`, dan judul pengajuannya berawalan `[QA{k}]`. Operator dan Admin
melihat pengajuan **semua** set; bekerjalah hanya pada yang berawalan set Anda.

## Pengajuan tahun ini per set

| No | Pemilik | Keadaan |
|---|---|---|
| 1 | peneliti01 | Draf, baru sampai langkah 2 |
| 2 | peneliti02 | Draf lengkap, siap dikirim |
| 3 | peneliti03 | Menunggu Penilaian, belum ada penilai |
| 4 | peneliti04 | Menunggu Penilaian, baru 1 dari 2 penilai ditugaskan |
| 5 | peneliti05 | Sedang Dinilai, penilai1 sudah mengirim penilaian, penilai2 belum |
| 6 | peneliti06 | Sedang Dinilai, kedua penilai Selesai: **siap diputuskan** Operator |
| 7 | peneliti07 | Perlu Revisi (penilai1 membuka catatan) |
| 8 | peneliti08 | Sedang Dinilai, putaran pemeriksaan ulang setelah dikirim ulang (lihat catatan) |
| 9 | peneliti09 | Disetujui, laporan Monev 1 masih draf, satu luaran tercatat |
| 10 | peneliti10 | Disetujui, laporan Monev 1 terkirim dan menunggu penilai-luar |
| 11 | peneliti11 | Ditolak |
| 12 | peneliti12 | Ditarik Kembali (sesudah disetujui) |

Nomor ganjil memakai formulir Penelitian (skema Penelitian Dasar atau Terapan), nomor genap formulir Pengabdian.

**Diajukan Ulang** tidak bisa ditemukan sebagai status yang berhenti: begitu peneliti mengirim ulang, aplikasi
langsung membuka putaran pemeriksaan ulang (Sedang Dinilai). Jejaknya terlihat di riwayat dan Log Audit
pengajuan nomor 8.

## Riwayat tahun lalu per set

Peneliti01 sampai peneliti04 masing-masing punya satu pengajuan di periode tahun lalu: tiga Disetujui (laporan
Monev 1 sudah dinilai, luaran tercatat) dan satu Ditolak (peneliti04).

## Data latar

Akun `latar-*@simpas.test` dan pengajuan berawalan `[Latar]` hanya mengisi layar tingkat institusi (paginasi,
statistik per fakultas, perbandingan tahun). Mohon tidak diubah. Tidak ada kerugian bila terubah, tetapi
angka statistik jadi bergeser dari yang diharapkan.

## Yang terlihat janggal tetapi memang begitu datanya

- `latar-penilai1` dan `latar-penilai2` memegang sekitar 20 penilaian aktif, di atas batas beban skema (10).
  Peringatan beban penilai di layar penugasan untuk keduanya memang benar, bukan bug.
- Akun `qa{k}-peneliti-baru` dibuat langsung oleh seeder, bukan lewat formulir pendaftaran, jadi Operator **tidak**
  menerima notifikasi "pendaftaran menunggu verifikasi" untuknya. Akunnya tetap muncul di antrean verifikasi.
  Untuk menguji notifikasi itu, daftarkan akun baru lewat halaman pendaftaran.
