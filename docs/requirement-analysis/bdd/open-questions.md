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

## Terselesaikan

| # | Perkara | Keputusan | Tanggal |
| - | ------- | --------- | ------- |
| 5 | BR-BUD-06 menyebut "period config" yang tidak terdefinisi; entitas `SubmissionOuter` ternyata luaran yang dijanjikan, bukan data anggaran | Pindah ke BC Submission sebagai `SubmissionTargetOutput`; BR-BUD-06 dicabut, diganti BR-SM-14 dan BR-SM-15; pembatas tipe memakai `scheme.rules.allowed_output_types` yang sudah ada — tidak ada config baru. Target tetap bisa diubah pasca-APPROVED dengan audit trail wajib; hanya `submitted_by` yang boleh mengubah. Jejak lengkap: spec §8.1 | 2026-09-03 |
