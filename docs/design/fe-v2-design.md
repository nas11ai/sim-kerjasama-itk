# Desain Frontend SIMPAS v2 — Tulis Ulang per Layar Mengikuti Wireframe

**Versi:** 0.4
**Tanggal:** 2026-10-25
**Status:** Draf; keputusan terbuka K1–K3 dan K5 sudah diputuskan pemilik (§9).
v0.4: pengumuman dipertahankan dan diberi pembatasan akses per peran (§4.7, irisan
A1), mengubah K3.

Dokumen ini menetapkan **bagaimana** frontend SIMPAS v2 dibangun ulang: apa yang
dibuang, apa yang dipertahankan, bentuk kode barunya, dan urutan pengerjaannya.
Dokumen ini ditulis supaya bisa dikerjakan **lintas banyak session**: tiap session
cukup membaca §1–§6 sekali dan baris irisannya di §8. Tidak perlu menyurvei ulang
repo.

Sumber kebenaran, urutan prioritasnya kalau saling bertentangan:

1. `docs/requirement-analysis/ddd/`: aturan bisnis (BR-XX-NN).
2. `docs/requirement-analysis/bdd/`: perilaku yang teramati.
3. Wireframe `../simpas-wireframe/`: tata letak, isi layar, state. Repo terpisah,
   folder `screens/<FRAME-ID>/screen.js`, peta frame ke BDD/BR di
   `docs/FRAME-AUDIT.md`, daftar state wajib per frame di `docs/STATE-COVERAGE.md`.
4. `docs/requirement-analysis/ux/`: IA dan pola interaksi.

Kalau wireframe bertentangan dengan DDD/BDD, **DDD/BDD menang**. Catat
selisihnya di §9.

---

## 1. Kenapa ditulis ulang

Diukur 2026-10-25:

- `resources/js/Pages`: 96 berkas, ±36.500 baris. Sekitar 40 halaman lebih dari
  450 baris; terbesar `FormAccessControls/IndexPage.vue` 1.044 baris.
- Detail pengajuan ditulis dua kali (`User/Submissions/ShowSubmission.vue` 749
  baris, `Submissions/ShowSubmission.vue` 737 baris), masing-masing mendefinisikan
  ulang belasan interface di dalam berkasnya.
- Struktur halaman mengikuti tabel database (Forms, FormPhases,
  FormAccessControls, SubmissionPeriods, ReviewEvaluationForms terpisah), bukan
  mengikuti tugas pengguna. Wireframe mengikuti tugas pengguna.
- Enum `SubmissionStatus` frontend punya 5 nilai, state backend punya 8
  (`CODING_STANDARDS.md` §Utang).
- Shared props masih mengirim `roles` dan `is_reviewer`; IA sidebar
  (`ux/ia/00_navigation/sidebar.md`) menuntut visibilitas berbasis **permission**.
- Vitest hanya 4 berkas. Tidak ada jaring pengaman yang membuat refactor
  bertahap lebih murah daripada menulis ulang.
- 93 berkas Vue masih ada di `quality-legacy.txt`. Rencananya memang keluar
  lewat **penghapusan**, bukan dirapikan.

Aplikasi **belum live**. Tidak ada pengguna yang perlu dijaga, sehingga yang
lama tidak perlu tetap berfungsi selama pembangunan.

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**

- Setiap frame wireframe yang ada di §8 punya halaman Vue yang menggambar
  isi dan **semua state** frame itu (happy path + `@denial`/`@boundary` dari
  `STATE-COVERAGE.md`).
- Pengumuman, satu-satunya layar tanpa frame wireframe, mengikuti aturan di
  §4.7 dan disusun dari komponen shell yang sudah ada (§9 K5).
- Semua kosakata UI dan kode memakai `02_ubiquitous_language.md`.
- Semua halaman legacy terhapus; `max.legacy.files` turun ke 1
  (`app/Models/FormSubmission.php`, permanen).
- Halaman tipis dan mudah dibaca, dijaga mesin oleh ambang yang sudah ada
  (`hard.vue.file.loc: 250`, tanpa blok duplikat).

**Non-tujuan**

- Menambah aturan bisnis baru. Kalau sebuah layar butuh perilaku yang belum ada
  di DDD/BDD, berhenti dan tanyakan. Satu-satunya yang sudah ditanyakan dan
  diputuskan: pengumuman (§4.7, §9 K5).
- REST API, SSR, i18n multi-bahasa, dark mode. UI satu bahasa (Indonesia).
- Pencarian global topbar (Ctrl+K, `ux/ia/00_navigation/topbar.md`). Ditunda
  (§9 K2): topbar dirender tanpa kotak pencarian sampai irisan tersendiri
  dibuka setelah F99.
- Desain visual di luar wireframe. Wireframe lo-fi sudah mencerminkan
  komponen shadcn-vue dan token produksi; hasil akhirnya = shadcn-vue default +
  `tokens.css`, bukan desain baru.
- Merapikan halaman legacy. Halaman legacy hanya disentuh untuk **dihapus**.

**Anti-tujuan**: hasil yang tetap dianggap gagal meski "jalan":

