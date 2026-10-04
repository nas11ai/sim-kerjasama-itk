# Gelombang 3 bagian 9 — Kontrol akses formulir mengikuti ADR 0013

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `49ec121`.

**Goal:** `FormAccessControlController` memenuhi ambang keras dan keluar dari `quality-legacy.txt`
(`max.legacy.files` 104 → 103); kontrol akses bisa dipasang pada unit di atas prodi sebagaimana
ADR 0013 maksudkan, dan menyuntingnya tidak merusak pengajuan yang sudah ada.

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §5 · BR-FE-02 · ADR 0013 (akses mengikuti
leluhur organisasi) · BR-FE-20 (penjaga referensi).

**Status:** selesai — Task 1–2 dikerjakan; `FormAccessControlController` keluar dari daftar
legacy (plafon 104 → 103).

---

## Pengukuran awal

Ukuran berkas; `index()` loc 67, cc 10; `bulkCreate()` loc 48; `bulkDelete()` loc 41.

## Temuan — diverifikasi di kode

**C1 — Hanya prodi yang bisa dipilih, padahal ADR 0013 dirancang untuk unit yang lebih luas.**
`store`, `update`, dan `bulkCreate` memvalidasi `study_program_id` dengan
`exists:organizations,id,type=study_program`. ADR 0013: *form yang dipasang pada sebuah organisasi
terlihat oleh organisasi itu dan semua unit di bawahnya* — tujuannya justru memungkinkan form
se-fakultas atau se-institut dengan satu kontrol akses. Hari ini Operator harus membuat satu kontrol
akses per prodi.

**C2 — Mengubah form sebuah kontrol akses yang sedang dipakai memindahkan pengajuan diam-diam.**
`update()` menerima `form_id` baru untuk kontrol akses yang sudah dirujuk `form_phase_details`
(dan lewat itu oleh pengajuan). Detail tahap dan pengajuannya lalu menunjuk form lain — semangat
BR-FE-20/23 yang sama dengan putusan slice tahap formulir.

**C3 — Filter fakultas di daftar hanya melihat anak langsung.** `whereHas('studyProgram', parent_id =
faculty)` melewatkan prodi di bawah departemen, dan kontrol akses yang dipasang pada fakultas itu
sendiri.

**C4 — Hapus massal sebagian dan bahasa campur; tanpa audit; galat bocor.** `bulkDelete` menghapus
yang bisa dan melaporkan sisanya dalam bahasa Inggris; `bulkCreate` juga. Tidak satu pun aksi
diaudit (BR-RPT-09); semua menempel `$e->getMessage()`.

## Keputusan

- **K1 — Kontrol akses boleh dipasang pada organisasi aktif bertipe apa pun** (validasi
  `exists:organizations,id` + aktif). Kunci request `study_program_id` dipertahankan agar Vue tak
  berubah bentuk; nilainya kini boleh id fakultas. Vue Create/Edit/bulk: pemilih prodi mendapat satu
  opsi tambahan **"Seluruh <nama fakultas>"** yang bernilai id fakultas itu. Unit institut/akar
  menunggu layar v2. Perubahan Vue minimal (sesuai pengecualian yang sudah dipakai slice lalu:
  tanpa ini aturan ADR 0013 tak terjangkau dari layar).
- **K2 — Form kontrol akses yang dipakai tidak boleh diganti.** Bila dirujuk `form_phase_details`,
  perubahan `form_id` ditolak (galat pada `form_id`). Permission dan organisasi tetap boleh diubah:
  itu kebijakan akses, bukan identitas data.
- **K3 — Filter fakultas memakai subtree** (unit fakultas itu sendiri dan seluruh keturunannya).
- **K4 — Action beraudit**: `Actions/Form/{GrantFormAccess, ReviseFormAccess, RevokeFormAccess,
  GrantFormAccessInBulk, RevokeFormAccessInBulk}` (nama boleh disesuaikan, satu use case per Action).
  Hapus massal **semua-atau-tidak** dengan alasan yang terbaca (sama dengan slice tahap). Pesan bahasa
  Indonesia. Duplikat pada buat massal tetap dilewati dan dilaporkan.
- **K5 — Daftar lewat Query** `Queries/Form/FormAccessControlDirectory`; bentuk prop tetap.

