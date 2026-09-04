# Register Kontradiksi & Pertanyaan Terbuka

Kontradiksi antara DDD, wireframe, dan kode legacy **tidak diputuskan sepihak** saat
menulis skenario. Ia dicatat di sini dengan opsi dan rekomendasi, diputuskan pemilik
produk, baru skenarionya mengunci keputusan itu.

## Terbuka

| # | Kontradiksi | Sumber | Memblokir |
| - | ----------- | ------ | --------- |
| 1 | `Gate::before` memberi Super Admin keempat ability `FormSubmissionPolicy` tanpa syarat, sementara `SubmitFormSubmission::run()` menegakkan BR-SM-04 sendiri | `SuperAdminSubmitDivergenceTest` mendokumentasikan tanpa menyetujui | Wave 1b |
| 2 | Auto-reject di kode warisan fork vs BR-REV-07/BR-REV-14 | ADR 0006 sudah memutuskan arah; skenario harus menguncinya | Wave 2 |
| 3 | Enum `SubmissionStatus` frontend 5 nilai vs state backend 8 nilai. **Dikonfirmasi wireframe:** `REJECTED`, `WITHDRAWN`, dan `RESUBMITTED` tidak pernah dirender sebagai status di satu pun dari 57 frame — UX ketiganya belum pernah didesain | CODING_STANDARDS + penelusuran `h.statusBadge()` di seluruh `screens/` | Wave 1b |
| 4 | BR-SM-01 ditegakkan unique index parsial — perilaku user-facing saat constraint violation belum ditetapkan | Tidak terdefinisi di sumber mana pun | Wave 1b |
| 9 | **Wireframe membolehkan submit dengan anggaran melebihi batas skema.** Lihat §9.1 | `RESEARCHER-02-04/screen.js:20-21` vs BR-BUD-01/BR-SM-06 | Implementasi BR-BUD-01 (M1) |
| 10 | **Apakah BR-REV-12 berlaku untuk siklus monev?** UI monev menampilkan skor begitu reviewer submit; UI pengajuan justru menggerbangnya. Lihat §10.1 | `RESEARCHER-05-03` vs `RESEARCHER-02-03` | Implementasi BR-REV-12 (M2/M4) |
| 11 | **Lima kebutuhan bisnis belum punya layar sama sekali.** Lihat §11.1 | Penelusuran 57 frame wireframe | Desain UI mendahului M1/M4/M5 |

### 9.1 Anggaran melebihi batas skema — UI vs aturan

`RESEARCHER-02-04` (wizard langkah 5) memuat kalimat:

> "Jika total melebihi batas maksimum skema, muncul peringatan — **Anda tetap dapat
> melanjutkan submit**."

BR-BUD-01 (kanonik) dan BR-SM-06 (cermin) berbunyi sebaliknya: total **tidak boleh**
melebihi `scheme.max_budget`. Ini bukan perbedaan penekanan — UI menurunkan batas keras
finansial menjadi peringatan lunak.

Rekomendasi: **aturan menang, UI menyesuaikan.** Anggaran menyangkut kepatuhan dana dan
salahnya baru ketahuan saat audit; itu kelas kesalahan yang paling mahal ditemukan
belakangan. Skenario `[BUD-LIMIT-01]` sudah menguncinya sebagai penolakan.

Kalau LPPM justru menghendaki submit tetap boleh dengan anggaran berlebih dan Operator
yang mengadili, itu keputusan sah — tapi berarti **BR-BUD-01 yang diubah**, bukan
skenarionya dilunakkan diam-diam. Kalimat di wireframe dihapus atau diganti mengikuti
keputusan itu.

### 10.1 Keterlihatan skor pada siklus monev

Dua layar menerapkan gerbang berbeda untuk hal yang sama:

