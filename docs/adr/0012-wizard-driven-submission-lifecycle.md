# 0012 — Siklus pengajuan mengikuti wizard tujuh langkah

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Wireframe `RESEARCHER-02-02` menetapkan pembuatan pengajuan berupa wizard tujuh
langkah:

```
1 Pilih Form & Periode   5 Rencana Anggaran
2 Informasi Dasar        6 Upload Berkas
3 Skema & TRL            7 Review & Submit
4 Anggota Penelitian
```

Dokumen DDD tidak menyebut wizard sama sekali — alurnya digambarkan sebagai satu
rangkaian pengisian. Padahal wizard punya konsekuensi backend yang nyata: kapan
baris dibuat, di mana data parsial disimpan, kapan validasi berjalan, dan
bagaimana pengisian dilanjutkan setelah browser ditutup.

## Keputusan

**Baris `form_submissions` dibuat di langkah 1.** Ini dipaksa BR-FE-11, bukan
preferensi. Temporal Field Binding membandingkan `field.required_since` dengan
`submission.created_at`. Kalau baris baru lahir di langkah 3 atau 7, researcher
yang mulai mengisi Senin tetapi barisnya tercipta Jumat akan terkena field wajib
baru yang ditambahkan admin di tengah minggu — persis yang BR-FE-11 ingin cegah.

**Satu endpoint per langkah**, masing-masing membawa `last_updated_at` untuk
optimistic locking (BR-FE-14):

```
POST   /submissions                   1  buat draft, jangkar temporal
PATCH  /submissions/{id}/basic-info   2
PATCH  /submissions/{id}/scheme       3  scheme_id + trl_id
PUT    /submissions/{id}/members      4  cek COI (BR-SM-08)
PUT    /submissions/{id}/budget       5  cek max_budget (BR-BUD-01)
POST   /submissions/{id}/files        6
POST   /submissions/{id}/submit       7  validasi penuh + transisi
```

**Validasi bertingkat.** Validasi per langkah bersifat parsial. Temporal Field
Binding penuh hanya berjalan di langkah 7 — kalau dijalankan tiap langkah,
researcher terblokir di langkah 2 karena field milik langkah 6 belum diisi.

**Kolom `last_completed_step SMALLINT NOT NULL DEFAULT 0`** menyimpan kemajuan
wizard. Menebaknya dari kelengkapan data tidak bisa diandalkan: langkah 4 boleh
kosong secara sah (pengajuan tanpa anggota), sehingga "kosong" tidak berarti
"belum dikunjungi".

## Konsekuensi

- Draft berumur panjang; wizard tahan refresh dan ganti perangkat.
- Urutan langkah mengikat: `scheme_id` harus terisi sebelum langkah 5, karena
  batas anggaran berasal dari `scheme.max_budget`. Wireframe sudah menempatkan
  keduanya dengan urutan benar.
- `last_completed_step` secara teknis adalah state UI yang tinggal di tabel
  domain. Diterima sebagai kompromi sadar demi ketahanan lintas perangkat.
- Konsekuensi terpenting jatuh ke [ADR 0005](0005-scheme-selector-writes-to-column.md):
  karena `scheme_id` masih NULL sepanjang langkah 1–2, unique index BR-SM-01
  **tidak boleh** ikut menghitung DRAFT — kalau ikut, draft kedua ditolak sebelum
  penggunanya sempat memilih skema, padahal BR-SM-01 justru mengizinkan dua
  pengajuan ke skema berbeda.
- `01_submission.md` perlu diperbarui agar alurnya menyebut wizard.

## Alternatif yang ditolak

**Buat baris di langkah terakhir, simpan langkah 1–6 di client.** Melanggar
BR-FE-11 dan membuang isian saat browser ditutup — pengisian proposal berlangsung
berhari-hari, bukan sekali duduk.

**Satu endpoint `save` untuk semua langkah.** Payload jadi gemuk dan aturan
validasi per langkah bercampur; setiap penyimpanan parsial harus mengirim ulang
seluruh isi form.

**Simpan kemajuan wizard di `localStorage`.** Paling bersih secara domain, tapi
hilang saat ganti perangkat atau bersihkan browser.