## Global Constraints

- Identifier bahasa Inggris; prosa komentar bahasa Indonesia, ≤ 3 baris berturut-turut, ≤ 93
  karakter termasuk indentasi; sebut kode BR/ADR.
- Dilarang menyiasati PHPStan. Action menulis audit; Query `ChangesNoState`; `app/Support` tidak
  menulis state.
- `quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut. Nama test menyebut kode BR
  atau perilaku — jangan mengarang ID skenario.
- Vue legacy hanya disentuh sebatas K1 dan pesan galat yang diperlukan. Jangan sentuh
  `docs/requirement-analysis/`. Commit satu baris tanpa trailer.
- Container `CI`, DB test `simpas_dev_pg` :55432; satu proses test pada satu waktu.

## Definisi selesai

- [x] Kontrol akses tingkat fakultas bisa dibuat dari layar dan membuka form bagi user prodi di bawahnya
- [x] Gerbang hijau; controller keluar dari daftar; plafon 104 → 103

## Yang berbeda dari rencana

- Hasil ukur: controller 130 baris (method terpanjang `bulkDelete()` loc 14, cc 1);
  `FormAccessControlDirectory` 111 baris (`filtered()` loc 26, cc 6).
- Penjaga hapus menjadi Action `AssertFormAccessUnused` (`ChangesNoState`), bukan
  `ReferenceGuard`: pesan bawaannya menganjurkan "Nonaktifkan saja", padahal kontrol akses tidak
  punya status aktif. Penolakannya menyebut form, permission, organisasi, dan jumlah detail tahap.
- Pilihan form, permission, dan fakultas pindah ke Query `FormAccessControlFormOptions`, dipakai
  layar daftar, Buat, dan Ubah.
- Hapus massal dan buat massal menulis audit per kontrol akses ("Kontrol akses formulir
  dihapus"/"dibuat"), dengan deskripsi yang sama seperti aksi tunggal.
- Filter fakultas memakai `Organization::subtreeIds()`, yang hanya menelusuri keturunan aktif:
  kontrol akses pada unit nonaktif di bawah fakultas tidak ikut terfilter.
- Layar Ubah memilih fakultasnya sendiri bila organisasinya fakultas; layar daftar, detail, dan
  Ubah membaca `study_program.faculty` dengan `?.` agar kontrol akses tingkat fakultas tak pecah.
- Toast hapus di layar daftar kini menampilkan alasan penolakan (`errors.id`).
- Filter daftar dengan id bukan angka kini tidak menemukan apa pun, bukan galat SQL.
- Kelompok di layar daftar tetap memuat semua kontrol akses form itu, bukan hanya yang lolos
  filter — perilaku lama dipertahankan.

---
---

# Rencana Implementasi

### Task 1: Organisasi apa pun dan form yang terkunci (C1, C2, K1, K2)

- [x] Test merah: membuat kontrol akses pada fakultas → tersimpan, dan user di prodi bawahnya bisa
  mengakses form (lewat jalur akses yang ada, ADR 0013); mengganti `form_id` kontrol akses yang
  dipakai detail tahap → ditolak, tak berubah; mengganti permission/organisasinya → tersimpan;
  organisasi nonaktif → ditolak.
- [x] Implementasi backend K1, K2 (Action beraudit untuk buat dan ubah).
- [x] Vue: opsi "Seluruh <fakultas>" di Create/Edit (dan bulk create bila memakai pemilih yang sama).
- [x] Gerbang (`npm run quality` juga); commit `feat(form): kontrol akses bisa dipasang pada fakultas (ADR 0013), form yang dipakai terkunci`

### Task 2: Hapus, massal, daftar (C3, C4, K3–K5)

- [x] Test merah: hapus massal [tak terpakai, terpakai] → tidak ada yang terhapus, alasan terbaca;
  hapus dan buat massal diaudit; pesan bahasa Indonesia; filter fakultas menemukan kontrol akses di
  prodi bawah departemen dan pada fakultas itu sendiri.
- [x] Implementasi; `tools/limits.php`/`prosa.php` bersih; hapus baris controller; plafon 104 → 103;
  status dokumen + *Yang berbeda dari rencana*.
- [x] Commit `refactor(form): kontrol akses lewat Action beraudit dan keluar dari daftar legacy`
