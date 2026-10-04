# Gelombang 3 bagian 5 — Pengumuman dan Periode Pengajuan

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Temuan diukur pada `5dc7623`.

**Goal:** `AnnouncementController` dan `SubmissionPeriodController` memenuhi ambang keras dan keluar
dari `quality-legacy.txt` (`max.legacy.files` 109 → 107). Cacat yang ditemukan ditutup lebih dulu,
masing-masing dengan test yang merah sebelum perbaikan.

**Spec induk:** [`refactor-plan.md`](refactor-plan.md) §5 gelombang 3

**Status:** selesai (Task 1–5); kedua controller keluar dari `quality-legacy.txt`, `max.legacy.files` 107.

---

## Pengukuran awal

| Berkas | Pelanggaran |
| ------ | ----------- |
| `AnnouncementController` | ukuran berkas; `store()` loc 41; `update()` loc 47; prosa 5 baris |
| `SubmissionPeriodController` | ukuran berkas; `store()` loc 55; `update()` loc 59; prosa 4, 8, 4 baris |

## Temuan — diverifikasi di kode

Pengumuman tidak punya aturan di DDD/BDD; perilaku yang dijadikan acuan adalah yang sudah
dipakai halaman depan (`routes/web.php`, rute `welcome`): **publik** = tampil untuk tamu,
**privat** = hanya untuk pengguna yang login, dan yang `expired_at`-nya lewat tidak ditampilkan.

**A1 — Detail pengumuman bocor ke tamu.** `GET announcements/{announcement}`
(`announcements.detail`) berada di luar grup `auth` dan menampilkan pengumuman apa pun —
termasuk **privat** dan yang sudah kedaluwarsa — kepada siapa saja yang menebak id-nya.

**A2 — "Tandai sudah dibaca" mati total.** `User/Announcements/IndexPage.vue` memanggil
`/announcements/{id}/markRead`, padahal route-nya `/user/announcements/{id}/markRead` (404).
Ia juga memakai `router.get` Inertia terhadap endpoint yang menjawab JSON, dan `userIndex` tidak
pernah mengirim `is_read`, jadi status baca tidak bertahan saat halaman dimuat ulang. Mengubah
state lewat GET juga melanggar semantik HTTP.

**A3 — Hapus lampiran pengumuman tidak di-scope.** `update()` menghapus `AnnouncementFile`
berdasarkan id dari `deleted_files` tanpa memeriksa bahwa berkas itu milik pengumuman yang sedang
disunting — lampiran pengumuman lain ikut terhapus.

**A4 — Hapus pengumuman meninggalkan berkas.** `destroy()` memanggil `Storage::delete()` (disk
default), padahal berkas disimpan di disk `public`; berkasnya tidak pernah terhapus.

**A5 — Daftar pengumuman pengguna memuat yang kedaluwarsa** (berbeda dengan halaman depan).

**P1 — Validasi "tanggal berakhir setelah tanggal mulai" salah membaca domain.**
`SubmissionPeriodGate` menyatakan **setiap `SubmissionDate` adalah batas akhir**, bukan jendela
mulai–selesai. Pemeriksaan di `store`/`update` mengurutkan tanggal lalu membandingkan yang
pertama dengan yang terakhir — setelah diurutkan, satu-satunya yang tertolak adalah semua tanggal
sama persis, dan pesannya menyiratkan jendela yang tidak ada. Yang sesungguhnya bisa salah: dua
baris dengan label yang sama dalam satu permintaan.

**P2 — Notifikasi perpanjangan terkirim sebelum commit.** `update()` membuka transaksi luar,
memanggil `ExtendSubmissionDates` (yang mengirim notifikasi BR-SCH-08 setelah transaksinya
sendiri), lalu menulis ulang tahap. Bila langkah sesudahnya gagal, transaksi luar di-rollback
tetapi peneliti sudah dikabari perpanjangan yang tidak pernah terjadi.

**P3 — Galat dan audit.** `store`/`update` menempelkan `$e->getMessage()` ke layar; membuat
periode dan mengubah nama/tahapnya tanpa audit (BR-RPT-09).

