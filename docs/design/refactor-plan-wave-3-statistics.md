# Gelombang 3 bagian 6 — Statistik yang jujur

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `80ca442`.

**Goal:** `StatController` memenuhi ambang keras dan keluar dari `quality-legacy.txt`
(`max.legacy.files` 107 → 106). Angka yang hari ini salah dibetulkan lebih dulu, masing-masing
dengan test yang merah sebelum perbaikan.

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §5 gelombang 3 ·
DDD [`supporting/05_reporting.md`](../requirement-analysis/ddd/supporting/05_reporting.md)
(BR-RPT-01 hanya baca, BR-RPT-06 query live).

**Status:** selesai (Task 1-3).

---

## Pengukuran awal

`StatController`: ukuran berkas; `getFormPhaseStats()` loc 127; `getFormSubmissionStats()` loc 70;
`getSubmissionReviewerStats()` loc 63.

## Temuan — diverifikasi di kode

**S1 — Lima endpoint JSON "testing" di produksi.** `admin.stats.get-form-phase`,
`admin.stats.get-form-submission`, `admin.stats.get-reviewer`, `admin.stats.get-user`, dan
`admin.stats.data` (dikomentari "testing data"). Tanpa pemanggil di `resources/js`; dua dipakai
`tests/Feature/StatControllerTest.php`. Data yang sama sudah sampai ke layar lewat halaman Inertia.

**S2 — Rincian status memakai status yang tidak ada.** Status pengajuan disimpan dengan nama state
(`draft`, `submitted`, `under_review`, `needs_revision`, `resubmitted`, `approved`, `rejected`,
`withdrawn` — `app/States/Submission`). `getFormPhaseStats` menghitung `'pending'` dan `'revision'`
— keduanya **selalu 0** — dan tidak pernah menghitung `submitted`, `needs_revision`, `resubmitted`.
Kartu "Menunggu" di dasbor admin (`StatisticSection.vue` mencari `status === 'pending'`) karena itu
selalu 0. `totalByStatus` mengelompokkan nama state mentah dan ikut menghitung **draft**, yang
belum diajukan siapa pun.

**S3 — "User Fakultas" menghitung satu fakultas saja.** `getUserStats.totalFaculty` menyaring
`parent_org.metadata->'legacy'->>'faculty_id' = '1'` — hanya user di bawah fakultas lama ber-id 1.

**S4 — Tautan rusak.** `Statistics/UserStats.vue` memanggil `route('admin.stats.index')` yang tidak
terdaftar (tercatat di `KNOWN_BROKEN_FRONTEND_ROUTES`).

## Keputusan

- **K1 — S1 dicabut.** `StatControllerTest` dipindah ke halaman Inertia
  (`admin.stats.form-submission`, `admin.stats.reviewer`, dan seterusnya) dengan `assertInertia`.
- **K2 — Satu peta status ke kelompok dasbor.** `Queries/Reporting/SubmissionStatusBuckets`
  memetakan state ke kelompok yang sudah dipakai layar: `pending` = `submitted` + `resubmitted`
  (menunggu ditinjau), `under_review`, `revision` = `needs_revision`, `approved`, `rejected`, dan
  `withdrawn` sebagai kelompok tambahan. **Draft tidak dihitung** di statistik status mana pun.
  Peta ditulis dari kelas state (`Submitted::$name`, dst.), bukan string tangan. Kunci prop yang
  dibaca Vue (`pending`, `under_review`, `approved`, `rejected`, `revision`, `totalByStatus[].status`)
  tidak berubah, sehingga Vue tidak disentuh — kecuali label `withdrawn` bila layar butuh.
- **K3 — "User Fakultas" = user yang unitnya berada di subtree fakultas mana pun** (unit fakultas
  itu sendiri atau di bawahnya); "User Program Studi" = user yang unitnya bertipe `study_program`.
  Tanpa id fakultas lama.
- **K4 — Tautan `admin.stats.index`** diarahkan ke route yang ada (`admin.dashboard`), dan entri
  `KNOWN_BROKEN_FRONTEND_ROUTES`-nya dihapus (daftar hanya menyusut).
- **K5 — Setiap kelompok statistik jadi Query** di `Queries/Reporting/` (`ChangesNoState`, bertipe):
  fase form, pengajuan, reviewer, user. Controller cangkang; bentuk prop tetap.

## Global Constraints

- Identifier bahasa Inggris; prosa komentar bahasa Indonesia, ≤ 3 baris berturut-turut, ≤ 93
  karakter termasuk indentasi; sebut kode BR; jangan membuang fakta latar.
- Dilarang menyiasati PHPStan. Query `implements ChangesNoState`. PostgreSQL: kolom non-agregat ikut
  `GROUP BY`; jangan `SUM(cond = 'x')` — pakai `CASE`/`FILTER`.