- Frontend memutuskan otorisasi atau aturan bisnis sendiri (mis. menghitung
  sendiri boleh-tidaknya submit). Frontend hanya membaca `can` dan state dari
  backend.
- Memeriksa nama role di frontend (`roles.includes('admin')`).
- Menyalin pola legacy ke halaman baru: interface didefinisikan di dalam
  halaman, `axios` untuk data halaman, `confirm()` native, satu halaman untuk
  banyak tugas.
- Halaman yang terlihat benar tapi diam-diam tidak menggambar state denial,
  sehingga pengguna mengklik tombol yang pasti ditolak backend.

## 3. Yang dipertahankan dan yang dibuang

| Dipertahankan | Alasan |
| --- | --- |
| Inertia v3 + Vue 3 `<script setup>` + TS + Tailwind v4 + Ziggy | Arsitektur yang sudah disepakati (ADR 0007) |
| `resources/js/Components/ui/` (34 primitive shadcn-vue/reka) | Vendored, sudah selaras token wireframe |
| Toolchain: ESLint, Prettier, vue-tsc, Vitest, Playwright | Gate `npm run quality` tetap sama |
| Semua route, Action, Policy, Query di backend | Backend sudah selesai; hanya lapisan presentasinya yang disesuaikan |

| Dibuang (dihapus per irisan) | Penggantinya |
| --- | --- |
| Semua `Pages/**` legacy | Halaman baru per frame (§4) |
| `Components/*.vue` di luar `ui/` | Komponen per konteks + komponen shell (§4) |
| `Layouts/AuthenticatedLayout.vue` dll. | `AppLayout`, `AuthLayout` sesuai `shell.yaml` wireframe |
| `Constants/SubmissionStatus.ts`, `Utils/getSubmissionStatusInfo.ts` | Satu peta 8 state di `types/` + `StatusBadge` |
| Store Pinia untuk data server | Props Inertia (Pinia hanya untuk state klien yang benar-benar global, mis. toast) |

Dependency tidak ditambah atau diganti sebagai efek samping. Kalau sebuah irisan
benar-benar butuh paket baru, tanyakan dulu.

## 4. Bentuk kode

### 4.1 Struktur folder

Mengikuti tabel `CODING_STANDARDS.md` §Frontend, diisi per bounded context:

```text
resources/js/
├── Pages/
│   ├── Auth/            SHARED-01-*
│   ├── Dashboard/       SHARED-02-01..04
│   ├── Notification/    SHARED-02-05
│   ├── Announcement/    tanpa frame (§4.7)
│   ├── Profile/         SHARED-01-06, 01-07
│   ├── Submission/      RESEARCHER-02-*, OPERATOR-02-*, RESEARCHER-07-01
│   ├── Review/          REVIEWER-03-*, OPERATOR-03-*
│   ├── Monev/           RESEARCHER-05-*, OPERATOR-05-*
│   ├── ResearchOutput/  RESEARCHER-06-*
│   ├── Identity/        OPERATOR-08-01..04, ADMIN-08-01
│   ├── FormEngine/      ADMIN-08-05..09, OPERATOR-09-01
│   ├── Scheme/          ADMIN-08-03, 08-04
│   ├── SystemConfig/    ADMIN-08-02, 08-10
│   └── Reporting/       OPERATOR-07-*
├── Components/
│   ├── ui/              primitive shadcn (vendored, jangan disunting kecuali perlu)
│   ├── app/             shell & pola lintas konteks (lihat 4.3)
│   └── <Context>/       komponen milik satu konteks, nama folder = nama di Pages/
├── Layouts/             AppLayout.vue, AuthLayout.vue, BlankLayout.vue
├── composables/         logika reaktif yang dipakai ulang (useFilters, useConfirm, …)
├── types/               kontrak props per halaman + tipe domain bersama
└── lib/                 fungsi murni: format tanggal/rupiah, util kecil
```

Nama halaman: `Pages/<Context>/<Tugas>Page.vue`, misalnya
`Submission/IndexPage.vue`, `Submission/ShowPage.vue`,
`Submission/WizardPage.vue`, `Submission/ManageShowPage.vue` (perspektif
operator). Satu frame wireframe = satu halaman, **kecuali** frame yang di
wireframe hanya berupa langkah/tab/dialog dari frame lain (lihat §8, kolom
"Halaman").

### 4.2 Aturan halaman

- **Halaman tipis.** Halaman hanya: `defineProps<XxxPageProps>()` → susun section
  dari komponen konteks → pasang aksi. Batas mesin 250 baris; sasaran praktis
  < 150.
- **Satu sumber bentuk data.** Interface props halaman ada di
  `types/<context>.ts` (mis. `SubmissionShowPageProps`), ditulis mengikuti
  `Data` class backend. Komponen menerima potongan props yang sudah bertipe,
  bukan mendefinisikan ulang.
- **Section dipakai ulang lintas perspektif.** Detail pengajuan peneliti,
  operator, dan reviewer adalah tiga halaman tipis yang menyusun section yang
  sama (`Components/Submission/InfoSection.vue`, `MembersSection.vue`,
  `BudgetSection.vue`, …). Perbedaannya ada di panel aksi dan objek `can`, bukan
  di salinan halaman.
