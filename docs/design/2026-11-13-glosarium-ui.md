# Glosarium Istilah UI

Keputusan pemilik 2026-11-13 (Paket A, K2 dan K9). Berlaku untuk **teks yang dilihat user**: isi template,
label, placeholder, judul halaman, pesan flash dan validasi, subjek email, label opsi yang dibangun di PHP.

**Tidak** berlaku untuk kode: nama kelas, route, kolom DB, nilai option, kunci props, dan dokumen DDD/BDD tetap
memakai istilah ubiquitous language (`submission`, `reviewer`, `trl`, ...).

## Istilah

| Inggris / lama        | Tampilan                    |
| --------------------- | --------------------------- |
| Dashboard             | Beranda                     |
| Submission            | Pengajuan                   |
| Submit / disubmit     | Kirim / dikirim             |
| Draft                 | Draf                        |
| Form                  | Formulir                    |
| Form Builder          | Pembuat Formulir            |
| Form Evaluasi         | Formulir Evaluasi           |
| Field                 | Isian                       |
| Step                  | Langkah                     |
| Phase / Phases        | Tahap                       |
| Review (kata benda)   | Penilaian                   |
| Reviewer              | Penilai                     |
| Researcher            | Peneliti                    |
| User                  | Pengguna                    |
| Role                  | Peran                       |
| Permission            | Hak Akses                   |
| Upload                | Unggah                      |
| Download              | Unduh                       |
| File                  | Berkas                      |
| Export                | Ekspor                      |
| Audit Log             | Log Audit                   |
| Login / Logout        | Masuk / Keluar              |
| Password              | Kata Sandi                  |
| Settings              | Pengaturan                  |
| Deadline              | Tenggat                     |
| Preview               | Pratinjau                   |
| Link                  | Tautan                      |
| Edit                  | Sunting / Ubah              |
| Read-only             | Hanya baca                  |
| TRL                   | TKT (Tingkat Kesiapterapan Teknologi) |

Tetap: **Monev**, **Operator**, **Email**, **NIDN**, **Admin**.

## Tanda baca (K9)

Teks UI tidak memuat "—". Dijaga `tests/Unit/Architecture/NoEmDashInUiTest.php` (string PHP di `app/`) dan
`tests/frontend/architecture/noEmDash.spec.ts` (template dan string skrip di `resources/js`).

| Pemakaian lama                 | Pengganti                          | Contoh                                  |
| ------------------------------ | ---------------------------------- | --------------------------------------- |
| Judul — subjudul               | titik dua                          | Langkah 3: Skema & TKT                  |
| Nilai — nilai (gabungan data)  | titik tengah " · "                 | TKT 4 · Validasi Lab                    |
| Klausa — klausa                | koma                               | Ekspor gagal, coba lagi.                |
| Keterangan tambahan            | kurung                             | Fakultas X (beserta unit di bawahnya)   |
| Nilai kosong "—"               | "-"                                | NIDN: -                                 |
| Subjek email "X — SIMPAS"      | "X \| SIMPAS"                      | Pengajuan disetujui \| SIMPAS           |