## Keputusan

- **K1 — Visibilitas pengumuman satu definisi.** `Queries/Announcement/VisibleAnnouncements`
  (ChangesNoState) dengan dua sudut: tamu (publik, belum kedaluwarsa) dan pengguna login (publik +
  privat, belum kedaluwarsa). Dipakai halaman depan, `userIndex`, dan `detail`. `detail` untuk
  pengumuman yang tak terlihat oleh penanya menjawab 404. Admin (`index`/`show`/`edit`) tetap
  melihat semuanya.
- **K2 — Status baca diperbaiki, bukan dicabut.** Route menjadi `POST`
  (`user.announcements.markRead`, URL sama), Vue memakai `route()` dan `router.post`/`fetch`
  sesuai bentuk responsnya, dan `userIndex` mengirim `is_read` per pengguna. Perubahan Vue minimal
  — hanya pemanggilan itu (memori: Vue legacy tidak direfactor).
- **K3 — Lampiran lewat Action.** `Actions/Announcement/{PublishAnnouncement, ReviseAnnouncement,
  RetractAnnouncement}` — beraudit; lampiran yang dihapus hanya yang milik pengumuman itu; berkas
  dihapus dari disk `public`. Penanda waktu memakai `now()` (zona aplikasi sudah `Asia/Makassar`),
  bukan `Carbon::now('Asia/Makassar')` yang ditulis tangan.
- **K4 — Tanggal periode adalah batas akhir.** Pemeriksaan jendela dicabut; diganti "satu tanggal
  per label" (`submission_dates.*.label` distinct) dengan pesan yang jelas.
- **K5 — Periode lewat Action.** `Actions/Period/CreateSubmissionPeriod` (audit) dan
  `Actions/Period/ReviseSubmissionPeriod` (nama, tahap, tanggal dalam satu transaksi; audit).
  Notifikasi BR-SCH-08 hanya terkirim **setelah commit** — gunakan `DB::afterCommit` (atau
  mekanisme setara yang sudah dipakai repo) di `ExtendSubmissionDates`, jangan menelan galatnya.
  Galat tak terduga merambat; jangan ditempel ke layar.
- **K6 — `storeLabel`** tetap JSON (dipakai `fetch` di Create/EditPage); dipindah ke controller
  invokable kecil bila perlu agar berkas lolos ambang.

## Global Constraints

- Identifier bahasa Inggris; prosa komentar bahasa Indonesia, ≤ 3 baris berturut-turut, ≤ 93
  karakter termasuk indentasi; sebut kode BR; jangan membuang fakta latar.
- Dilarang menyiasati PHPStan. Setiap Action/Query menulis audit atau `implements ChangesNoState`.
- `quality-budget.txt` hanya turun; `quality-legacy.txt` hanya menyusut. Nama test menyebut kode
  BR atau perilaku — **jangan mengarang ID skenario**.
- Jangan sentuh `docs/requirement-analysis/`. Commit satu baris tanpa trailer.
- Container `CI` dan DB test `simpas_dev_pg` :55432; satu proses test pada satu waktu.

## Definisi selesai

- [x] `CI composer run quality` dan `npm run quality` hijau
- [x] Kedua controller keluar dari `quality-legacy.txt`; `max.legacy.files` 109 → 107
- [x] Test A1–A5, P1–P3 merah sebelum perbaikan (yang memang perilaku baru)

## Yang tidak dikerjakan

- Aturan domain pengumuman (tidak ada di DDD) — dokumen ini memakai perilaku halaman depan.
- Validasi tipe berkas lampiran selain yang sudah ada (`jpg,png,pdf`, 2 MB).

## Yang berbeda dari rencana

- `AnnouncementController` keluar dari `quality-legacy.txt` lebih awal, di Task 3 (plafon 109 → 108
  dibuat di sana); Task 5 hanya menurunkannya 108 → 107 untuk `SubmissionPeriodController`.
