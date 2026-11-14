# Matriks Uji QA (Excel) — Desain

K1 langkah 5 dari `docs/plans/2026-11-13-paket-a-masukan-pemilik.md`. Pemilik menyerahkan cara pengerjaan
("langsung jalan pakai usulanmu"); permintaan tambahan sesi 2026-11-14: skenario berpola Gherkin, bahasa
Indonesia yang sangat mudah dipahami, lokasi halaman disebut, dan berkas harus bisa dimasukkan ke spreadsheet
(Google Sheets).

## Untuk siapa dan untuk apa

3–4 penguji QA menguji staging bersama (`docs/qa/staging.md`) secara serentak. Masing-masing memakai set data
sendiri `qa1`–`qa4` (`docs/qa/data-uji.md`): 12 pengajuan tahun ini di setiap status, riwayat tahun lalu, dan
akun semua peran. Matriks memberi tahu penguji apa yang diuji, di halaman mana, apa hasil yang benar, lalu
menampung hasil dan temuan supaya pemilik bisa melihat kemajuan.

## Tujuan / bukan tujuan

- Tujuan: setiap fitur yang terlihat di layar punya minimal satu kasus jalur berhasil dan, bila ada aturan
  penolakan yang bisa dicoba dari layar, satu kasus jalur ditolak. Setiap kasus menyebut skenario BDD-nya.
- Tujuan: penguji yang belum pernah membaca kode bisa menjalankan kasus hanya dari isi baris itu.
- Bukan tujuan: satu baris per skenario BDD. Banyak skenario hanya teramati lewat waktu (tenggat lewat,
  tautan 24 jam), konkurensi, atau job yang gagal; itu sudah diuji otomatis (Pest) dan ditandai begitu di
  lembar cakupan.
- Anti-tujuan: kasus yang saling menimpa data antarpenguji, atau kasus yang urutannya rusak bila dikerjakan
  dua orang.

## Pendekatan

| Pilihan | Untung | Rugi |
| --- | --- | --- |
| **A. Kasus per alur (dipilih)** | Satu alur = satu rangkaian di satu set data, dikerjakan satu penguji dari atas ke bawah; status yang dipakai kasus berikutnya disiapkan kasus sebelumnya | Pembagian kerja per alur, bukan per baris |
| B. Satu baris per skenario BDD (538) | Jejak lengkap | Terlalu banyak, banyak yang tidak bisa diuji dari layar |
| C. Dibagi per modul ke tiap penguji | Mudah dibagi | Rantai status putus: penilai di set B tidak punya pengajuan yang sudah ditugaskan oleh penguji lain |

## Bentuk berkas

`docs/qa/matriks-uji-qa.xlsx`, dibangun ulang dengan `scripts/qa/buat_matriks_uji.py` (isi kasus di
`scripts/qa/kasus_uji.py`, satu tempat untuk disunting). Lembar:

1. **Petunjuk**: cara pakai, arti kolom dan nilai dropdown, contoh satu baris yang sudah diisi, aturan main
   data bersama.
2. **Kasus Uji**: satu baris per kasus. Kolom: ID, Alur, Urutan, Skenario, Prioritas, Masuk sebagai, Lokasi
   halaman (menu + alamat), Diketahui, Ketika, Maka, Mengubah data?, Rujukan BDD, lalu kolom isian penguji:
   Penguji, Tanggal uji, Hasil, Catatan / ID Temuan.
3. **Temuan**: log bug (ID, tanggal, penguji, kasus, ringkasan, langkah mengulang, yang terjadi, yang
   seharusnya, tingkat, tautan tangkapan layar, status).
4. **Ringkasan**: rumus `COUNTIFS` per alur dan per penguji atas kolom Hasil.
5. **Akun & Data**: ringkasan `data-uji.md` (akun per set, 12 pengajuan) supaya penguji tidak perlu membuka repo.
6. **Pembagian**: usulan alur per penguji (empat penguji; bila tiga, alur QA4 dibagi rata).
7. **Cakupan BDD**: semua ID skenario dari `docs/requirement-analysis/bdd/` beserta kasus manual yang
   mencakupnya, atau "otomatis (Pest)". Dibaca langsung dari berkas `.feature` saat membangun.

## Keputusan

- Gherkin dengan kata kunci Indonesia (Diketahui / Ketika / Maka / Dan), satu kolom per kata kunci; langkah
  "Ketika" boleh bernomor. Bahasa sehari-hari; istilah layar mengikuti label tombol dan menu yang ada di
  aplikasi (glosarium UI), bukan istilah kode.
- Lokasi halaman = jalur menu kiri ("Menu kiri › Pengajuan › Daftar Pengajuan") + alamat relatif
  (`/user/submissions`). Bagian yang berupa nomor ditulis `…`.
- Data konfigurasi bersama (Formulir, Tahap, Periode, Skema, Organisasi, Pengumuman, Konfigurasi Umum) hanya
  diubah dengan membuat data baru berawalan `QA{k}`; data bawaan tidak disunting.
- Kasus yang butuh tautan email (verifikasi email pendaftaran) ditandai: staging memakai `MAIL_MAILER=log`, jadi
  pemilik VM membacakan tautannya dari log.
- Aman untuk Google Sheets: hanya data validation daftar, conditional formatting berbasis nilai sel, `COUNTIF`
  / `COUNTIFS` / `COUNTA`, freeze panes, wrap text. Tanpa tabel Excel, referensi terstruktur, makro, proteksi,
  sel gabungan di area data, atau fungsi Excel 365.
- Generator memakai Python + `openpyxl` lewat `uv run --with openpyxl` (tanpa dependency proyek baru).