- **Mutasi lewat `useForm`/`router` Inertia.** Error validasi dibaca dari
  `form.errors`. Tidak ada `axios` untuk data halaman. Pengecualiannya polling
  progres atau unduhan, dan itu ditulis sebagai composable.
- **Filter, pencarian, dan paginasi di server.** `router.get(…, { preserveState:
  true, replace: true })` lewat `useFilters`; tabel tidak memfilter data di
  klien.
- **Konfirmasi lewat `ConfirmDialog`**, bukan `confirm()`. Aksi destruktif atau
  tak bisa dibatalkan (tolak manual, tarik, alihkan kepemilikan) memakai dialog
  dengan alasan wajib bila BR-nya menuntut.
- **Komentar BR.** Komponen yang menggambar konsekuensi sebuah aturan memberi
  komentar kodenya (`<!-- BR-BUD-03 — anggaran terkunci setelah keputusan -->`),
  sama seperti di PHP.
- **Frame ID di kepala halaman.** Baris pertama `<script setup>`:
  `// Frame: RESEARCHER-02-03, RESEARCHER-02-12`. Ini yang menghubungkan kode ke
  wireframe dan `FRAME-AUDIT.md`.

### 4.3 Komponen shell dan pola bersama (`Components/app/`)

Diturunkan dari helper wireframe (`h.pageHeader`, `h.tabs`, `h.banner`,
`h.sectionCard`, `h.infoGrid`, `h.field`, `h.statusBadge`, `h.btn`) dan organism
di `screens/_lib/organisms.js`:

`AppSidebar`, `AppTopbar`, `PageHeader` (back, judul, subjudul, badge, aksi),
`SectionCard`, `InfoGrid`, `Banner` (info/peringatan/denial), `StatusBadge`
(8 state submission + state lain bila perlu), `DataTable` (pembungkus
`@tanstack/vue-table` + paginasi server), `FilterBar`, `EmptyState`,
`ConfirmDialog`, `FormField` (label + kontrol + error + hint), `ActionPanel`
(tombol + hint kenapa tersedia/tidak), `Stepper` (wizard).

Komponen bersama dibuat **saat irisan pertama yang membutuhkannya**, bukan
dibangun di muka. Pengecualiannya shell (§8 irisan F0).

### 4.4 Otorisasi dan navigasi

- **Visibilitas aksi** dibaca dari objek `can` per halaman, hasil Policy di
  backend (ADR 0007 butir 3). Contoh yang sudah ada:
  `app/Data/Submission/SubmissionAbilitiesData.php`.
- **Sidebar** dibaca dari permission user di shared props. `HandleInertiaRequests`
  ditambah `auth.user.permissions: string[]`, dan **flag sidebar yang bergantung
  pada data** (mis. "punya pengajuan APPROVED" untuk Monev/Luaran) dikirim
  sebagai boolean dari backend. Peta nav item → permission ada di
  `ux/ia/00_navigation/sidebar.md`; isi menu dan urutannya di `shell.yaml`
  wireframe.
- **Route dijaga permission DDD, bukan `users.manage` borongan** (§9 K1). Saat
  ini layar operator (periode, user, statistik, grup `/admin`, `/admin/reference`)
  dijaga `can:users.manage`, yang menurut `ddd/generic/02_identity_access.md`
  hanya dimiliki Admin. Akibatnya Operator melihat menu lalu kena 403. Setiap
  irisan yang menyentuh sebuah route menggantinya dengan permission yang sesuai
  peta `sidebar.md`, dengan pemetaan untuk dua permission yang tidak ada di DDD
  maupun `PermissionSeeder`:

  | Di `sidebar.md` | Dipakai di kode |
  | --- | --- |
  | `forms.manage` | `schemes.manage` |
  | `reporting.view` | `reporting.export` **atau** `reporting.view-audit-log` |

  Dokumen DDD dan UX tidak disunting; pemetaan ini satu-satunya tempatnya.
- `roles` dan `is_reviewer` di shared props dihapus setelah tidak ada pemakainya
  (irisan F99).
- **Dashboard satu halaman** (keputusan penulis dokumen). Empat frame dashboard
  wireframe (SHARED-02-01..04) digambar sebagai **section** di satu
  `Dashboard/IndexPage`, masing-masing tampil menurut permission. User
  dua peran (reviewer sekaligus peneliti) melihat gabungannya, sesuai prinsip
  "tanpa pindah konteks" di `sidebar.md`. Tidak ada aturan prioritas role.
- Utang `Gate::before` Super Admin (`CODING_STANDARDS.md` §Utang, baris
  terakhir) harus diperiksa sebelum tombol submit membaca `can`. Kalau masih
  terbuka saat irisan S2, selesaikan di irisan itu.

### 4.5 Kontrak backend → frontend per halaman

Setiap irisan yang menyentuh sebuah halaman juga merapikan kontraknya di
backend. Ini **bagian dari irisan**, bukan proyek terpisah:

1. Controller merender nama halaman baru (`Inertia::render('Submission/ShowPage', …)`).
2. Props disusun dari `Data` class di `app/Data/View/` (pola yang sudah ada:
   `EvaluationProgressData`), termasuk `can`. Bacaan → `app/Queries/`.
   Controller tetap tipis (AGENTS.md aturan 3).