| Layar | Perilaku |
| ----- | -------- |
| `RESEARCHER-02-03` (pengajuan) | "Hasil review akan tampil setelah proses review selesai dan pengajuan dinyatakan Perlu Revisi, Disetujui, atau Ditolak" — **sesuai BR-REV-12** |
| `RESEARCHER-05-03` (monev) | Menampilkan "Skor Kemajuan 78/100" dengan catatan "Evaluasi tampil setelah reviewer submit" — **lebih awal** |

BR-REV-12 menyebut "submission", dan laporan monev **adalah** FormSubmission (child).
Dibaca apa adanya, gerbang yang sama berlaku. Tapi BC Monev tidak pernah menyatakannya
ulang, dan konteksnya berbeda: monev bersifat formatif — umpan balik yang datang
terlambat kehilangan gunanya untuk kegiatan yang masih berjalan.

Rekomendasi: **monev dikecualikan secara eksplisit**, dengan menambahkan aturan di BC
Monev — bukan dengan membiarkan BR-REV-12 ditafsir bebas. Alasannya kebutuhan bisnis:
tujuan monev adalah mengoreksi arah kegiatan sebelum selesai, dan itu mustahil kalau
catatan reviewer baru terlihat setelah siklusnya berakhir.

Sampai diputuskan, M4 **tidak** mengalokasikan skenario keterlihatan skor monev.

### 11.1 Kebutuhan bisnis tanpa layar

Penelusuran 57 frame menemukan lima kebutuhan yang aturannya sudah ada di DDD tapi
antarmukanya belum pernah didesain:

| Kebutuhan | Aturan | Kondisi wireframe |
| --------- | ------ | ----------------- |
| Target Luaran (`SubmissionTargetOutput`) | BR-SM-14, BR-SM-15 | **Nol layar.** Tidak ada langkah wizard, tidak ada tab di detail. `RESEARCHER-06-*` bernama "Luaran" tapi itu `ResearchOutput` (realisasi) — entitas lain |
| Anggota penelitian + cek COI | BR-SM-08 | **Nol layar.** Tab "Anggota" terdaftar di `RESEARCHER-02-03` tapi isinya tidak pernah dirender |
| Notifikasi in-app + tandai dibaca | BR-NOT-01, BR-NOT-04 | **Nol layar.** Tidak ada notification center; hanya kartu KPI "4 belum dibaca" tanpa tautan |
| Wizard langkah 2, 3, 4, 6, 7 | BR-SM-02, BR-SM-14, BR-FE-11 | Label ketujuh langkah ada, tapi hanya langkah 1 dan 5 punya konten |
| Status override terlihat researcher | BR-FE-16 | Tidak ada layar yang menampilkan badge status override |

Ini **bukan** kontradiksi — tidak ada yang saling bertentangan. Ini pekerjaan desain yang
belum dilakukan, dan ia mendahului implementasi: skenario `[SM-TARGET-01..09]`,
`[SM-MEMBER-01..03]`, dan `[NOT-INAPP-01..03]` tidak bisa dikerjakan sampai layarnya ada.

Target Luaran yang paling mendesak — ia entitas baru hasil keputusan #5, jadi memang
belum pernah masuk desain sama sekali.

## Terselesaikan