- `quality-budget.txt` hanya turun (termasuk `max.mixed`); `quality-legacy.txt` hanya menyusut.
  Nama test menyebut kode BR atau perilaku — jangan mengarang ID skenario.
- Jangan sentuh `docs/requirement-analysis/`. Commit satu baris tanpa trailer.
- Container `CI` dan DB test `simpas_dev_pg` :55432; satu proses test pada satu waktu.

## Definisi selesai

- [x] Gerbang hijau; `StatController` keluar dari daftar; `max.legacy.files` 107 → 106
- [x] Test S2, S3 merah sebelum perbaikan

---
---

# Rencana Implementasi

### Task 1: Cabut endpoint JSON testing (S1, K1)

- [x] Pindahkan `StatControllerTest` ke halaman Inertia (hijau terhadap kode lama — karakterisasi).
- [x] Test `Route::has` dataset kelima nama → false (**merah** dulu); cabut route, method, entri `ziggy.js`.
- [x] Gerbang; commit `refactor(reporting): cabut endpoint JSON statistik untuk testing`

### Task 2: Status dan user yang dihitung benar (S2, S3, K2, K3)

- [x] Test merah: pengajuan `submitted` + `resubmitted` muncul sebagai `pending`, `needs_revision`
  sebagai `revision`, draft tidak dihitung — baik di `formPhaseStatus` maupun `totalByStatus`;
  `totalFaculty` menghitung user dari dua fakultas berbeda (keduanya bukan legacy id 1).
- [x] Implementasi K2, K3 sebagai Query.
- [x] Gerbang; commit `fix(reporting): statistik status dan user dihitung dari state yang sebenarnya`

### Task 3: Sisa Query, tautan, dan keluar dari daftar (S4, K4, K5)

- [x] Pindahkan statistik fase form dan reviewer ke Query (karakterisasi bentuk prop dulu).
- [x] K4; `npm run quality` hijau; `FrontendRouteNamesTest` hijau dengan daftar menyusut.
- [x] `tools/limits.php` dan `tools/prosa.php` bersih; hapus baris `StatController`; plafon 107 → 106.
- [x] Status dokumen + *Yang berbeda dari rencana*. Commit `refactor(reporting): statistik lewat Query dan keluar dari daftar legacy`

## Yang berbeda dari rencana

- `SubmissionStatusBuckets` juga `implements ChangesNoState`: `AuditCoverageTest` menyapu semua kelas
  di `app/Queries/`, jadi pembantu tanpa penanda itu ditolak.
- Ada satu Query tambahan, `OrganizationReferences` (daftar fakultas dan program studi), karena
  ketiga layar membutuhkannya; tanpa itu kueri yang sama tertulis tiga kali.
- `total_submissions` di `formPhaseStatus` kini menghitung pengajuan non-draft (jumlah semua
  kelompok), konsisten dengan K2; sebelumnya ikut menghitung draft.
- `totalByStatus` hanya memuat kelompok yang terisi (seperti sebelumnya, yang hanya memuat status
  yang ada). Kelompok `withdrawn` baru muncul di sini dan di `formPhaseStatus`; Vue tidak berubah.
- "User Program Studi" dan "User Fakultas" menghitung user unik (`DISTINCT user_id`).
- Arti `totalProdi` berubah: dulu setiap profil yang punya unit apa pun; kini user unik (`DISTINCT
  user_id`) yang unitnya bertipe `study_program`.
- Subtree fakultas menghitung semua unit, aktif atau tidak: `totalFaculty` memakai satu CTE rekursif
  yang diawali setiap fakultas, tanpa filter `is_active` dan tanpa cache (`Organization::subtreeIds`
  hanya menelusuri turunan aktif). User dihitung menurut posisinya di pohon, jadi kartu fakultas dan
  prodi sepakat.
- Satu definisi "pengajuan" di setiap hitungan: draft dikecualikan. `total_submissions` (fase, per
  periode, per fakultas, per prodi) dan `totalByFaculty`/`totalByProdi` memakai
  `SubmissionStatusBuckets::countedStates()`, daftar state yang sama dengan kelompok status, sehingga
  "Total Pengajuan" di dasbor sama dengan jumlah kelompok status. Filter `is_submitted` diganti
  filter state; `total_forms` tidak berubah.
- Query mengembalikan model/kolektor Eloquent apa adanya (bentuk prop tetap); perluasan ke DTO
  bertipe tidak dilakukan agar bentuk JSON tidak bergeser.
- Commit 2 (perbaikan angka) ditulis lebih dulu pada controller lama dengan tambalan minimal,
  lalu commit 3 memindahkannya ke Query.