3. Test Pest kontrak: `assertInertia(fn ($page) => $page->component('Submission/ShowPage')->has('submission')->has('can'))`.
   Nama halaman yang tidak ada akan gagal di sini.
4. Interface TS yang sama bentuknya ditulis di `types/`.
5. Middleware route memakai permission DDD (§4.4). Test Pest: peran yang berhak
   mendapat 200, peran yang tidak berhak mendapat 403.

Kalau frame membutuhkan halaman yang **backend-nya belum ada** (kolom "BE" di §8
bertanda ✗), irisan itu membangun controller/query-nya juga, dengan aturan
backend biasa (Action/Query/Policy + Pest).

### 4.6 Status submission

State class backend (`app/States/Submission/`) sudah memiliki `key()`, `label()`
berbahasa Indonesia, dan `variant()`. Label karenanya **dikirim backend**
(`app/Data/View/SubmissionStatusData`) dan frontend hanya merendernya lewat
`StatusBadge`, tanpa peta label kedua. Frontend tetap punya union
`SubmissionStatusKey` (8 key) di `types/submission-status.ts` untuk logika, dan
sinkronnya dijaga test arsitektur Pest. (v0.3: menggantikan rencana peta label
di frontend setelah ditemukan bahwa backend sudah memegangnya.)

### 4.7 Pengumuman

Pengumuman tidak punya BR, skenario BDD, maupun frame wireframe. Aturan di
bawah ini diputuskan pemilik (§9 K5) dan menjadi acuan satu-satunya. Komentar
kode merujuk `fe-v2-design §4.7`, bukan kode BR karangan.

**Siapa membuat.** Operator (dan Admin lewat katalog penuhnya) memakai
permission baru `announcements.manage`, ditambahkan ke
`PermissionSeeder::PERMISSIONS` dan ke peran `operator`. Grup route kelola
pengumuman keluar dari `can:users.manage` (pola K1). Saat ini Operator belum
bisa membuat pengumuman karena route itu hanya untuk Admin.

**Siapa melihat.** Ada dua jenis, `public` dan `private`, keduanya sudah ada di
kolom `announcements.type`. Pengumuman privat bisa dipersempit ke peran:

| Jenis | Target peran | Terlihat oleh |
| --- | --- | --- |
| `public` | tidak boleh diisi | tamu dan semua pengguna login |
| `private` | kosong | semua pengguna login |
| `private` | satu atau lebih peran | pengguna login yang punya minimal satu peran itu |

- Target peran disimpan di tabel pivot baru `announcement_roles`
  (`announcement_id`, `role_id`). Migration hanya dijalankan ke DB dev/test.
- Peran yang bisa dipilih adalah lima peran DDD di `PermissionSeeder::ROLE_PERMISSIONS`
  (`researcher`, `reviewer_internal`, `reviewer_external`, `operator`, `admin`),
  bukan role legacy `Admin`. Labelnya dikirim backend, sehingga frontend tidak
  memeriksa nama peran (§2 anti-tujuan).
- Validasi: target peran pada pengumuman `public` ditolak (`AnnouncementRequest`).
- Pengumuman yang `expired_at`-nya lewat tidak terlihat oleh pembaca. Layar kelola
  tetap menampilkannya dengan badge "Kedaluwarsa".
- Satu definisi visibilitas: `Queries/Announcement/VisibleAnnouncements` diperluas
  dengan filter peran. Semua jalur pembaca (daftar, detail, dashboard, umpan
  publik, hitungan belum dibaca) wajib lewat Query ini. Pengumuman yang tidak
  terlihat oleh penanya dijawab **404** (perilaku yang sudah ada, A1 wave 3).

**Sudah dibaca.** Tabel `announcement_users` tetap menjadi penanda baca. Halaman
detail mengirim `router.post(route('user.announcements.markRead'))` sekali saat
dibuka bila belum dibaca. Endpoint-nya diubah dari JSON menjadi redirect back
Inertia. Sidebar item "Pengumuman" menampilkan jumlah belum dibaca
(`auth.user.unread_announcements`, lazy shared prop).

**Isi.** Isi pengumuman berupa teks biasa yang dirender dengan
`whitespace-pre-line`, **tanpa `v-html`**. Legacy (`AnnouncementDetail.vue`)
menerima teks dari `Textarea` tetapi merendernya sebagai HTML, sehingga terjadi
XSS tersimpan. Lampiran tetap mengikuti `AnnouncementRequest` (jpg/png/pdf, 2 MB).

**Di luar cakupan:** notifikasi/email saat terbit, target per form/skema atau
per user, jadwal terbit di masa depan, rich text. Kalau dibutuhkan, buka
keputusan baru di §9.

## 5. Testing