| # | Perkara | Keputusan | Tanggal |
| - | ------- | --------- | ------- |
| 5 | BR-BUD-06 menyebut "period config" yang tidak terdefinisi; entitas `SubmissionOuter` ternyata luaran yang dijanjikan, bukan data anggaran | Pindah ke BC Submission sebagai `SubmissionTargetOutput`; BR-BUD-06 dicabut, diganti BR-SM-14 dan BR-SM-15; pembatas tipe memakai `scheme.rules.allowed_output_types` yang sudah ada — tidak ada config baru. Target tetap bisa diubah pasca-APPROVED dengan audit trail wajib; hanya `submitted_by` yang boleh mengubah. Jejak lengkap: spec §8.1 | 2026-09-03 |
| 6 | Arah penelusuran org tree di BR-FE-02 terbalik di DDD — Form ber-scope luas justru tak terlihat oleh peneliti di prodi | **Arah anak-menjangkau-leluhur disetujui.** `FormAccessControl.organization_id` harus ancestor-or-self dari organisasi user. Ditulis sebagai [ADR 0013](../../adr/0013-form-access-control-matches-ancestor-organizations.md); pseudocode, CTE, dan bunyi BR-FE-02 di DDD sudah dikoreksi. Lihat §6.1 | 2026-09-04 |
| 7 | "Active Submission" di BR-SCH-01 tidak terdefinisi — apakah APPROVED menghalangi penonaktifan skema? | **APPROVED dihitung aktif.** Active = `status NOT IN ('rejected', 'withdrawn')` — predikat yang sudah dipakai index unik BR-SM-01. Definisi masuk `02_ubiquitous_language.md`, bunyi BR-SCH-01 diperjelas, dan `[SCH-RULE-15]` mengunci perilakunya. Lihat §7.1 | 2026-09-04 |
| 8 | Dua skenario menguji aturan yang tidak punya nomor BR | **Keduanya diberi nomor.** BR-FE-16 (visibility `reason` override) dan BR-SCH-10 (skema tidak aktif tidak ditawarkan); tag `[FE-OVERRIDE-09]` dan `[SCH-RULE-14]` dipindahkan ke nomor barunya. Lihat §8.1 | 2026-09-04 |

### 6.1 Arah penelusuran org tree — jejak keputusan