- Pembantu berkas lampiran ada di `app/Services/Announcement/AnnouncementFileStore`, bukan
  `app/Support`, karena `SupportPurityTest` melarang penulisan (disk/DB) di `app/Support`.
- `markAsRead` tetap di controller: ia satu pemanggilan yang pendek dan sudah lolos ambang; Action
  untuk satu baris tulis belum dibutuhkan.
- Task 4 melampaui brief pada beberapa titik: (1) pemeriksaan "satu tanggal per label" ada di
  `SubmissionPeriodRequest::after()` (kunci galat `submission_dates`) dan masukan dibawa DTO
  `SubmissionPeriodInput`; (2) audit `Tenggat periode diperpanjang` dalam `ExtendSubmissionDates`
  kini ditulis di dalam transaksi, sebelum notifikasi tertunda ke `DB::afterCommit`; (3) `storeLabel`
  dipindah ke `StoreSubmissionDateLabelController` (invokable) dan rute `submission-date-labels.store`
  menunjuknya; (4) dua komentar di controller diringkas agar lolos batas prosa tiga baris.

---
---

# Rencana Implementasi

### Task 1: Visibilitas pengumuman (A1, A5, K1)

- [x] Test merah: tamu membuka detail pengumuman **privat** → 404; tamu membuka detail yang
  kedaluwarsa → 404; pengguna login membuka privat → 200; daftar pengguna tidak memuat yang
  kedaluwarsa. Karakterisasi: halaman depan tetap memuat publik yang belum kedaluwarsa (maks. 10).
- [x] Implementasi K1; rute `welcome` memakai Query (closure-nya menyusut).
- [x] Gerbang; commit `fix(announcement): pengumuman privat dan kedaluwarsa tak terbuka untuk tamu`

### Task 2: Status baca (A2, K2)

- [x] Test merah: `POST user.announcements.markRead` menyimpan baris; `GET` tidak lagi mengubah
  state (405); `userIndex` mengirim `is_read` true untuk yang sudah dibaca pengguna itu dan false
  untuk lainnya. Sesuaikan `AnnouncementMarkReadTest` yang memakai GET.
- [x] Implementasi K2; test frontend kecil bila ada pola di `tests/frontend` untuk pemanggilan
  route (opsional).
- [x] Gerbang (`npm run quality` juga); commit `fix(announcement): tandai dibaca lewat POST dan status baca bertahan`

### Task 3: Pengumuman lewat Action (A3, A4, K3)

- [x] Test merah: `update` dengan `deleted_files` berisi id lampiran pengumuman **lain** → lampiran
  itu utuh; `destroy` menghapus berkas dari disk `public` (`Storage::fake('public')`); ketiga aksi
  diaudit.
- [x] Implementasi K3; controller jadi cangkang.
- [x] Gerbang; commit `fix(announcement): lampiran ter-scope dan pengumuman lewat Action beraudit`

### Task 4: Periode lewat Action (P1–P3, K4–K6)

- [x] Test: dua baris berlabel sama → `assertSessionHasErrors('submission_dates')` (**merah**);
  dua batas akhir berbeda label dengan tanggal sama → diterima (**merah** bila hari ini ditolak);
  membuat periode diaudit (**merah**); revisi yang gagal setelah tanggal diperpanjang tidak
  mengirim notifikasi (**merah** — paksa gagal lewat `form_phase_ids` yang memicu galat setelah
  sinkron tanggal, atau cara lain yang sah); `DeadlineExtensionTest` dan test periode lain tetap hijau.
- [x] Implementasi K4–K6.
- [x] Gerbang; commit `fix(period): tanggal periode sebagai batas akhir dan revisi atomik beraudit`

### Task 5: Ukur dan keluarkan

- [x] `tools/limits.php` dan `tools/prosa.php` bersih untuk kedua controller dan berkas baru.
- [x] Hapus kedua baris dari `quality-legacy.txt`; `max.legacy.files` 109 → 107.
- [x] Status dokumen; *Yang berbeda dari rencana*. Commit `refactor: pengumuman dan periode keluar dari daftar legacy`
