# M1 — Submission & Budget

**22 BR perilaku · 77 skenario · 10 berkas `.feature`**

Inti aplikasi. Semua milestone lain menggantung di sini: Review menilai submission,
Monev lahir dari submission yang APPROVED, Luaran melaporkan hasilnya.

Sumber: [`core/01_submission.md`](../../requirement-analysis/ddd/core/01_submission.md),
[`supporting/01_budget.md`](../../requirement-analysis/ddd/supporting/01_budget.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `core/submission/wizard.feature` | `SM-WIZARD-01..09` | BR-SM-03 (parsial), jangkar temporal |
| `core/submission/submit.feature` | `SM-SUBMIT-01..17` | BR-SM-01, BR-SM-02, BR-SM-04, BR-SM-16 |
| `core/submission/state.feature` | `SM-STATE-01..05` | BR-SM-03, BR-SM-05 |
| `core/submission/access.feature` | `SM-ACCESS-01..06` | BR-SM-07 |
| `core/submission/members.feature` | `SM-MEMBER-01..03` | BR-SM-08 |
| `core/submission/ownership.feature` | `SM-OWNER-01..04` | BR-SM-09 (+BR-IAM-10) |
| `core/submission/withdrawal.feature` | `SM-WITHDRAW-01..04` | BR-SM-11 |
| `core/submission/archival.feature` | `SM-ARCHIVE-01..06` | BR-SM-10, BR-SM-13 |
| `core/submission/target_output.feature` | `SM-TARGET-01..09` | BR-SM-14, BR-SM-15 |
| `supporting/budget.feature` | `BUD-LIMIT/LOCK/ITEM/AUDIT/WHO` | BR-BUD-01, BR-BUD-02, BR-BUD-03, BR-BUD-04, BR-BUD-05, BR-BUD-08, BR-BUD-09 (+BR-SM-06) |

Semua skenario bertag `@M1`.

**BR-SM-12 tidak muncul di tabel** karena ia cermin BR-FE-14 dan sudah dibuktikan
`[FE-LOCK-01..04]` di Wave 1a — keempat skenario itu kini membawa tag `@BR-SM-12` juga.
Nol skenario baru, nol implementasi baru: optimistic locking berlaku untuk semua
`FormSubmission`, termasuk submission pengajuan.

---

## 2. Wizard tujuh langkah

Baris `form_submissions` lahir di **langkah 1**, bukan di akhir — dipaksa BR-FE-11:
jangkar temporal harus ada saat researcher mulai mengisi. Validasi per langkah bersifat
parsial; Temporal Field Binding penuh baru jalan di langkah 7.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-WIZARD-01` | happy | Langkah 1 membuat baris DRAFT beserta `created_at` sebagai jangkar temporal | RESEARCHER-02-02 |
| `SM-WIZARD-02` | happy | Keluar di langkah 3 lalu kembali — isian langkah 2 tetap ada | RESEARCHER-02-06, RESEARCHER-02-07 |
| `SM-WIZARD-03` | boundary | Validasi langkah 2 hanya menyentuh field langkah 2, bukan seluruh form | RESEARCHER-02-06 |
| `SM-WIZARD-04` | denial | Pada Form **yang punya** `scheme_selector`, langkah 5 ditolak selama `scheme_id` kosong — batas anggaran berasal dari skema | RESEARCHER-02-07 |
| `SM-WIZARD-09` | boundary | Pada Form **tanpa** `scheme_selector`, langkah 5 terbuka langsung — skema opsional per Form (BR-SCH-04) | RESEARCHER-02-02 |
| `SM-WIZARD-05` | happy | Langkah 3 mengisi `scheme_id` dan `trl_id` ke kolom, bukan ke `form_field_responses` | RESEARCHER-02-07 |
| `SM-WIZARD-06` | boundary | Langkah 7 menjalankan Temporal Field Binding penuh dan menampilkan error per field | RESEARCHER-02-10 |
| `SM-WIZARD-07` | happy | Draft bisa dilanjutkan di sesi berikutnya dari langkah terakhir | RESEARCHER-02-01, RESEARCHER-02-04 |
| `SM-WIZARD-08` | denial | Melompat ke langkah 7 tanpa melewati langkah wajib ditolak | RESEARCHER-02-09, RESEARCHER-02-10 |

`SM-WIZARD-09` ada karena DDD berbunyi dua hal yang mudah dibaca bertabrakan:
"`scheme_id` wajib terisi sebelum langkah 5" (`core/01_submission.md`) dan "Scheme
sepenuhnya opsional per Form" (BR-SCH-04). Keduanya benar — syarat itu hanya berlaku
kalau Form-nya memang punya `scheme_selector`. Tanpa `SM-WIZARD-09`, implementasi yang
membaca kalimat pertama saja akan memblokir anggaran untuk seluruh Form tanpa skema,
dan itu bertentangan dengan `[BUD-LIMIT-03]` di §10.

> **Perlu keputusan.** DDD menyebut `last_completed_step` di struktur artefak baseline
> tapi tidak mendefinisikan apakah researcher boleh melompat mundur-maju bebas.
> `SM-WIZARD-08` mengasumsikan urutan mengikat ke depan, bebas ke belakang. Kalau
> ternyata bebas dua arah, skenario ini yang berubah. → register.

---

## 3. Submit dan keunikan

### BR-SM-01 — satu submission aktif per periode per skema

"Active" berarti **sudah disubmit**. DRAFT tidak dihitung, karena `scheme_id` masih
kosong sepanjang langkah 1–2 dan researcher berhak menyiapkan pengajuan ke beberapa
skema sekaligus.

| ID | Kelas | Yang dibuktikan |
| -- | ----- | --------------- |
| `SM-SUBMIT-01` | happy | Submit pertama ke skema A pada periode berjalan berhasil |
| `SM-SUBMIT-02` | denial | Submit kedua ke skema A pada periode yang sama ditolak |
| `SM-SUBMIT-03` | boundary | Dua DRAFT ke skema berbeda boleh hidup bersamaan |
| `SM-SUBMIT-04` | happy | Submit ke skema B tetap boleh meski skema A sudah disubmit |
| `SM-SUBMIT-05` | boundary | Setelah REJECTED, submit ulang ke skema yang sama boleh |
| `SM-SUBMIT-06` | boundary | Submission tanpa skema tidak saling menghalangi |

### Saat keunikan dilanggar — BR-SM-16

| ID | Kelas | Yang dibuktikan |
| -- | ----- | --------------- |
| `SM-SUBMIT-15` | denial | Submit kedua ditolak dengan pesan validasi yang **menyebut pengajuan aktif yang sudah ada** beserta tautannya — bukan galat database mentah |
| `SM-SUBMIT-16` | boundary | Draft yang gagal disubmit **tetap DRAFT dan tetap bisa disunting** — tidak dihapus, tidak berubah status |
| `SM-SUBMIT-17` | boundary | Kalau index database yang menangkap lebih dulu (lomba-balap dua tab), pesannya sama seperti `SM-SUBMIT-15` |

`SM-SUBMIT-16` menutup pertanyaan yang paling menyakitkan bagi penggunanya: pekerjaan
mereka tidak hilang. `SM-SUBMIT-17` menjaga jaring pengaman database tetap ada tanpa
memuntahkan galat teknis ke layar. Menuliskannya sekarang
> berarti mengarang perilaku produk.

### BR-SM-02 — proposal PDF wajib

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-SUBMIT-07` | denial | Submit tanpa proposal PDF ditolak dengan pesan per field | RESEARCHER-02-09 |
| `SM-SUBMIT-08` | happy | Submit dengan proposal PDF lolos ke SUBMITTED | RESEARCHER-02-10 |

### BR-SM-04 — hanya `submitted_by` yang submit dan resubmit

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-SUBMIT-09` | happy | `submitted_by` submit berhasil | RESEARCHER-02-10 |
| `SM-SUBMIT-10` | denial | Co-Investigator submit ditolak walau bisa menyunting isi | RESEARCHER-02-03 |
| `SM-SUBMIT-11` | denial | Member submit ditolak | RESEARCHER-02-03 |
| `SM-SUBMIT-12` | happy | `submitted_by` resubmit dari NEEDS_REVISION | RESEARCHER-02-05 |

| `SM-SUBMIT-13` | denial | Super Admin yang bukan `submitted_by` **tidak bisa** submit — Policy dan Action berkata sama |
| `SM-SUBMIT-14` | happy | Super Admin tetap bisa melihat, menyunting, dan menarik submission orang lain |

`SM-SUBMIT-13` mengunci [ADR 0015](../../adr/0015-gate-before-never-bypasses-domain-invariants.md):
submit adalah tindakan `submitted_by` mengikat dirinya sendiri, jadi ia invarian domain —
bukan kewenangan yang bisa dilewati. Kalau admin memang harus mengajukan untuk orang
lain, jalurnya transfer kepemilikan (`[SM-OWNER-01]`), yang membuat `submitted_by` tetap
jujur. `SM-SUBMIT-14` menjaga pengecualian ini tetap sempit — ability lain tetap
di-bypass.

---

## 4. State machine dan penguncian

| ID | Kelas | Yang dibuktikan | BR |
| -- | ----- | --------------- | -- |
| `SM-STATE-01` | happy | DRAFT → SUBMITTED lewat `transitionTo()`, bukan `update(['status' => …])` | BR-SM-03 |
| `SM-STATE-02` | denial | **Outline** — lompatan terlarang ditolak: DRAFT→APPROVED, DRAFT→RESUBMITTED, APPROVED→DRAFT, REJECTED→APPROVED, REJECTED→RESUBMITTED, WITHDRAWN→APPROVED | BR-SM-03 |
| `SM-STATE-03` | happy | NEEDS_REVISION → RESUBMITTED → UNDER_REVIEW (transisi kedua otomatis) | BR-SM-03 |
| `SM-STATE-04` | denial | **Outline** — menyunting isi submission di APPROVED, REJECTED, WITHDRAWN ditolak | BR-SM-05 |
| `SM-STATE-05` | happy | Operator override tetap bisa mengubah submission di status terminal | BR-SM-05 |

> **Catatan register #3.** Enum `SubmissionStatus` di frontend hanya 5 nilai, backend
> 8. `SM-STATE-02` memakai delapan nilai backend — itu kebenaran domainnya. Kalau
> frontend tidak menyusul, layar akan menampilkan status yang tidak dikenalnya.

---

## 5. Akses tingkat submission — BR-SM-07

Lapis kedua di luar `FormAccessControl`. Mengecek akses Form saja **tidak cukup**:
Form-nya boleh terbuka untuk seluruh fakultas, tapi isi submission hanya untuk
pemilik dan anggotanya.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-ACCESS-01` | happy | `submitted_by` melihat submission-nya | RESEARCHER-02-03 |
| `SM-ACCESS-02` | happy | ResearchMember melihat submission yang ia ikuti | RESEARCHER-02-03 |
| `SM-ACCESS-03` | denial | Researcher lain di organisasi yang sama ditolak | RESEARCHER-02-01 |
| `SM-ACCESS-04` | happy | Operator melihat semua lewat `submissions.view-all` | OPERATOR-02-01, OPERATOR-02-02 |
| `SM-ACCESS-05` | happy | Reviewer yang ditugaskan melihat submission itu | REVIEWER-03-01 |
| `SM-ACCESS-06` | denial | Reviewer yang **tidak** ditugaskan ditolak | REVIEWER-03-01 |

---

## 6. Anggota penelitian — BR-SM-08

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-MEMBER-01` | denial | Menambah user yang sudah jadi `SubmissionReviewer` submission itu ditolak — konflik kepentingan | RESEARCHER-02-08 |
| `SM-MEMBER-02` | happy | Menambah Co-Investigator dan member biasa | RESEARCHER-02-08 |
| `SM-MEMBER-03` | boundary | Jumlah anggota dibatasi `scheme.max_members` | RESEARCHER-02-08 |

Arah sebaliknya — reviewer yang sudah jadi anggota — dibuktikan `[REV-ASSIGN-07]` di
M2. Keduanya sisi dari satu mutual block; keduanya harus ada.

---

## 7. Transfer kepemilikan — BR-IAM-10, dicerminkan BR-SM-09

Skenario di bawah bertag **`@BR-SM-09 @BR-IAM-10`** sekaligus. Kanoniknya BR-IAM-10 —
ia yang menyatakan urutannya ("sebelum deaktivasi dikonfirmasi"), dan urutan itulah yang
sebenarnya diimplementasikan. Satu Action, dipanggil dari layar deaktivasi user maupun
dari layar transfer kepemilikan.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-OWNER-01` | happy | Operator memindahkan `submitted_by` ke Co-Investigator aktif | OPERATOR-02-04 |
| `SM-OWNER-02` | denial | Transfer ke member biasa ditolak — hanya Co-Investigator | OPERATOR-02-04 |
| `SM-OWNER-03` | denial | Menonaktifkan lead researcher yang masih punya submission aktif ditolak sebelum transfer | OPERATOR-08-03 |
| `SM-OWNER-04` | boundary | Tidak ada Co-Investigator aktif — deaktivasi tetap diblokir, Operator diberi tahu alasannya | OPERATOR-08-03 |

---

## 8. Penarikan dan pengarsipan

### BR-SM-11 — withdrawal

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `SM-WITHDRAW-01` | happy | Operator menarik submission APPROVED lewat override | OPERATOR-02-06 |
| `SM-WITHDRAW-02` | denial | Researcher menarik sendiri ditolak — bukan self-service | RESEARCHER-02-03 |
| `SM-WITHDRAW-03` | boundary | Menarik submission yang belum APPROVED ditolak | OPERATOR-02-06 |
| `SM-WITHDRAW-04` | boundary | Setelah WITHDRAWN, seluruh data historis tetap ada dan terbaca | OPERATOR-02-02 |

### BR-SM-10 dan BR-SM-13 — arsip

`isArchived()` adalah computed property, bukan kolom: `is_submitted = false` **DAN**
period sudah tutup (`is_force_closed` **ATAU** semua `SubmissionDate` terlewat).

| ID | Kelas | Yang dibuktikan |
| -- | ----- | --------------- |
| `SM-ARCHIVE-01` | happy | Period tutup — DRAFT tidak dihapus, berubah jadi read-only |
| `SM-ARCHIVE-02` | happy | Pemilik DRAFT menerima notifikasi saat period tutup |
| `SM-ARCHIVE-03` | denial | Menyunting DRAFT yang sudah archived ditolak |
| `SM-ARCHIVE-04` | boundary | `is_submitted = false` + period tutup → archived |
| `SM-ARCHIVE-05` | boundary | `is_submitted = true` + period tutup → **bukan** archived |
| `SM-ARCHIVE-06` | boundary | `is_force_closed` memicu archived meski tanggal belum terlewat |

`SM-ARCHIVE-04..06` sengaja menguji ketiga kombinasi, karena inilah satu-satunya
tempat "archived" didefinisikan — salah baca di sini menyembunyikan draft yang masih
boleh dikerjakan.

---

## 9. Target luaran — BR-SM-14, BR-SM-15

Target luaran = luaran yang **dijanjikan**. Bedakan dari Research Output (M4) yang
merupakan luaran **terealisasi**. Satu taksonomi, dua titik lifecycle.

| ID | Kelas | Yang dibuktikan |
| -- | ----- | --------------- |
| `SM-TARGET-01` | happy | Memilih tipe luaran yang terdaftar di `output_type_definitions` (frame RESEARCHER-02-07) |
| `SM-TARGET-02` | denial | Tipe di luar `output_type_definitions` ditolak — bukan string bebas |
| `SM-TARGET-03` | denial | Tipe di luar `scheme.rules.allowed_output_types` ditolak saat submission punya skema |
| `SM-TARGET-04` | boundary | Submission tanpa skema hanya dibatasi `output_type_definitions` |
| `SM-TARGET-05` | happy | Mengubah target saat DRAFT |
| `SM-TARGET-06` | happy | Mengubah target **setelah APPROVED** lewat tab detail RESEARCHER-02-11 — target menyesuaikan jalannya kegiatan |
| `SM-TARGET-07` | denial | **Outline** — mengubah target saat REJECTED atau WITHDRAWN ditolak |
| `SM-TARGET-08` | denial | Co-Investigator mengubah target ditolak — hanya `submitted_by` |
| `SM-TARGET-09` | happy | Perubahan pasca-APPROVED tercatat di audit trail dengan nilai lama dan baru |

`SM-TARGET-06` sengaja berbeda dari anggaran (BR-BUD-02 mengunci di APPROVED). LPPM
butuh penyesuaian target selama kegiatan berjalan; kompensasinya audit trail wajib di
`SM-TARGET-09`. `SM-TARGET-08` juga sengaja berbeda dari BR-RO-07 yang membolehkan
Co-Investigator mengisi luaran realisasi — mengisi hasil bukan tindakan yang sama
dengan mengubah janji.

---

## 10. Anggaran

| ID | Kelas | Yang dibuktikan | BR |
| -- | ----- | --------------- | -- |
| `BUD-LIMIT-01` | denial | Grand total melebihi `scheme.max_budget` ditolak | BR-BUD-01 + BR-SM-06 |
| `BUD-LIMIT-02` | boundary | Grand total **tepat sama** dengan `max_budget` diterima | BR-BUD-01 + BR-SM-06 |
| `BUD-LIMIT-03` | boundary | Submission tanpa skema tidak punya batas anggaran | BR-BUD-01 |
| `BUD-LOCK-01` | denial | **Outline** — anggaran terkunci di APPROVED, REJECTED, WITHDRAWN | BR-BUD-02 |
| `BUD-LOCK-02` | happy | Anggaran tetap bisa disunting saat NEEDS_REVISION | BR-BUD-03 |
| `BUD-ITEM-01` | denial | **Outline** — `volume` ≤ 0 atau `unit_price` ≤ 0 ditolak | BR-BUD-04 |
| `BUD-ITEM-02` | happy | Line item dengan volume dan harga sah tersimpan, grand total ikut naik | BR-BUD-04 |
| `BUD-ITEM-03` | denial | `BudgetComponent` non-aktif tidak bisa dipilih untuk item baru | BR-BUD-05 |
| `BUD-ITEM-04` | boundary | Item lama yang terlanjur memakai komponen itu tetap utuh | BR-BUD-05 |
| `BUD-AUDIT-01` | happy | Perubahan line item saat NEEDS_REVISION tercatat nilai sebelum dan sesudah | BR-BUD-08 |
| `BUD-AUDIT-02` | boundary | Audit trail menyimpan siapa yang mengubah dan kapan, bukan hanya nilainya | BR-BUD-08 |

| `BUD-WHO-01` | happy | `submitted_by` menyunting line item | BR-BUD-09 |
| `BUD-WHO-02` | happy | Co-Investigator juga boleh menyunting — menyusun anggaran pekerjaan bersama | BR-BUD-09 |
| `BUD-WHO-03` | denial | `member` biasa hanya bisa membaca | BR-BUD-09 |

BR-BUD-09 menutup lubang yang ditemukan audit tiga sumbu ADR 0014: sebelum ini **tidak
ada satu pun aturan** yang menyatakan siapa boleh menyunting anggaran, padahal Target
Luaran (BR-SM-15) dan Research Output (BR-RO-07) keduanya punya. `BUD-WHO-02` sengaja
berbeda dari `[SM-TARGET-08]`: menyusun angka bersama-sama wajar, sedangkan **mengikat
diri** lewat submit tetap hanya hak ketua (BR-SM-04).

`BUD-LIMIT-02` (tepat di batas) wajib ada: `>` versus `>=` adalah kesalahan yang paling
sering lolos review dan paling sulit terlihat dari data produksi.

**Kanonik BR-BUD-01, dicerminkan BR-SM-06.** Ketiga skenario `BUD-LIMIT-*` bertag
keduanya. Satu validator, dipanggil dari langkah 5 wizard dan dari submit — bukan dua.
BR-BUD-01 yang kanonik karena ia menyebut asal angkanya (`SUM` line items), sementara
BR-SM-06 hanya menyebut "total budget".

---

## 11. Frame yang tercakup

RESEARCHER-02-01 · RESEARCHER-02-02 · RESEARCHER-02-03 · RESEARCHER-02-04 ·
RESEARCHER-02-05 · RESEARCHER-02-06 · RESEARCHER-02-07 · RESEARCHER-02-08 ·
RESEARCHER-02-09 · RESEARCHER-02-10 · RESEARCHER-02-11 · RESEARCHER-02-12 ·
OPERATOR-02-01 · OPERATOR-02-02 · OPERATOR-02-04 ·
OPERATOR-02-06 · OPERATOR-08-03 · REVIEWER-03-01

OPERATOR-02-03 (Perpanjang Tenggat) dan OPERATOR-02-05 (Tolak Manual) tercakup di M0
dan M2 — keduanya bukan aturan Submission.

---

## 12. Yang menunggu keputusan

| # | Perkara | Dampak |
| - | ------- | ------ |
| ~~register #1~~ | **Terselesaikan 2026-09-04** — ADR 0015; dikunci `[SM-SUBMIT-13/14]` |
| ~~register #3~~ | **Terselesaikan 2026-09-04** — enum frontend wajib memuat 8 nilai dan diturunkan dari kontrak Inertia (ADR 0007) |
| ~~register #4~~ | **Terselesaikan 2026-09-04** — BR-SM-16; dikunci `[SM-SUBMIT-15..17]` |
| [#16](../../requirement-analysis/bdd/open-questions.md) | Wizard boleh dilompati mundur-maju bebas? | `SM-WIZARD-08` mengasumsikan maju berurutan |