| Lapis | Alat | Isi |
| --- | --- | --- |
| Kontrak props | Pest | Satu test per halaman baru: nama komponen + kunci props + `can` (§4.5) |
| Aturan bisnis | Pest | Sudah ada, mengikat semua skenario BDD (`pending.txt` kosong). Tidak diduplikasi di frontend |
| Logika UI | Vitest | Wajib untuk composable dan komponen yang punya logika (navigasi wizard, `useFilters`, peta status, `ActionPanel` membaca `can`). Tidak perlu untuk komponen presentasi murni |
| Alur kritis | Playwright | Satu smoke per milestone: login → buat pengajuan → submit → tugaskan → evaluasi → keputusan. Dijalankan di akhir S4 dan F99 |

Test Vitest yang menggambar state dari skenario BDD menyebut ID-nya
(`it('[SM-SUBMIT-03] tombol submit tersembunyi saat periode tutup')`). Karena
skenario itu sudah dicakup Pest, `pending.txt` tidak berubah.

## 6. Protokol per session

Tujuannya hemat token: satu session = satu irisan, tanpa survei ulang.

**Rencana per irisan.** Setiap irisan punya rencana implementasinya sendiri di
`docs/plans/YYYY-MM-DD-fe-<irisan>.md`, ditulis dengan skill
`superpowers:writing-plans` **tepat sebelum** irisan itu dikerjakan, supaya
nama komponen dari irisan sebelumnya sudah pasti. Rencana F0 sudah ada:
`docs/plans/2026-10-25-fe-f0-shell.md`. Irisan besar (S2b, S4, E2, E3)
dikerjakan dalam dua session: satu untuk menulis rencana, satu untuk
menjalankannya. Irisan kecil boleh dikerjakan dalam satu session. Kalimat
pembuka session cukup:
*"Kerjakan irisan berikutnya di docs/design/fe-v2-design.md"*.

**Mulai session**

1. Baca dokumen ini §1–§6 dan baris irisan berikutnya di §8 (status `☐`
   teratas yang dependensinya `☑`).
2. Untuk tiap frame di irisan itu, baca:
   `../simpas-wireframe/screens/<ID>/screen.js`, bagian `<ID>` di
   `../simpas-wireframe/docs/FRAME-AUDIT.md` dan `STATE-COVERAGE.md`, lalu BR
   yang disebut di sana. Untuk melihat tampilan visualnya, buka
   `../simpas-wireframe/dist/present.html` di browser.
3. Baca controller backend yang sekarang merender layar itu, dan halaman legacy
   yang akan dihapus. Ini hanya untuk tahu props dan route-nya; jangan menyalin
   strukturnya.

**Kerjakan**: kontrak backend (§4.5) → tipe TS → komponen → halaman → test.

**Tutup irisan**

1. Hapus halaman dan komponen legacy yang digantikan. Hapus barisnya dari
   `quality-legacy.txt`, turunkan `max.legacy.files`, dan hapus nama route dari
   `KNOWN_BROKEN_FRONTEND_ROUTES` bila halamannya hilang.
2. Jalankan `composer run quality` dan `npm run quality` (lewat container bila
   PHP host bukan 8.4, lihat AGENTS.md). Laporkan hasilnya apa adanya.
3. Ubah status irisan di §8 menjadi `☑`, tambahkan selisih wireframe↔DDD yang
   ditemukan ke §9.
4. Commit satu baris (`feat(fe): …`), lalu sarankan session baru dan sebutkan
   irisan berikutnya.

Irisan yang ternyata terlalu besar untuk satu session dipecah di §8 (mis. S2b →
S2b-1, S2b-2) sebelum dikerjakan, bukan di tengah jalan.

## 7. Urutan

Mengikuti ADR 0009 (critical path dulu): shell → loop inti pengajuan → review →
operasi operator → administrasi → pasca-persetujuan → laporan → bersih-bersih.
Selama pembangunan, layar yang belum diganti boleh rusak karena aplikasi belum live,
tapi **gate quality harus hijau di setiap commit**. Karena itu halaman legacy
dihapus di irisan yang menggantikannya, tidak lebih awal.

## 8. Irisan

Kolom **BE**: ✓ = controller & route sudah ada, cukup merapikan props;
◐ = ada tapi perlu dibentuk ulang cukup besar; ✗ = perlu dibangun. Status
perkiraan saat dokumen ditulis. Verifikasi di awal irisan.