Pseudocode `canAccessForm` di
[`generic/02_identity_access.md`](../ddd/generic/02_identity_access.md) semula menghitung
`subtreeIds($user->profile->organization_id)` — yaitu **descendant dari organisasi
user** — lalu memeriksa apakah `FormAccessControl.organization_id` ada di dalamnya.
Dibaca apa adanya, itu berarti **induk menjangkau anak**: hanya user yang organisasinya
merupakan leluhur dari organisasi ACL yang mendapat akses. Frasa BR-FE-02 ("org subtree
user") mewarisi bacaan yang sama, dan `app/Models/FormAccessControl.php`
mengimplementasikan persis itu.

Konsekuensinya membuat fitur tidak berfungsi. Untuk Form se-institut yang di-scope ke
node akar `ITK`:

| Organisasi user | `subtreeIds(user)` | `ITK` ada di dalamnya? |
| --------------- | ------------------ | ---------------------- |
| ITK             | {ITK, FTI, Informatika, …} | ya → akses |
| FTI             | {FTI, Informatika} | **tidak → ditolak** |
| Informatika     | {Informatika}      | **tidak → ditolak** |

Form yang di-scope luas justru hanya terlihat oleh user yang organisasinya persis node
akar, sementara seluruh peneliti — yang duduk di prodi/departemen — terkunci di luar.

**Keputusan (2026-09-04): arah anak-menjangkau-leluhur.** ACL org harus
ancestor-or-self dari organisasi user, ditelusuri naik lewat
`Organization::ancestorIds()`. Skenario `[FE-ACCESS-02]` dan `[FE-ACCESS-03]` sudah
mengunci arah ini sejak Wave 1a. `subtreeIds()` tidak dihapus — ia tetap benar untuk
pelaporan agregat, hanya keliru dipakai di jalur access control.

**Sudah diimplementasikan (2026-09-04).** `Organization::ancestorIds()` ditambahkan,
`FormAccessControl::organizationAncestorsFor()` menggantikan `organizationSubtreeFor()`,
dan pengecekan kembar di `UserFormController` ikut dikoreksi. Invalidasi cache berbalik
arah sesuai ADR 0013: cache `org_ancestors_{id}` sebuah node dibuang saat leluhurnya
berpindah atau dinonaktifkan. Skenario `[FE-ACCESS-01]`…`[FE-ACCESS-06]` sudah punya
test dan keluar dari `pending.txt`; `[FE-ACCESS-07]`/`[FE-ACCESS-08]` masih pending
karena mengatur BR-FE-01 (periode force closed), bukan arah org tree.

### 7.1 Apa itu "active Submission" untuk BR-SCH-01

BR-SCH-01 berbunyi "Scheme tidak bisa di-deactivate jika ada **active** Submission yang
menggunakannya", dan flowchart di [`supporting/04_scheme.md`](../ddd/supporting/04_scheme.md)
mengulang istilah yang sama tanpa mendefinisikannya. Dua status jelas mati: `REJECTED`
dan `WITHDRAWN`. Yang diperdebatkan `APPROVED`:

| Bacaan | Alasan | Akibat kalau dipakai |
| ------ | ------ | -------------------- |
| APPROVED **aktif** | Pengajuan yang disetujui adalah kegiatan yang sedang berjalan — laporan monev (child submission) masih lahir darinya, dan skema masih memasok `max_budget` serta `duration_months` untuk kegiatan itu | Skema baru bisa dinonaktifkan setelah seluruh kegiatan selesai; penonaktifan jadi jarang tapi aman |
| APPROVED **tidak aktif** | BR-SM-05 menggolongkan APPROVED bersama REJECTED dan WITHDRAWN sebagai status yang "tidak bisa diubah kembali" — yaitu terminal | Skema bisa dinonaktifkan sementara kegiatan masih berjalan, dan aturan yang mengikat kegiatan itu hilang dari daftar aktif |

**Keputusan (2026-09-04): APPROVED dihitung aktif.** "Terminal" di BR-SM-05 berbicara
soal *mutabilitas record*, bukan soal apakah kegiatannya masih berjalan.

Yang menutup perkara ini bukan sekadar argumen, melainkan preseden yang sudah berjalan:
index unik BR-SM-01 memfilter dengan `status NOT IN ('rejected', 'withdrawn')`
([ADR 0005](../../adr/0005-scheme-selector-writes-to-column.md), migrasi
`2026_08_17_000003_add_submission_uniqueness_indexes.php`). Sistem ini **sudah** punya
satu definisi "aktif" di lapisan paling keras yang ia miliki; BR-SCH-01 memakai predikat
yang sama persis alih-alih melahirkan definisi kedua yang bisa menyimpang.

`[SCH-RULE-06]` menguji sisi yang tidak menghalangi (REJECTED, WITHDRAWN);
`[SCH-RULE-15]` menguji sisi yang menghalangi (APPROVED).

### 8.1 Skenario yang menempel pada BR yang tidak menyatakannya

Dua skenario Wave 1a menguji aturan nyata yang tidak punya nomor BR sendiri, sehingga
terpaksa menumpang BR tetangga — tag yang menyesatkan saat dibaca sebagai
ketertelusuran. Guard assertion 2 mewajibkan setiap skenario punya minimal satu tag
`@BR-`, jadi tag itu tidak bisa sekadar dilepas; obatnya memberi nomor.

| Skenario | Semula | Sekarang | Aturan yang dinomori |
| -------- | ------ | -------- | -------------------- |
| `[FE-OVERRIDE-09]` | `@BR-FE-09` (reason wajib diisi) | `@BR-FE-16` | Researcher melihat *status* override tapi tidak `reason`-nya — semula hanya catatan prosa di `generic/01_form_engine.md` |
| `[SCH-RULE-14]` | `@BR-SCH-01` (penjagaan saat deactivate) | `@BR-SCH-10` | Scheme `is_active = false` tidak ditawarkan ke pengajuan baru — semula hanya tersirat dari semantik kolom |

**Keputusan (2026-09-04): kedua aturan diberi nomor BR** dan barisnya ditambahkan ke
tabel Business Rules di DDD. Inventaris naik dari 101 ke 103 BR, 94 di antaranya
bersifat perilaku. BR-FE-09 dan BR-SCH-01 tetap tercakup jujur oleh skenario yang
memang membuktikannya (`[FE-OVERRIDE-06]`, `[SCH-RULE-04]`/`[SCH-RULE-05]`/`[SCH-RULE-06]`).
