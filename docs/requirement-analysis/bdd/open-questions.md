# Register Kontradiksi & Pertanyaan Terbuka

Kontradiksi antara DDD, wireframe, dan kode legacy **tidak diputuskan sepihak** saat
menulis skenario. Ia dicatat di sini dengan opsi dan rekomendasi, diputuskan pemilik
produk, baru skenarionya mengunci keputusan itu.

## Terbuka

| # | Kontradiksi | Sumber | Memblokir |
| - | ----------- | ------ | --------- |
| 1 | `Gate::before` memberi Super Admin keempat ability `FormSubmissionPolicy` tanpa syarat, sementara `SubmitFormSubmission::run()` menegakkan BR-SM-04 sendiri | `SuperAdminSubmitDivergenceTest` mendokumentasikan tanpa menyetujui | Wave 1b |
| 2 | Auto-reject di kode warisan fork vs BR-REV-07/BR-REV-14 | ADR 0006 sudah memutuskan arah; skenario harus menguncinya | Wave 2 |
| 3 | Enum `SubmissionStatus` frontend 5 nilai vs state backend 8 nilai | Utang tercatat di CODING_STANDARDS | Wave 1b |
| 4 | BR-SM-01 ditegakkan unique index parsial — perilaku user-facing saat constraint violation belum ditetapkan | Tidak terdefinisi di sumber mana pun | Wave 1b |
| 6 | **Arah penelusuran org tree di BR-FE-02 terbalik di DDD.** Lihat §6.1 di bawah | `generic/02_identity_access.md` (pseudocode `canAccessForm`) vs `[FE-ACCESS-02]`/`[FE-ACCESS-03]` | Implementasi BR-FE-02 (M0/M1) |
| 7 | **"Active Submission" di BR-SCH-01 tidak terdefinisi.** Apakah submission APPROVED menghalangi penonaktifan skema? Lihat §7.1 | `supporting/04_scheme.md` (BR-SCH-01 + flowchart "Ada active Submission?") | Implementasi BR-SCH-01 (M0) |
| 8 | **Dua skenario menguji aturan yang tidak punya nomor BR.** Lihat §8.1 | `[FE-OVERRIDE-09]` vs BR-FE-09; `[SCH-RULE-14]` vs BR-SCH-01 | Tidak memblokir — kualitas register, bukan kebenaran |

### 6.1 Arah penelusuran org tree — detail