| # | Status | Irisan | Frame | Halaman baru | BE | Legacy yang dihapus (perkiraan) | Bergantung |
| --- | --- | --- | --- | --- | --- | --- | --- |
| F0 | ☑ | Shell & fondasi | (shell.yaml) | `AppLayout`, `AuthLayout`, `AppSidebar`, `AppTopbar`, `PageHeader`, `StatusBadge`, peta status, `auth.user.permissions` | ◐ shared props | — (layout lama tetap sampai F99) | — |
| S1 | ☑ | Auth | SHARED-01-01..05, 01-08, 01-09 | `Auth/*` | ✓ | `Pages/Auth/*` | F0 |
| S2a | ☑ | Pengajuan peneliti: daftar & detail | RESEARCHER-02-01, 02-03, 02-12 | `Submission/IndexPage`, `Submission/ShowPage` + section | ◐ | `User/Submissions/IndexPage`, `ShowSubmission` (`ShowPeriod` pindah ke S2b) | F0 |
| S2b-1 | ☑ | Wizard pengajuan: cangkang, langkah 1/2/3/6/7, submit | RESEARCHER-02-02, 02-06, 02-07, 02-09, 02-10 | `Submission/WizardPage` + `Components/app/Stepper` + `Components/Submission/Wizard/*` | ◐ (ADR 0012; rencana `docs/plans/2026-10-31-fe-s2b-wizard.md`) | `User/FormPhase` + route `user.form-phase`, `User/Submissions/ShowPeriod` + route `user.submissions.period`, `TargetOutputManager` | S2a |
| S2b-2 | ☐ | Wizard pengajuan: langkah 4 anggota & 5 anggaran | RESEARCHER-02-08, 02-04 | `Components/Submission/Wizard/{MembersStep,BudgetStep,…}` | ◐ | `ResearchMemberManager` | S2b-1 |
| S2c | ☐ | Edit draft, revisi, target luaran, audit saya | RESEARCHER-02-04, 02-05, 02-11, 07-01 | `Submission/EditPage`, `RevisionPage`, `TargetOutputPage`, `AuditPage` | ◐ / ✗ audit peneliti | sisa `User/*` pengajuan | S2b |
| S3a | ☐ | Review oleh reviewer | REVIEWER-03-01..04 | `Review/AssignmentIndexPage`, `HistoryPage`, `EvaluationPage`, `SummaryPage` | ◐ | `Reviewer/*`, `ReviewSystem`, `ReviewScorePanel` | S2a |
| S4 | ☐ | Pengajuan sisi operator | OPERATOR-02-01..06, OPERATOR-03-01, 03-02 | `Submission/ManageIndexPage`, `ManageShowPage` + dialog perpanjang/alihkan/tolak/tarik/tugaskan/ganti | ◐ | `Submissions/*`, `AssignReviewerDialog`, `ReplaceReviewerDialog`, `RejectSubmissionDialog`, `TransferOwnershipDialog`, `SubmissionEvaluationManager/*`, `ReviewerAssignments/*` | S2a, S3a |
| A1 | ☐ | Pengumuman: baca & kelola | tanpa frame (§4.7) | `Announcement/IndexPage`, `Announcement/ShowPage` (pembaca; tamu untuk yang publik), `Announcement/ManageIndexPage`, `Announcement/FormPage` (buat & sunting), panel pengumuman publik di halaman login (§9 K5) | ◐ permission `announcements.manage`, pivot `announcement_roles`, `VisibleAnnouncements` + filter peran, `markRead` → redirect Inertia | `Announcements/*`, `AnnouncementDetail`, `User/Announcements/*` | S1 |
| D1 | ☐ | Dashboard & notifikasi | SHARED-02-01..05 | `Dashboard/IndexPage` (section per permission, §4.4, + section "Pengumuman terbaru" lewat `VisibleAnnouncements`), `Notification/IndexPage` | ◐ | `DashboardPage`, `User/DashboardPage`, `Reviewer/DashboardPage`, `Notifications/*` | S2a, S3a, S4, A1 |
| I1 | ☐ | User, verifikasi, undangan, reviewer | OPERATOR-08-01..04, ADMIN-08-01 | `Identity/*` | ◐ | `Users/*`, `Identity/*`, `Reviewers/*` | F0 |
| P1 | ☐ | Profil | SHARED-01-06, 01-07 | `Profile/CompletePage`, `Profile/ShowPage` | ◐ | `Profile/*`, `User/Biodata/*` | S1 |
| E1 | ☐ | Periode pengajuan | OPERATOR-09-01 | `FormEngine/PeriodIndexPage` | ◐ | `SubmissionPeriods/*` | F0 |
| E2 | ☐ | Form: daftar, general & fields, access control | ADMIN-08-05..07 | `FormEngine/FormIndexPage`, `FormEditPage` (tab) | ◐ besar | `Forms/*`, `FormAccessControls/*`, `FormBuilder/*` | E1 |
| E3 | ☐ | Form: tahapan & detail tahap (termasuk form evaluasi) | ADMIN-08-08, 08-09 | tab Phases di `FormEditPage`, `FormEngine/PhaseEditPage` | ◐ besar | `FormPhases/*`, `ReviewEvaluationForms/*`, `EvaluationForms/*` | E2 |
| C1 | ☐ | Skema, organisasi, konfigurasi umum | ADMIN-08-02..04, 08-10 | `Scheme/*`, `SystemConfig/OrganizationPage`, `SystemConfig/GeneralPage` | ✓ skema / ✗ organisasi / ◐ konfigurasi | `Schemes/*`, `Config/*` | F0 |
| M1 | ☐ | Monev | RESEARCHER-05-01..03, OPERATOR-05-01, 05-02 | `Monev/*` | ✗ daftar peneliti / ◐ siklus | `Monev/CyclePage` | S2a |
| O1 | ☐ | Luaran penelitian | RESEARCHER-06-01..03 | `ResearchOutput/*` | ✗ daftar / ✓ simpan | `ResearchOutputManager` | S2a |
| R1 | ☐ | Statistik, export, audit sistem | OPERATOR-07-01..03 | `Reporting/StatsPage` (menggantikan empat halaman statistik legacy), `ExportPage`, `AuditPage` | ◐ empat aksi `StatController` digabung jadi satu / ✓ export, audit | `Statistics/*`, `Exports/*`, `Audit/*`, `StatisticSection` | D1 |
| F99 | ☐ | Bersih-bersih | — | — | hapus `roles`/`is_reviewer` dari shared props | `AuthenticatedLayout`, sisa `Components/*` di luar `ui/` & `app/`, `Constants/`, `Utils/`; layar tanpa wireframe beserta route & controller-nya (§9 K3): `Roles/*`, `Permissions/*`, `WelcomePage` (`/` dialihkan ke dashboard atau login; umpan publik sudah pindah ke halaman login di A1); `max.legacy.files` → 1; Playwright smoke | semua |