Ditemukan saat menulis Wave 1a. Pseudocode `canAccessForm` di
[`generic/02_identity_access.md`](../ddd/generic/02_identity_access.md) menghitung
`subtreeIds($user->profile->organization_id)` — yaitu **descendant dari organisasi
user** — lalu memeriksa apakah `FormAccessControl.organization_id` ada di dalamnya.
Dibaca apa adanya, itu berarti **induk menjangkau anak**: hanya user yang organisasinya
merupakan leluhur dari organisasi ACL yang mendapat akses. Frasa BR-FE-02 ("org subtree
user") mewarisi bacaan yang sama.

Konsekuensinya membuat fitur tidak berfungsi. Untuk Form se-institut yang di-scope ke
node akar `ITK`:

| Organisasi user | `subtreeIds(user)` | `ITK` ada di dalamnya? |
| --------------- | ------------------ | ---------------------- |
| ITK             | {ITK, FTI, Informatika, …} | ya → akses |
| FTI             | {FTI, Informatika} | **tidak → ditolak** |
| Informatika     | {Informatika}      | **tidak → ditolak** |

Artinya Form yang di-scope luas justru hanya terlihat oleh user yang organisasinya
persis node akar, sementara seluruh peneliti — yang duduk di prodi/departemen —
terkunci di luar. Scoping luas menjadi mustahil.

Arah sebaliknya (**anak menjangkau leluhur**: ACL org harus merupakan
ancestor-or-self dari organisasi user) menghasilkan perilaku yang masuk akal — Form
ber-scope `ITK` terlihat oleh semua orang di bawah ITK, Form ber-scope `FTI` terlihat
oleh FTI beserta prodi di bawahnya.

**Skenario `[FE-ACCESS-02]` dan `[FE-ACCESS-03]` mengunci arah anak-menjangkau-leluhur**,
karena itu satu-satunya bacaan yang membuat aturan ini berguna. Dokumen DDD **tidak
diubah** — ia dokumen kesepakatan, dan koreksinya perlu persetujuan pemilik produk.

Yang perlu diputuskan: apakah arah anak-menjangkau-leluhur disetujui. Kalau ya,
`canAccessForm`, CTE rekursifnya, dan bunyi BR-FE-02 perlu dikoreksi bersama —
kemungkinan lewat ADR, karena ini mengubah makna "subtree" untuk seluruh access control.

### 7.1 Apa itu "active Submission" untuk BR-SCH-01

BR-SCH-01 berbunyi "Scheme tidak bisa di-deactivate jika ada **active** Submission yang
menggunakannya", dan flowchart di [`supporting/04_scheme.md`](../ddd/supporting/04_scheme.md)
mengulang istilah yang sama tanpa mendefinisikannya. Delapan status submission ada di
[`02_ubiquitous_language.md`](../ddd/02_ubiquitous_language.md); tidak ada satu pun tempat
yang membagi kedelapannya menjadi "active" dan "tidak".

Dua status jelas mati: `REJECTED` dan `WITHDRAWN`. Yang diperdebatkan `APPROVED`:

| Bacaan | Alasan | Akibat kalau dipakai |
| ------ | ------ | -------------------- |
| APPROVED **aktif** | Pengajuan yang disetujui adalah kegiatan yang sedang berjalan — laporan monev (child submission) masih lahir darinya, dan skema masih memasok `max_budget` serta `duration_months` untuk kegiatan itu | Skema baru bisa dinonaktifkan setelah seluruh kegiatan selesai; penonaktifan jadi jarang tapi aman |
| APPROVED **tidak aktif** | BR-SM-05 menggolongkan APPROVED bersama REJECTED dan WITHDRAWN sebagai status yang "tidak bisa diubah kembali" — yaitu terminal | Skema bisa dinonaktifkan sementara kegiatan masih berjalan, dan aturan yang mengikat kegiatan itu hilang dari daftar aktif |

Rekomendasi: **APPROVED dihitung aktif**. "Terminal" di BR-SM-05 berbicara soal
*mutabilitas record*, bukan soal apakah kegiatannya masih berjalan; dan menonaktifkan
skema di bawah kaki penelitian yang sedang berlangsung adalah kelas kesalahan yang
mahal untuk ditemukan belakangan.

Sampai diputuskan, `[SCH-RULE-06]` sengaja **hanya memakai `REJECTED` dan `WITHDRAWN`** —
bagian yang tidak diperdebatkan. Brief Task 10 semula menulis "APPROVED dan REJECTED",
yang berarti mengunci bacaan kedua secara diam-diam; itu persis yang dilarang §8 spec.
Kalau pemilik produk memilih bacaan kedua, `[SCH-RULE-06]` tinggal ditambah barisnya.

### 8.1 Skenario yang menempel pada BR yang tidak menyatakannya

Dua skenario Wave 1a menguji aturan nyata yang **tidak punya nomor BR sendiri**, sehingga
terpaksa menumpang BR tetangga. Guard assertion 2 mewajibkan setiap skenario punya minimal
satu tag `@BR-`, jadi tag itu tidak bisa sekadar dilepas.

| Skenario | Ditandai | Yang sebenarnya diuji | Sumber aturannya |
| -------- | -------- | --------------------- | ---------------- |
| `[FE-OVERRIDE-09]` | `@BR-FE-09` | Researcher melihat *status* override tapi tidak melihat `reason` | Catatan prosa "FormSubmissionOverride — Visibility ke Researcher" di `generic/01_form_engine.md` — tanpa nomor |
| `[SCH-RULE-14]` | `@BR-SCH-01` | Skema tidak aktif tidak ditawarkan ke pengajuan baru | Tersirat dari semantik kolom `is_active`; BR-SCH-01 sendiri hanya mengatur *penjagaan saat deactivate* |

Ini **bukan hijau palsu**: BR-FE-09 tetap dibuktikan jujur oleh `[FE-OVERRIDE-06]` (alasan
kosong ditolak) dan BR-SCH-01 oleh `[SCH-RULE-04]`/`[SCH-RULE-05]`, jadi assertion 7 tidak
tertipu. Yang hilang adalah ketertelusuran: membaca tag saja akan menyesatkan.

Yang perlu diputuskan: apakah kedua aturan itu diberi nomor BR baru di DDD (mis. BR-FE-16
untuk visibility override, BR-SCH-10 untuk ketersediaan skema tidak aktif). Kalau ya,
tag skenarionya dipindahkan ke nomor baru dan entri ini ditutup. Menyunting DDD butuh
persetujuan pemilik produk — di luar wewenang penulisan skenario.

## Terselesaikan

| # | Perkara | Keputusan | Tanggal |
| - | ------- | --------- | ------- |
| 5 | BR-BUD-06 menyebut "period config" yang tidak terdefinisi; entitas `SubmissionOuter` ternyata luaran yang dijanjikan, bukan data anggaran | Pindah ke BC Submission sebagai `SubmissionTargetOutput`; BR-BUD-06 dicabut, diganti BR-SM-14 dan BR-SM-15; pembatas tipe memakai `scheme.rules.allowed_output_types` yang sudah ada — tidak ada config baru. Target tetap bisa diubah pasca-APPROVED dengan audit trail wajib; hanya `submitted_by` yang boleh mengubah. Jejak lengkap: spec §8.1 | 2026-09-03 |