## 9. Keputusan terbuka dan selisih

| Kode | Pertanyaan / selisih | Keputusan |
| --- | --- | --- |
| K1 | Route operator dijaga `can:users.manage` (hanya Admin), sedangkan sidebar memakai permission DDD; `forms.manage` dan `reporting.view` di `sidebar.md` tidak ada di DDD/seeder. | Pemilik, 2026-10-25: tiap irisan menjaga ulang route-nya dengan permission DDD + test 200/403; dua permission dipetakan ke yang ada (§4.4). DDD tidak disunting. |
| K2 | Pencarian global topbar ada di wireframe dan `topbar.md`, tapi endpoint-nya belum ada dan bertentangan dengan "tanpa REST API". | Pemilik, 2026-10-25: ditunda; irisan tersendiri setelah F99, dengan Query ber-scope BR-SM-07. |
| K3 | Layar legacy yang tidak ada di wireframe maupun DDD/UX: Announcements (admin & user), layar mendefinisikan Roles/Permissions, empat halaman Statistics, `WelcomePage`. Memberi role ke user tetap ada di OPERATOR-08-03. | Pemilik, 2026-10-25: dibuang di F99; statistik digabung ke `Reporting/StatsPage` di R1. **Diubah oleh K5:** Announcements tidak dibuang. |
| K4 | Satu dashboard atau empat untuk user dua peran. | Penulis dokumen: satu halaman, section per permission (§4.4). |
| K5 | Kebutuhan bisnis: operator membuat pengumuman secara manual (contoh: format file baru untuk input form pengajuan), dan aksesnya harus bisa dibatasi. Tidak ada di wireframe/DDD/BDD. | Pemilik, 2026-10-25: dipertahankan sebagai irisan A1; publik/privat tetap, privat bisa dipersempit **per peran** (bukan per form/skema). Aturannya di §4.7. Penulis dokumen: permission `announcements.manage` untuk Operator, isi teks biasa tanpa `v-html`, tanpa notifikasi saat terbit, umpan publik tampil sebagai panel di halaman login (SHARED-01-01) karena `/` dialihkan ke login (K3). |
| W1 | `sidebar.md` menampilkan Daftar Monev dan Luaran Penelitian hanya bila ada "submission APPROVED milik user"; DDD lebih luas: BR-RO-07 membolehkan `co_investigator` aktif mengelola luaran, BR-RO-06 menyimpan luaran sebagai data historis setelah WITHDRAWN, BR-MON-08 membekukan (bukan menghapus) monev saat WITHDRAWN. `HasApprovedSubmission` di F0 mengikuti `sidebar.md` (`submitted_by` + Approved saja); tanpa efek di F0 karena kedua item masih `routeName: null`. | Ditunda ke M1/O1: kemungkinan Approved ∪ Withdrawn dan keanggotaan `co_investigator` aktif; diputuskan di rencana irisan itu (DDD menang atas UX). |
| W2 | `sidebar.md` menampilkan Daftar Pengajuan untuk `submissions.view-own` ATAU `submissions.view-all`; F0 hanya memakai `view-own` karena operator sudah mendapat Semua Pengajuan. | Penulis rencana F0: hanya view-own; operator memakai Semua Pengajuan. |
| W3 | Wireframe auth vs backend: tautan "Punya link undangan?" di SHARED-01-01 tidak punya tujuan (undangan masuk lewat tautannya sendiri); empat state sebab undangan tidak valid di SHARED-01-04 sengaja dilebur satu oleh backend (anti-enumerasi token); layar verifikasi email dan konfirmasi password hidup di backend (`verified`, `password.confirm`) tapi tidak ada di wireframe. | Penulis rencana S1: tautan undangan tidak digambar, satu pesan penolakan, dua layar Breeze dipindah ke `AuthLayout` apa adanya. |
| W4 | "Hak Akses" di SHARED-01-03 butuh label permission yang terbaca manusia; belum ada peta label permission. | S1 menampilkan nama permission mentah; peta label diputuskan di I1 (OPERATOR-08-04 butuh peta yang sama). |
| W5 | "Ditolak pada" di state penolakan SHARED-01-05 tidak punya kolom; `user_profiles` hanya menyimpan `rejection_reason`. | Tidak digambar di S1; menambah kolom waktu penolakan diputuskan di I1 (verifikasi NIDN). |
| W6 | `sidebar.md`/`shell.yaml` tidak punya item pengumuman, dan SHARED-01-01 tidak punya panel pengumuman publik. | Penulis dokumen (K5): item "Pengumuman" untuk semua pengguna login (dengan jumlah belum dibaca) dan "Kelola Pengumuman" untuk `announcements.manage`, ditaruh di grup operator dekat "Periode Pengajuan"; panel publik di sisi form login, paling banyak 5 terbaru. Dokumen UX tidak disunting. |
| W7 | "Tanggal Submit" di RESEARCHER-02-01/02-03 tidak punya kolom; `form_submissions` tidak menyimpan waktu submit. | Penulis rencana S2a: diturunkan dari entri audit `SubmitFormSubmission::AUDIT_DESCRIPTION` pertama; "Diputuskan oleh/Tanggal" di 02-12 dari entri `RejectSubmission::AUDIT_DESCRIPTION` terbaru (null untuk penolakan sebelum audit dicatat). |
| W8 | 02-01/02-03 menggambar "Buat Pengajuan Baru", ekspor (RPT-EXPORT-08/09), "Tambah Luaran", dan unduh berkas; tujuannya belum ada. Belum ada route unduh berkas jawaban (tombol legacy tanpa `href`). | S2a tidak menggambarnya: Buat Pengajuan Baru dan unduhan → S2b (wizard upload, ADR 0012), ekspor → R1 (scope sudah dijaga `SubmissionVisibility`), Tambah Luaran → O1. |
| W9 | Edit → RESEARCHER-02-04 dan Mulai Revisi → 02-05 belum ada. | S2a menautkan keduanya ke `user.form-phase` legacy lewat `editUrl` backend sampai S2c. S2b-1: `editUrl` menaut `user.submissions.wizard` untuk Draft dan NeedsRevision; route legacy dihapus. |
| W10 | Denial SM-ACCESS-03 tampil sebagai halaman 403 bawaan Laravel, bukan shell v2. | Halaman error Inertia di F99. |
| W11 | Panel evaluasi reviewer (checklist, tombol ulir) dulu hidup di detail peneliti legacy; daftar reviewer legacy menaut `user.submissions.show`. | S2a menghapusnya bersama `ShowSubmission`: reviewer tidak bisa mengevaluasi dari UI sampai S3a. Aturan REV-EVAL-04/06 tetap terikat di `ReviewThreadCreationTest`/`ReviewSummaryGateTest`; payload layarnya wajib kembali di `Review/EvaluationPage` S3a. |
| W12 | RESEARCHER-02-04 "Edit Pengajuan (Draft)" adalah wizard pada langkah 5, bukan layar tersendiri; tidak ada frame langkah 5 lain. | Penulis rencana S2b: menyunting draft = wizard; `EditPage` dicoret dari S2c. |
| W13 | ADR 0012 menulis tujuh endpoint per langkah (PATCH basic-info, PATCH scheme, POST files, …). Langkah 2, 3, dan 6 sama-sama menulis `form_field_responses` (skema & TRL lewat field `scheme_selector`/`trl_selector`, ADR 0005). | Penulis rencana S2b: ketiganya satu endpoint `POST …/wizard/{step}` dengan nomor langkah di route; langkah 4/5 memakai endpoint per baris yang sudah ada. Pembagian field ke langkah menurut tipe field (`WizardStepGate::stepForFieldType`), karena Form Engine tidak menyimpan langkah per field. |
| W14 | `trl_id` tidak pernah ditulis sebelum S2b (hanya `scheme_id` yang disinkron). | S2b menambah `SyncTrlSelection` dengan validasi izin skema (BR-SCH-05). |
| W15 | Berkas jawaban pengajuan dulu disimpan di disk `public` (`FieldAnswerWriter`), sehingga terbuka lewat `/storage/…` bagi siapa pun yang tahu path-nya, di luar BR-SM-07. | Pemilik, 2026-10-31: berkas pengajuan disimpan di penyimpanan privat (disk `local`) dan hanya dibaca lewat route unduh ber-scope; tanpa migrasi berkas lama. Pemilik, 2026-10-31 (lanjutan): unggahan biodata (`SaveBiodata`, termasuk KTP) dan lampiran review (`ReviewController`) ikut pindah ke disk privat di S2b; keduanya tanpa pembaca URL publik (biodata belum punya pembaca, lampiran review dibaca `ReviewAttachmentDownloadController` yang sudah ber-scope). Lampiran pengumuman tetap `public`. Unduh berkas biodata untuk Operator (verifikasi) dibangun di I1. |
| W16 | Banner langkah 1 "sudah memiliki pengajuan aktif pada periode dan skema yang sama" tidak bisa ditampilkan di langkah 1: skema baru dipilih di langkah 3 dan BR-SM-01 tidak menghitung DRAFT. Wireframe menulis "Tersimpan otomatis". | Penulis rencana S2b: pesannya muncul saat submit ([SM-SUBMIT-15]); wizard menyimpan setiap berpindah langkah, bukan autosave. |

Selisih wireframe↔DDD yang ditemukan saat mengerjakan irisan dicatat di sini
dengan kode `W1`, `W2`, …, beserta keputusan yang diambil.
