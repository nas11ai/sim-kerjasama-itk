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
(`navigation.unreadAnnouncements`, lazy shared prop; §9 W32).

**Isi (§9 K6, K6a).** Isi pengumuman ditulis dengan editor rich text (Tiptap,
hanya dimuat `Announcement/FormPage`) dan disimpan sebagai HTML di kolom
`content` yang sama. Server adalah batas kepercayaan:
`Support/Announcement/AnnouncementHtml::sanitize` (HTMLPurifier) menyaring isi
saat ditulis (Publish/Revise) **dan** saat dibaca, dengan daftar izin `p, br,
strong, em, u, s, h2, h3, ul, ol, li, blockquote, a[href], img[src|alt]`.
Tautan hanya `http`/`https`/`mailto` dan selalu `target="_blank"
rel="noopener noreferrer"`. Gambar hanya lewat unggahan
(`admin.announcements.images.store`: JPG/PNG, maks 2 MB dan 4096 px, disandi
ulang GD sehingga metadata EXIF hilang, nama acak); `src` hanya lolos bila
menunjuk `/storage/announcements/inline/<acak>.<jpg|png>`, jadi URL luar,
`data:`, dan path traversal dibuang. Gambar sisipan yang tak lagi dirujuk ikut
dihapus saat revisi atau penarikan. `Components/Announcement/RichTextContent.vue`
adalah satu-satunya pemakai `v-html` di v2. Daftar, panel login, dan kutipan
memakai teks polos (`AnnouncementHtml::plainText`). Lampiran tetap mengikuti
`AnnouncementRequest` (jpg/png/pdf, 2 MB).

**Di luar cakupan:** notifikasi/email saat terbit, target per form/skema atau
per user, jadwal terbit di masa depan, tabel/warna di isi. Kalau dibutuhkan,
buka keputusan baru di §9.

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
| S2b-2 | ☑ | Wizard pengajuan: langkah 4 anggota & 5 anggaran | RESEARCHER-02-08, 02-04 | `Components/Submission/Wizard/{MembersStep,BudgetStep,…}` | ◐ | `ResearchMemberManager` | S2b-1 |
| S2c | ☑ | Revisi, target luaran, audit saya (edit draft = wizard, W12) | RESEARCHER-02-05, 02-11, 07-01 | `Submission/RevisionPage` + tab Target Luaran/Riwayat di `Submission/ShowPage` (W17) | ◐ (rencana `docs/plans/2026-10-31-fe-s2c-revision.md`) | — | S2b |
| S3a-1 | ☑ | Review oleh reviewer: penugasan & evaluasi | REVIEWER-03-01, 03-03 | `Review/AssignmentIndexPage`, `Review/EvaluationPage` + `Components/Review/*` | ◐ (rencana `docs/plans/2026-10-31-fe-s3a-review.md`) | `Reviewer/Assignments/IndexPage`, `Reviewer/EvaluationForm/*`, `Reviewer/Submissions/IndexPage` | S2a |
| S3a-2 | ☑ | Review oleh reviewer: ringkasan, diskusi, riwayat | REVIEWER-03-02, 03-04 | `Review/SummaryPage`, `Review/HistoryPage` | ◐ (rencana yang sama, Task 6–9) | `Reviewer/Submissions/ShowPage` | S3a-1 |
| S4-1 | ☑ | Pengajuan sisi operator: daftar & detail baca | OPERATOR-02-01, 02-02 | `Submission/ManageIndexPage`, `ManageShowPage` + `Components/Submission/Manage/*` | ◐ (rencana `docs/plans/2026-11-01-fe-s4-operator.md`, Task 1–4) | `Submissions/*`, `AssignReviewerDialog`, `ReplaceReviewerDialog`, `RejectSubmissionDialog`, `TransferOwnershipDialog`, `ReviewSystem`, `ReviewScorePanel`, `PhaseAccessPanel` (semuanya hanya dipakai `ShowSubmission` operator) | S2a, S3a-2 |
| S4-2 | ☑ | Pengajuan sisi operator: enam aksi | OPERATOR-02-03..06, OPERATOR-03-01, 03-02 | dialog perpanjang/alihkan/tolak/tarik/tugaskan/ganti di `ManageShowPage` (W30) | ◐ (rencana yang sama, Task 5–11) | — (yatim `SubmissionEvaluationManager/*`, `ReviewerAssignments/*` pindah ke F99) | S4-1 |
| A1 | ☑ | Pengumuman: baca & kelola | tanpa frame (§4.7) | `Announcement/IndexPage`, `Announcement/ShowPage` (pembaca; tamu untuk yang publik), `Announcement/ManageIndexPage`, `Announcement/FormPage` (buat & sunting), panel pengumuman publik di halaman login (§9 K5) | ◐ permission `announcements.manage`, pivot `announcement_roles`, `VisibleAnnouncements` + filter peran, `markRead` → redirect Inertia (rencana `docs/plans/2026-11-01-fe-a1-announcement.md`; isi rich text K6/K6a) | `Announcements/*`, `AnnouncementDetail`, `User/Announcements/*` | S1 |
| D1 | ☑ | Dashboard & notifikasi | SHARED-02-01..05 | `Dashboard/IndexPage` (section per permission, §4.4, + section "Pengumuman terbaru" lewat `VisibleAnnouncements`), `Notification/IndexPage` | ◐ `DashboardOverview` + Query per section, `NotificationInbox`/`NotificationDestination`/`UnreadNotificationCount` ber-cache, `notifications.read-all`, nama dasbor lama jadi redirect (rencana `docs/plans/2026-11-01-fe-d1-dashboard.md`; W37–W46) | `DashboardPage`, `User/DashboardPage`, `Reviewer/DashboardPage`, `Notifications/*` | S2a, S3a, S4, A1 |
| I1-1 | ☑ | User, deaktivasi, sesi | OPERATOR-08-01, 08-03 | `Identity/UserIndexPage`, `Identity/UserShowPage` + `Components/Identity/*` | ◐ `UserPolicy`, route dijaga `users.verify`/`users.manage` (W47), Action peran & hak akses beraudit, deaktivasi mengakhiri sesi & menutup penugasan baru (W59), sesi & kehadiran BR-IAM-21, pengaktifan kembali BR-IAM-20 (rencana `docs/plans/2026-11-02-fe-i1-identity.md`, Task 1–5d) | `Users/*` | F0 |
| I1-2 | ☑ | Verifikasi NIDN, undangan, reviewer | OPERATOR-08-02, 08-04, ADMIN-08-01 | `Identity/VerificationIndexPage`, `Identity/InvitationIndexPage`, `Identity/ReviewerIndexPage` | ◐ koreksi NIDN (IAM-VERIFY-13/14), berkas biodata (W15), undangan oleh Operator + preset hak akses (W47/W51), route reviewer dijaga `reviewers.assign` (rencana yang sama, Task 6–12) | `Identity/VerificationQueuePage`, `Identity/InvitationsPage`, `Reviewers/*` | I1-1 |
| P1 | ☑ | Profil | SHARED-01-06, 01-07 | `Profile/CompletePage`, `Profile/ShowPage` + `Components/Profile/*` | ◐ satu definisi kelengkapan `UserProfile::missingDetails()` (BR-IAM-03), Action `CompleteProfile`/`UpdateOwnProfile`/`ReplaceProfilePhoto`/`ChangeOwnPassword` beraudit, foto privat (W65), pintu dari wizard, pasca-login, dan dasbor; biodata Form-Engine, `check_biodata`, dan berkas KTP dihapus (W61) (rencana `docs/plans/2026-11-02-fe-p1-profile.md`) | `Profile/*`, `User/Biodata/*`, `BiodataController`, `CheckBiodata`, pembaca berkas biodata W58 | S1 |
| E1 | ☑ | Periode pengajuan | OPERATOR-09-01 | `FormEngine/PeriodIndexPage` + `Components/FormEngine/*` (dialog buat/sunting, tutup, hapus) | ◐ `FormEngine\SubmissionPeriodController` dijaga `periods.manage` (K1), Query `SubmissionPeriodList`/`PeriodDraftOwners`, enum `PeriodStatus` dari `SubmissionPeriodGate::displayStatus`, Action `CloseSubmissionPeriod` (Close Early, BR-SCH-07/BR-SM-10), `ExtendSubmissionDates` → `DeadlineExtension` (W73), label tanggal teks bebas (W72) (rencana `docs/plans/2026-11-02-fe-e1-period.md`; W67–W75) | `SubmissionPeriods/*`, `SubmissionPeriodController` legacy, `StoreSubmissionDateLabelController` | F0 |
| E1b | ☑ | Jenis tenggat tetap (keputusan pemilik 2026-11-03) | OPERATOR-09-01 | — (dialog E1 memakai jenis, bukan label bebas) | ◐ besar: `DeadlineType` × siklus menggantikan `submission_date_labels`, `FormPhaseDetail.evaluation_date_id`/`revision_date_id`, gerbang membaca jenis (BR-FE-01/17), override per jenis oleh pemegang izin (rencana `docs/plans/2026-11-03-fe-e1b-deadline-kind.md`, K8a–K8g disetujui pemilik 2026-11-03) | `SubmissionDateLabel`, `SubmissionDateLabelSeeder`, `FormBuilderPeriodInput`; builder legacy hanya memilih periode (K8g); `Pages/FormPhases/*` mendapat `PhaseDeadlineSelect` | E1 |
| E2 | ☑ | Form: daftar, general & fields, access control | ADMIN-08-05..07 | `FormEngine/FormIndexPage`, `FormEngine/FormEditPage` (tab = route, W86) + `Components/FormEngine/*` (`FormTable`, `FormGeneralTab`, `FormFieldList` seret + Naik/Turun W82, `FormFieldDialog`, `FormFieldOptions`, `FormAccessTab`, `AffectedDraftsNotice`, `FormDeleteBlockedDialog`) | ◐ besar: `FormEngine\FormController` + `FormAccessController` dalam grup `['auth','can:schemes.manage']` (W88), Query `FormDirectory`/`FormGeneral`/`FormAccessRules`/`FormTypeOptions`/`FieldTypeOptions`/`OrganizationOptions`, Action `SetFormActive` (beraudit), `CreateForm`/`ReviseForm`/`DeleteForm`/`GrantFormAccess`/`RevokeFormAccess` dipakai ulang, Data `FormRowData`/`FormGeneralData`/`FormFieldEditData`/`ArchivedFieldData`/`FormAccessRuleData`/`FormHeaderData`, gerbang FE-DELETE-05 di `StartSubmission` + `ResearcherStartOptions` (W77), tipe field terkunci bila ada jawaban (BR-FE-23); wizard `FormBuilder`, duplikat/hapus massal Form, daftar kontrol akses global, endpoint `api.study-programs`, dan redirect `/forms` + `/form-access-controls` dihapus (W79) (rencana `docs/plans/2026-11-03-fe-e2-form.md`; W76–W90) | `Forms/*`, `FormAccessControls/*`, `FormBuilder/*`, `CompleteFormBuilderController`, `FormController`/`FormAccessControlController` legacy, `BuildCompleteForm`, `DuplicateForm`, `*InBulk`, `ReviseFormAccess`, `FormAccessControlDirectory`, `FormBuilderRequest` | E1b |
| E3-1 | ☑ | Form: tab Phases & sunting tahap | ADMIN-08-08, 08-09 (kartu Tahapan Form) | tab Phases di `FormEditPage` (`FormPhaseTab`, `PhaseCard`), `FormEngine/PhaseEditPage` + `Components/FormEngine/Phase*` | ◐ besar: `FormEngine\FormPhaseController` dalam grup `can:schemes.manage` (W88), tahap milik Form lewat aturan akses detailnya (W92), Query `FormPhaseCards`/`PhaseEdit`/`PhaseAccessOptions`/`LinkablePeriods`/`PhaseDeadlineGaps`, gerbang tahap nonaktif (K9a), gerbang periode pemilik tenggat di `StartSubmission`/`ResearcherStartOptions` + `LinkPhaseToPeriod` (K9b, menutup W99), penjaga `needs_review` (W97) dan BR-FE-22 saat detail dibuang (rencana `docs/plans/2026-11-03-fe-e3-phase.md`, Task 1–5; W92–W103) | `FormPhases/*`, `FormPhaseController` legacy, `FormPhaseDirectory`, `FormPhaseFormOptions`, `FacultyOptions` | E2 |
| E3-2 | ☑ | Form: form evaluasi reviewer di sunting tahap | ADMIN-08-09 (kartu Form Evaluasi) | `Components/FormEngine/Evaluation*` (`EvaluationSection`, `EvaluationFormCard`, `EvaluationFieldDialog`, `EvaluationFormLifecycle`) di `PhaseEditPage`, `Components/app/SortableRows` (diekstrak dari `FormFieldList`) | ◐ `FormEngine\EvaluationFormController` (route ber-detail: `phase-details/{detail}/evaluation-forms`, `evaluation-forms/{form}`; tanpa pindah detail W100), Query `PhaseEvaluationForms`, `limitType` pada `FieldTypeOptions` (W103), kunci `needs_review` selama ada form evaluasi aktif di layar (W97), hapus legacy evaluasi (rencana yang sama, Task 6–9). Cek browser Playwright 9/9 di `simpas_browser` (tab Phases, dialog hapus berangka, hubungkan periode + peringatan, sunting tenggat, form evaluasi + field, form evaluasi tertugaskan) | `ReviewEvaluationForms/*`, `ReviewEvaluationFormController`, `FormPhases/*` evaluasi, duplikat/urut ulang/pratinjau form evaluasi (W100) — yatim `EvaluationForms/ShowPage` pindah ke F99 | E3-1 |
| C1-1 | ☑ | Skema & konfigurasi umum | ADMIN-08-03, 08-04, 08-10 | `Scheme/IndexPage`, `Scheme/FormPage`, `SystemConfig/GeneralPage` + `Components/Scheme/*` (`SchemeTable`, `SchemeMainRulesCard`, `SchemeExtraRulesCard`, `TrlPicker`), `Components/SystemConfig/*` (`ReferenceCategoryCard`, `ReferenceItemsDialog`, `ReferenceItemRow`, `ReferenceItemCreateCard`, `NotificationChannelCard`) | ◐ `Scheme\SchemeController` dan `/admin/reference` dalam grup `can:schemes.manage` (W105), `SchemeRequest` + `SchemeRuleCatalog` (aturan bertipe W107; `rules` tak lagi terhapus saat sunting W108, `[SCH-RULE-17]`), `ActivateScheme` beraudit (W109), Query `SchemeDirectory`/`SchemeFormOptions`/`ReferenceCategories`/`ReferenceEntityUsage` (W115)/`NotificationChannelDeclarations`, Action `DeclareNotificationChannels` (≥ 1 channel, `[NOT-CHANNEL-07]`, W113), `ReferenceEntityRequest` (W114), `Support\Search\ActiveStatusFilter` dipakai bersama `FormDirectory`, `max.mixed` 19 → 17. Cek browser Playwright 7/7 di `simpas_browser` (daftar + filter + Nonaktifkan terkunci, nonaktif/aktifkan, sunting nama tanpa kehilangan `rules`, kode bentrok di field, hapus tipe luaran terkunci, ganti nama + aktifkan, channel) (rencana `docs/plans/2026-11-07-fe-c1-config.md`, Task 1–4; W105–W115) | `Schemes/IndexPage`, `Config/ReferenceEntitiesPage`, `SchemeController` legacy, `CountReferenceEntityUsage` | F0 |
| C1-2 | ☑ | Struktur organisasi | ADMIN-08-02 | `SystemConfig/OrganizationPage` + `Components/SystemConfig/*` (`OrganizationTree`, `OrganizationUnitPanel`, `OrganizationUnitDialog`, `OrganizationLifecycleDialogs`, `MoveUserDialog`), `lib/organizationTree.ts` | ◐ `Identity\OrganizationController` dalam grup `can:users.manage` (W105) dengan `index` baru (prop `selected` dan `memberCandidates` opsional lewat partial reload), Query `OrganizationTree` (unit nonaktif dan data bersiklus tetap tampil)/`OrganizationCensus` (satu definisi untuk layar dan penjaga)/`OrganizationMembers` (W120), subtree struktural `OrganizationSubtree` menggantikan `subtreeIds()` di `DeleteOrganization`/`DeactivateOrganization`/penjaga siklus `SaveOrganization` (W116; `[IAM-ORG-02]` dan `[IAM-ORG-13]` lewat unit nonaktif), induk nonaktif ditolak (W117, `[IAM-ORG-16]`), `OrganizationRequest` + `OrganizationTypeCatalog`, `MoveUserRequest` (`user_id` bukan angka kini 422, sebelumnya 500). Cek browser Playwright 6/6 di `simpas_browser` (buka/tutup cabang + pilih unit, FTI terkunci dengan hint angka, tambah sub-unit, pindahkan dua user lalu nonaktifkan, Teknik Kimia nonaktifkan-ya hapus-tidak, hapus unit + cabut 2 undangan) (rencana `docs/plans/2026-11-07-fe-c1-config.md`, Task 5–8; W116–W120) | `OrganizationController` legacy (tanpa halaman) | C1-1 |
| C1-3 | ☑ | Wewenang & aktifkan kembali organisasi | ADMIN-08-02 | `OrganizationUnitPanel` + `OrganizationLifecycleDialogs` (aksi "Aktifkan Kembali") | ✓ permission baru `organizations.manage` (Admin + Operator) menggantikan `users.manage` di ketujuh route organisasi, `SaveOrganization`/`MoveUserToOrganization`, dan navigasi (W105); Action `ReactivateOrganization` + Query `RevivableInvitations` (BR-IAM-22 baru, `[IAM-ORG-17..20]`, W119); DDD identity v2.6. Cek browser Playwright 4/4 sebagai Operator (rencana `docs/plans/2026-11-07-fe-c1-3-org-authority.md`) | — | C1-2 |
| M1-1 | ☑ | Monev: definisi siklus & layar peneliti | RESEARCHER-05-01..03 | `Monev/IndexPage`, `Monev/ReportFormPage`, `Monev/ReportShowPage` + `Components/Monev/{PhaseProgression,MonevSubmissionCard}`, `lib/monev`, `composables/useMonevReport` | ✗ besar: Queries `Monev/{MonevCycles,ResearcherMonevList,HasMonevSubmission,MonevReportForm,MonevReportDetail}` (W125/W128), Actions `Monev/{SaveMonevReport,SubmitMonevReport}` + `MonevReportRequest` (W121/W122), `MonevController`/`MonevReportController`, Policy `startMonevReport`, `FormSubmission::teamSubmissionId` (W123/W124), `TransferSubmissionOwnership` memindah laporan (W134), `SubmissionPeriodGate::isForceClosed` hanya tahap pengajuan + amandemen BR-SCH-07/BR-SM-10/13 v2.5 (W135) (rencana `docs/plans/2026-11-07-fe-m1-monev.md`, Task 1–4 + 3b) | `ChildSubmissionController`, `ChildReportLauncher.vue` | S2a |
| M1-2 | ☑ | Monev: layar operator | OPERATOR-05-01, 05-02 | `Monev/ManageIndexPage`, `Monev/ManageShowPage` + `Components/Monev/{MonevBoardTable,ManagedCycleCard,AssignCycleReviewerDialog}`, `ReviewerPicker` prop `suggested` | ◐ Queries `Monev/{MonevCycleBoard,MonevCycleManagement}` + `MonevCycles::whereHasCycles`, enum `MonevBoardStatus`, kolom `form_submissions.cycle_opened_at` + `OpenMonevCycle` memastikan laporan dan menolak pembukaan kedua (W126), Action `AssignCycleReviewer` + `AssignCycleReviewerRequest`, Policy `assignMonevReviewer`, `MonevManageController` (route `monev.manage.{index,show,open,reviewers.store}` dijaga `submissions.view-all`, W129), `admin.submissions.show` laporan anak → `monev.manage.show`, notifikasi `monev_stage_opened` Operator → `monev.manage.show` (W130), kandidat siklus tanpa laporan (W136), galat non-field isi laporan (W137) (rencana yang sama, Task 5–7) | `Monev/CyclePage`, `MonevCycleController`, route `admin.submissions.monev.{show,open}` | M1-1 |
| O1-1 | ☑ | Luaran: kebenaran backend (siapa, kapan, isi, berkas) | RESEARCHER-06-02 (aturan) | — | ◐ jalan staf `outputs.manage` ∧ `submissions.view-all` + Operator memegang `outputs.manage` (W138), jenis tenggat `output` "Batas Akhir" + `OutputUploadDetail` menggerbangi luaran, dikecualikan dari `MonevCycles` (W139), `OutputTypeDefinitionSeeder` + spesifikasi field (W140), unggahan sungguhan ke disk privat + route unduh ber-scope (W141) (rencana `docs/plans/2026-11-08-fe-o1-output.md`, Task 1–4; W138/W139 disetujui pemilik 2026-11-08: identity v2.7, form engine v2.4, research output v2.5; skenario `[RO-OUTPUT-25..28]`, `[MON-CYCLE-18]`; `limitKind` → `limitType` atas permintaan pemilik) | — | S2a, M1 |
| O1-2 | ☑ | Luaran: layar | RESEARCHER-06-01..03 | `ResearchOutput/{IndexPage,FormPage,ShowPage}` + `Components/ResearchOutput/{OutputGroupCard,OutputRow,OutputTypeStep,OutputMetadataFields,OutputFileInput,OutputFileList,ManagedOutputsTab}`, `composables/useOutputForm`, `formatFileSize`, tab "Luaran (n)" di `Submission/ManageShowPage`, "Luaran Terdaftar" di `WithdrawDialog` | ◐ Queries `ResearchOutput/{ResearcherOutputList,HasOutputSubmission,OutputWriteAccess,OutputRows,ResearchOutputForm,ResearchOutputDetail}` + `Submission/TeamParentSubmissions` (diekstrak dari Daftar Monev), `ResearchOutput\ResearchOutputController` (route `research-outputs.*`), `OperatorSubmissionDetail` props `outputs`/`outputAccess`/`outputPhaseAccess`/`outputOverrideTarget` + `overrideTargetFor`, `OutputFieldSpecData::displayValue` (W142–W147) (rencana yang sama, Task 5–8) | `HasApprovedSubmission`, `Submission\ResearchOutputController`, route `user.submissions.outputs.*` (yatim `ResearchOutputManager` pindah ke F99) | O1-1 |
| R1-1 | ☑ | Statistik: basis & tab Ringkasan | OPERATOR-07-01 | `Reporting/StatsPage` (tab = route `reporting.stats/{tab?}`, W156) + `Components/Reporting/{StatsTabs,StatsFilterBar,StatsOverviewSection,StatusBreakdownCard,RankedBarList,ColumnChartCard,ChartTableToggle}`, `Components/app/KpiGrid` (dipindah dari Dashboard), `lib/{chartPalette,statistics}` | ◐ Gate `view-statistics` (K1, W148), `ReportingSubmissions::base` + filter `year` di `OperatorSubmissionList` (W149), `StatisticsOverview` + KPI W150, bentuk grafik W151 (rencana `docs/plans/2026-11-08-fe-r1-reporting.md`, Task 1–4) | `Statistics/*`, `StatController`, route `admin.stats.*`, Query statistik legacy | D1 |
| R1-2 | ☑ | Statistik: Saat Ini, Riwayat, Tindak Lanjut (permintaan pemilik 2026-11-08) | OPERATOR-07-01 | tab `now`, `history`, `follow-up` di `Reporting/StatsPage` + `Components/Reporting/{NowSection,StageBacklogList,HistorySection,SchemePerformanceTable,FollowUpSection,FollowUpListCard}`, `lib/statistics::formatSinceSubmit` | ◐ Queries `StageBacklog`, `MonevStatusCounts` (W153), `ReviewerWorkload`, `ReviewerCoverage`, `MonthlyTrend`, `SchemePerformance`, `FollowUpLists`, `KpiDefinitions` (W150 bersama), `StatisticsFilters::withoutYear` (W152) (rencana yang sama, Task 5–7) | — | R1-1 |
| R1-3 | ☑ | Export data | OPERATOR-07-02 | `Reporting/ExportPage` + `Components/Reporting/{ExportRequestCard,ExportHistoryTable,ExportJobStatus,ExportJobActions}`, `lib/exportJob` | ◐ lima `ExportDefinition` berkolom DDD (`rowCount`/`rows`), `ExportFilters`, `ExportFormat` xlsx/csv (kolom `export_jobs.format`), `ExportFormOptions`, `ExportParameters`, route `reporting.exports.*` (W154; `[RPT-EXPORT-12/13]`) (rencana yang sama, Task 8–10) | `Exports/IndexPage`, `App\Exports\SubmissionSummaryExport`, route `exports.*` | R1-1 |
| R1-4 | ☑ | Audit log | OPERATOR-07-03 | `Reporting/AuditPage` + `Components/Reporting/{AuditTable,AuditEntryDialog,AuditChangeList}`, slot di `Components/app/FilterToolbar` (Waktu tanpa "semua") | ◐ kolom `activity_log.ip_address` lewat `Context` tersembunyi (`CaptureAuditIp`, `[RPT-AUDIT-08]`), `AuditLogList` + `AuditFilters` + `AuditSubjectLabel` + `AuditActorLabel` (juga dipakai `AuditLogExport`), route `reporting.audit.index` (W155) (rencana yang sama, Task 11–13) | `Audit/IndexPage`, `AuditLogController`, route `audit-log.index` | R1-3 |
| F99-1 | ☑ | Bersih-bersih: penghapusan | — | — | ◐ `/` dialihkan (W157); route review legacy dan penugasan form terpisah dihapus, test berskenario dipindah ke route v2, kartu form evaluasi memberi tahu pengajuan yang tetap memakai form lama (W158, W159); jalur simpan `user.form-submission.*` dihapus + `SubmissionAnswersRequest` dilebur ke `WizardStepRequest` (W160); BR-FE-14 untuk jawaban (`touch()`, W161); `roles`/`is_reviewer`/`flash` legacy dari shared props, `Auth.user` nullable, paket `pinia` dilepas (W162) (rencana `docs/plans/2026-11-08-fe-f99-cleanup.md`, Task 1–4) | pulau legacy utuh: `AuthenticatedLayout`, 23 `Components/*.vue` akar, `Constants/`, `Utils/`, `stores/`, `Roles/*`, `Permissions/*`, `WelcomePage` + `PublicAnnouncementFeed`, yatim `SubmissionEvaluationManager/*`, `ReviewerAssignments/*`, `EvaluationForms/ShowPage`, `ResearchOutputManager.vue`; `RoleController`, `PermissionController`, `ReviewerFormAssignmentController`; redirect W22/W46/W61; `ui/dialog/DialogScrollContent`; `max.legacy.files` → 1 | semua |
| F99-2 | ☑ | Bersih-bersih: kebenaran & penutupan | — | `Error/ErrorPage` (W10 → W163), toast di `AuthLayout` | ◐ `App\Http\ErrorPage` di `withExceptions` + route fallback ber-middleware `web` (W163); `review_summaries.author_reviewer_id` (`restrictOnDelete`, diisi sekali saat dibuat) + "diwarisi {pengganti}" di kartu ulir (W24 → W164, [REV-DISCUSS-06]); temuan audit E2E-06..10, `config/trustedproxy.php` (W165); evaluasi yang masuk memutuskan hasil review (W166, [REV-OUTCOME-01/02/04]); `SaveMonevReport` menggeser cap kunci (W161); `min-w-0` pada `SidebarInset` + satu `<main>` + `PageHeader` aksi `flex-wrap` (E2E-01); `EndToEndFlowSeeder` bisa menjalankan alur penuh (W167) + smoke Playwright alur kritis §5 `E2E_FIXTURE=full-flow` (rencana yang sama, Task 5–8) | — | F99-1 |
| RT1 | ☐ | Realtime: logout seketika & kehadiran langsung | — | — (tanpa halaman baru; `UserShowPage`/`UserIndexPage` membaca kehadiran dari sumber baru) | ✗ Laravel Reverb + `laravel-echo`: kanal privat per user (event deaktivasi/akhiri sesi → klien keluar seketika), kanal presence menggantikan pembacaan kehadiran `SessionRegistry` (§9 K7) | — | F99, I1-1 |

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
| W4 | "Hak Akses" di SHARED-01-03 butuh label permission yang terbaca manusia; belum ada peta label permission. | S1 menampilkan nama permission mentah; peta label diputuskan di I1 (OPERATOR-08-04 butuh peta yang sama). **Ditutup I1:** `PermissionCatalog` memberi label manusia, dipakai di detail user, undangan, dan SHARED-01-03. |
| W5 | "Ditolak pada" di state penolakan SHARED-01-05 tidak punya kolom; `user_profiles` hanya menyimpan `rejection_reason`. | Tidak digambar di S1; menambah kolom waktu penolakan diputuskan di I1 (verifikasi NIDN). **Ditutup I1 (W53):** diturunkan dari entri audit penolakan terbaru, tanpa kolom baru; tampil di 08-02 dan SHARED-01-05. |
| W6 | `sidebar.md`/`shell.yaml` tidak punya item pengumuman, dan SHARED-01-01 tidak punya panel pengumuman publik. | Penulis dokumen (K5): item "Pengumuman" untuk semua pengguna login (dengan jumlah belum dibaca) dan "Kelola Pengumuman" untuk `announcements.manage`, ditaruh di grup operator dekat "Periode Pengajuan"; panel publik di sisi form login, paling banyak 5 terbaru. Dokumen UX tidak disunting. |
| W7 | "Tanggal Submit" di RESEARCHER-02-01/02-03 tidak punya kolom; `form_submissions` tidak menyimpan waktu submit. | Penulis rencana S2a: diturunkan dari entri audit `SubmitFormSubmission::AUDIT_DESCRIPTION` pertama; "Diputuskan oleh/Tanggal" di 02-12 dari entri `RejectSubmission::AUDIT_DESCRIPTION` terbaru (null untuk penolakan sebelum audit dicatat). |
| W8 | 02-01/02-03 menggambar "Buat Pengajuan Baru", ekspor (RPT-EXPORT-08/09), "Tambah Luaran", dan unduh berkas; tujuannya belum ada. Belum ada route unduh berkas jawaban (tombol legacy tanpa `href`). | S2a tidak menggambarnya: Buat Pengajuan Baru dan unduhan → S2b (wizard upload, ADR 0012), ekspor → R1 (scope sudah dijaga `SubmissionVisibility`), Tambah Luaran → O1. |
| W9 | Edit → RESEARCHER-02-04 dan Mulai Revisi → 02-05 belum ada. | S2a menautkan keduanya ke `user.form-phase` legacy lewat `editUrl` backend sampai S2c. S2b-1: `editUrl` menaut `user.submissions.wizard` untuk Draft dan NeedsRevision; route legacy dihapus. |
| W10 | Denial SM-ACCESS-03 tampil sebagai halaman 403 bawaan Laravel, bukan shell v2. | Halaman error Inertia di F99. |
| W11 | Panel evaluasi reviewer (checklist, tombol ulir) dulu hidup di detail peneliti legacy; daftar reviewer legacy menaut `user.submissions.show`. | S2a menghapusnya bersama `ShowSubmission`: reviewer tidak bisa mengevaluasi dari UI sampai S3a. Aturan REV-EVAL-04/06 tetap terikat di `ReviewThreadCreationTest`/`ReviewSummaryGateTest`; payload layarnya wajib kembali di `Review/EvaluationPage` S3a. S3a-1: evaluasi kembali lewat `Review/EvaluationPage`; gerbang ulir (`canCreateThread`) menyusul di `Review/SummaryPage` S3a-2. S3a-2: `canCreateThread` kembali di `Review/SummaryPage` — W11 tertutup. |
| W12 | RESEARCHER-02-04 "Edit Pengajuan (Draft)" adalah wizard pada langkah 5, bukan layar tersendiri; tidak ada frame langkah 5 lain. | Penulis rencana S2b: menyunting draft = wizard; `EditPage` dicoret dari S2c. |
| W13 | ADR 0012 menulis tujuh endpoint per langkah (PATCH basic-info, PATCH scheme, POST files, …). Langkah 2, 3, dan 6 sama-sama menulis `form_field_responses` (skema & TRL lewat field `scheme_selector`/`trl_selector`, ADR 0005). | Penulis rencana S2b: ketiganya satu endpoint `POST …/wizard/{step}` dengan nomor langkah di route; langkah 4/5 memakai endpoint per baris yang sudah ada. Pembagian field ke langkah menurut tipe field (`WizardStepGate::stepForFieldType`), karena Form Engine tidak menyimpan langkah per field. |
| W14 | `trl_id` tidak pernah ditulis sebelum S2b (hanya `scheme_id` yang disinkron). | S2b menambah `SyncTrlSelection` dengan validasi izin skema (BR-SCH-05). |
| W15 | Berkas jawaban pengajuan dulu disimpan di disk `public` (`FieldAnswerWriter`), sehingga terbuka lewat `/storage/…` bagi siapa pun yang tahu path-nya, di luar BR-SM-07. | Pemilik, 2026-10-31: berkas pengajuan disimpan di penyimpanan privat (disk `local`) dan hanya dibaca lewat route unduh ber-scope; tanpa migrasi berkas lama. Pemilik, 2026-10-31 (lanjutan): unggahan biodata (`SaveBiodata`, termasuk KTP) dan lampiran review (`ReviewController`) ikut pindah ke disk privat di S2b; keduanya tanpa pembaca URL publik (biodata belum punya pembaca, lampiran review dibaca `ReviewAttachmentDownloadController` yang sudah ber-scope). Lampiran pengumuman tetap `public`. Unduh berkas biodata untuk Operator (verifikasi) dibangun di I1. **P1 (W61):** biodata Form-Engine dan unggahannya dihapus; aplikasi tidak menyimpan KTP. |
| W16 | Banner langkah 1 "sudah memiliki pengajuan aktif pada periode dan skema yang sama" tidak bisa ditampilkan di langkah 1: skema baru dipilih di langkah 3 dan BR-SM-01 tidak menghitung DRAFT. Wireframe menulis "Tersimpan otomatis". | Penulis rencana S2b: pesannya muncul saat submit ([SM-SUBMIT-15]); wizard menyimpan setiap berpindah langkah, bukan autosave. |
| W17 | RESEARCHER-02-11 dan 07-01 menggambar tab detail pengajuan, bukan layar sendiri. | Penulis rencana S2c: dibangun sebagai tab `Submission/ShowPage`; `TargetOutputPage`/`AuditPage` dicoret. Riwayat sistem untuk Operator tetap `Reporting/AuditPage` di R1. |
| W18 | Panel "Edit Pengajuan" 02-05 menggambar field inline dan "Kirim Ulang aktif setelah ada perubahan"; tak ada BR yang mensyaratkan perubahan sebelum resubmit (SM-SUBMIT-12/19 hanya menuntut field wajib). | Penulis rencana S2c: penyuntingan revisi = wizard (seperti W12), panel menaut langkahnya; Kirim Ulang hanya dinonaktifkan oleh field wajib kosong dan blok akun. |
| W19 | Entri audit target luaran bersubjek target, sehingga `AuditScope` tidak menunjukkannya ke tim pengajuan, dan penyuntingan target hanya tercatat di log `default`. | Penulis rencana S2c: subjeknya pengajuan (aggregate root), penyuntingan dicatat `AuditTrail` dengan nilai lama/baru (BR-SM-15, BR-RPT-05/09). Entri lama di DB dev tidak dimigrasi. |
| W20 | FE-WINDOW-02 hanya terikat di `FormPhaseDetail::isWithinDeadline`; simpan/kirim evaluasi lewat tenggat diterima backend (`LockEvaluationForm` tak pernah dipanggil). | Penulis rencana S3a: Policy `evaluate` menolak penugasan form yang `isOverdue()`, dengan tenggat `ReviewerFormAssignment.due_date`; membaca tetap lewat `view`. Form yang dimulai reviewer sendiri bertenggat tanggal terakhir periodenya (`Review\EvaluationDueDate`), jadi periode tanpa "Batas Evaluasi" (BR-FE-17) menutup evaluasi begitu batas submit lewat — data periode wajib mematuhi BR-FE-17. |
| W21 | REVIEWER-03-01 menggambar kartu per pengajuan; legacy mendaftar per form evaluasi (dengan statistik, filter status, urutan tenggat). Field evaluasi `checkbox` legacy berupa pilihan ganda dipisah koma. | Penulis rencana S3a: satu kartu per `SubmissionReviewer` aktif, tanpa paginasi; form per pengajuan menjadi tab di 03-03; `ReviewerAssignmentList`/`ReviewerSubmissionList` dihapus. `checkbox` evaluasi mengikuti Form Engine (satu kotak ya/tidak, `WizardFieldInput`). Opsi tanpa `value` memakai label sebagai nilai, seperti jawaban lama. Halaman evaluasi tidak membuat draft saat dibuka; draft lahir saat menyimpan. |
| W22 | Route legacy `reviewer.evaluation-form.show/submitted` dan `reviewer.submissions.index` masih ditaut halaman legacy lain (`ReviewSystem.vue` operator, `AppSidebar`, dasbor peneliti). | S3a-1: nama route dipertahankan sebagai pengalihan ke halaman v2. Admin yang membuka lembar evaluasi dari `ReviewSystem.vue` kini mendapat 403 di halaman reviewer; bacaan lembar evaluasi untuk Operator dibangun di S4 (OPERATOR-03-*). **Ditutup S4-1/S4-2:** Operator membaca lembar evaluasi (termasuk yang `replaced`) di tab Review `Submission/ManageShowPage`. |
| W23 | REVIEWER-03-02 menggambar kolom "Skor" dan filter "Tipe"; form evaluasi tidak punya skor total dan tipe konteks (proposal/monev) tidak tersimpan sebagai kolom. | S3a-2: keduanya tidak digambar. "Konteks" = judul fase · nama periode, jadi dua siklus monev satu fase hanya dibedakan tanggalnya. Baris `replaced` tanpa tautan (BR-REV-10). `reviewer.submissions.show` menjadi pengalihan ke `reviewer.assignments.summary` sampai `Reviewer/DashboardPage` diganti D1. |
| W24 | REV-DISCUSS-06: "penulis asli ReviewSummary tetap terbaca Sari". `ReplaceReviewer` memindahkan `reviewer_id` ulir terbuka ke pengganti, dan `ReviewThreadData.reviewer` dibaca dari kolom itu, jadi ulir warisan tampil atas nama pengganti di layar peneliti dan reviewer. | Terbuka, untuk owner: penulis asli butuh kolom sendiri (mis. `author_reviewer_id`) atau dibaca dari jejak audit. Belum diubah di S3a-2. |
| W25 | Perpanjang Tenggat (02-03) menggambar "Reviewer Terdampak", tapi override (ADR 0014) hanya membuka jendela fase peneliti; evaluasi reviewer yang lewat tenggat tetap tertutup (W20). | Pemilik, 2026-11-01: tetap satu mekanisme. `FormSubmissionOverride` yang berlaku pada detail pemilik form evaluasi juga membuka kembali evaluasi yang lewat tenggat (`ReviewerFormAssignment::isClosedByDeadline`); tanpa override FE-WINDOW-02 tetap berlaku (FE-WINDOW-05/06/07). |
| W26 | 02-04, 02-06, 03-02 menggambar kolom alasan/catatan; Action-nya tidak menerima alasan, dan "Kategori" di 02-05 tidak punya daftar di DDD. | Pemilik, 2026-11-01: alasan pengalihan dan penarikan wajib, catatan penggantian opsional; disimpan di entri audit Action-nya (`properties.new.reason` / `.note`), tanpa kolom baru (SM-OWNER-05/06, SM-WITHDRAW-06/07, REV-REASSIGN-04/05). Kategori tidak digambar. |
| W27 | 03-01 menggambar tombol "Hapus" per reviewer dan "Konfirmasi" yang menunggu jumlah minimum. | S4-2: tidak ada jalur menghapus penugasan (`RemoveReviewerEndpointRemovedTest`, BR-REV-10 → ganti), dan penugasan bertahap (REV-ASSIGN-01/02) — "Tugaskan" aktif sejak satu dipilih, indikator "n dari minimal m" tetap tampil. |
| W28 | 02-01 menggambar filter "Organisasi" dan "Tanggal: 30 hari". | S4-1: tidak dibangun; daftar hanya pengajuan induk terkirim (laporan monev milik M1). |
| W29 | 02-06 menggambar "Siklus Monev Aktif" dan "Luaran Terdaftar" di ringkasan penarikan. | S4-2: tidak digambar (belum ada read model monev/luaran operator, M1/O1); ringkasan = Judul, Ketua, Skema. |
| W30 | Frame 02-03..06, 03-01, 03-02 berupa halaman tersendiri. | S4-2: dibangun sebagai dialog di `ManageShowPage` (§8), dengan kerangka bersama `Manage/ActionDialog`. |
| W31 | 02-02 tidak menggambar aksi sunting isi untuk Super Admin, padahal SM-SUBMIT-14 menyebut Super Admin "bisa menyunting isi". | S4: tidak digambar; aturannya tetap terikat di Policy/Pest. Wizard untuk Super Admin bukan pemilik masih 409 saat menyimpan (catatan S2b). |
| K6 | Isi pengumuman berupa teks biasa (§4.7 lama) dirasa kurang untuk pengumuman berformat. | Pemilik, 2026-11-01: isi ditulis dengan editor rich text. Penulis rencana A1: Tiptap 3 hanya di `FormPage`, HTML tersaring HTMLPurifier saat tulis dan baca, `v-html` hanya di `RichTextContent.vue`; HTMLPurifier (sudah terpasang lewat phpspreadsheet) dijadikan dependency langsung tanpa mengubah versinya. Data dev teks biasa tidak dimigrasi. |
| K6a | Editor juga perlu gambar dan tautan, "dalam batas kepatuhan keamanan dan praktik terbaik". | Pemilik, 2026-11-01 (saat eksekusi A1): gambar hanya lewat unggahan ber-permission `announcements.manage` (JPG/PNG, maks 2 MB/4096 px; WebP ditolak karena GD tidak menjamin penyandiannya), disandi ulang GD (EXIF/GPS hilang), bernama acak, diaudit; saringan hanya meloloskan `src` gambar sisipan milik sendiri; berkas yang tak lagi dirujuk dihapus saat revisi/penarikan. Unggahan yang tidak pernah disimpan ke pengumuman tertinggal di disk (tercatat di audit); pembersihannya belum dibangun. Tautan `http`/`https`/`mailto` dengan `target="_blank" rel="noopener noreferrer"`. |
| K7 | Pemilik ingin akun yang dinonaktifkan langsung keluar dari aplikasi dan user aktif terpantau langsung. | Pemilik, 2026-11-02: BR-IAM-20 (pengaktifan kembali) dan BR-IAM-21 (sesi & kehadiran) ditambahkan ke DDD bersama skenarionya di I1-1. Penegakan yang mengikat ada di server (setiap request memeriksa `is_active`; sesi database dan remember-me dicabut saat deaktivasi). Polling ditolak karena memperpanjang sesi tanpa batas. Dorongan seketika ke tab yang diam dan kehadiran presence lewat Laravel Reverb menjadi irisan **RT1** setelah F99. |
| K8 | Label tenggat teks bebas (W72) tak berarti apa pun bagi gerbang: tenggat reviewer = Batas Submit peneliti di jalur produksi (BR-FE-17 tak tertegakkan), Batas Revisi tak punya pemakai, dan "Dibuka" menunggu seluruh tanggal lewat. | Pemilik, 2026-11-03: jenis tenggat tetap, irisan E1b sebelum E2, "tanggalnya tetap bisa di-override oleh user yang punya izin". Pemilik menamai kelasnya `DeadlineType` (bukan `DeadlineKind`). K8a `DeadlineType` submit/evaluation/revision × siklus (null = utama, n = Monev n), unik per periode (BR-FE-25). K8b detail memegang `submission_date_id`/`evaluation_date_id` (wajib bila direview)/`revision_date_id`, satu periode dan siklus (BR-FE-26). K8c pengisi → Batas Submit, NEEDS_REVISION → Batas Revisi bila ada, reviewer → Batas Evaluasi. K8d "Dibuka" dan pengajuan baru = Batas Submit siklus utama (BR-FE-01); arsip draft tetap seluruh tanggal (BR-SM-13). K8e `periods.manage` menggeser tanggal untuk semua (penugasan reviewer ikut), `submissions.view-all` memberi override per pengajuan (BR-FE-27), BR-SCH-08 hanya untuk Batas Submit/Revisi; penutupan paksa tak tertembus. K8f tabel `submission_date_labels` dihapus tanpa migrasi data. K8g builder legacy hanya memilih periode yang ada. |
| W32 | §4.7 menaruh jumlah belum dibaca di `auth.user.unread_announcements`. | Penulis rencana A1: `navigation.unreadAnnouncements`, karena `navigation` sudah menjadi tempat flag sidebar yang bergantung pada data (§4.4). |
| W33 | `user.announcements.markRead` berada di grup `check_biodata`, sehingga pembaca berbiodata belum lengkap terlempar ke form biodata saat membuka detail. | Penulis rencana A1: route itu keluar dari `check_biodata` (nama dan path tetap); daftar `user.announcements.index` tetap di balik gerbang. |
| W34 | "Berlaku sampai" tanpa jam. | Penulis rencana A1: tanggal saja, disimpan pukul 23:59:59 hari itu (pola tenggat Perpanjang Tenggat). |
| W35 | Layar detail khusus kelola (`admin.announcements.show`) tidak dibutuhkan. | Penulis rencana A1: dihapus; pengelola membaca isi di `FormPage`, pembaca memakai `Announcement/ShowPage`. |
| W36 | Target peran hanya lima peran DDD, sementara Super Admin dan role legacy `Admin` ada di luar daftar itu. | Penulis rencana A1: Query pembaca tidak mengecualikan Super Admin; pengumuman privat bertarget tidak tampil di jalur pembacanya kecuali ia memegang peran itu. Layar kelola tetap menampilkan semuanya. |
| W37 | DDD (activity diagram notifikasi) dan wireframe menggambar "Tandai Semua Dibaca"; komentar lama `routes/web.php` menolaknya. | Penulis rencana D1, **belum dikonfirmasi pemilik**: dibangun (`POST notifications.read-all`, `NOT-INAPP-04`). BR-NOT-04 melarang penandaan saat halaman dibuka, bukan tindakan eksplisit. |
| W38 | `sidebar.md` tidak memuat item Notifikasi dan `topbar.md` melarang bell, sedangkan DDD menuntut badge belum dibaca di navbar. | Penulis rencana D1: item sidebar "Notifikasi" (ikon `Bell`, badge `navigation.unreadNotifications`) sesudah "Pengumuman"; pola W32. Tautan dari dasbor tetap ada. |
| W39 | Payload notifikasi tidak membawa `action_url`, padahal diagram DDD mengarahkan "Buka" ke halaman relevan. | Penulis rencana D1: tujuan dihitung saat diklik (`NotificationDestination`) dari `type` + konteks, dengan pemeriksaan akses BR-SM-07 saat itu (`NOT-INAPP-05/06`). `ProposalSubmitted`/`SubmissionPeriodActive` pindah ke `NotificationPayload`; email tidak berubah. |
| W40 | Chip filter notifikasi butuh kategori yang tidak ada di DDD. | Penulis rencana D1: enum `NotificationCategory` per **kelas** notifikasi; chip hanya Semua · Belum dibaca · Review · Monev. Kelas yang sudah dihapus tampil sebagai "Lainnya". Test arsitektur menjaga setiap kelas punya kategori. |
| W41 | BR-NOT-05 menuntut penghitung ber-cache; badge dihitung ulang di setiap request. | Penulis rencana D1: `UnreadNotificationCount` di-cache per user, dilupakan saat notifikasi `database` terkirim (listener `NotificationSent`), dibaca, dibaca semua, atau dihapus. |
| W42 | Wireframe tidak mendefinisikan angka kartu Operator. | Penulis rencana D1: "Total Submission Aktif"/"Perlu Penugasan Reviewer" menghitung baris `OperatorSubmissionList` (`is_submitted`, induk saja); "Perlu Penugasan Reviewer" = SUBMITTED, menaut `?status=submitted`; "Export Selesai" = ekspor milik viewer sejak awal minggu (BR-RPT-07). Kartu "Submission Aktif" peneliti memakai Active Submission DDD (DRAFT dan APPROVED ikut); "Semester ini" pada "Evaluasi Selesai" dibuang (DDD tak mengenal semester). |
| W43 | Wireframe memberi 3 dan 5 hari "mendesak", 6 hari tidak. | Penulis rencana D1: satu ambang `DeadlineData::URGENT_DAYS = 5`, juga untuk meta "n mendekati deadline" reviewer. |
| W44 | "Aktivitas Terbaru" tidak punya sumber di DDD. | Penulis rencana D1, **pertanyaan terbuka untuk pemilik**: 5 entri teratas `AuditScope::forUser` (BR-RPT-05). Reviewer murni hampir selalu melihat EmptyState; alternatifnya 5 notifikasi terbaru. |
| W45 | Tiga dasbor legacy per peran. | Penulis rencana D1: satu halaman — sambutan → banner biodata/antrian → section kartu per permission → Aktivitas + Deadline (gabungan, maks 5) → Pengumuman terbaru (3) → Aksi Cepat (`lib/dashboardActions.ts`, dari permissions). Banner antrian hanya bagi `reviewers.assign` dan hanya bila ada yang menunggu. |
| W46 | Legacy `AppSidebar` dan halaman legacy masih menaut `user.dashboard`/`reviewer.dashboard`/`admin.dashboard`. | Penulis rencana D1: ketiganya `Route::redirect` ke `/dashboard` sampai F99; pemanggil PHP pindah ke `dashboard`. Item "Statistik" menaut `admin.stats.form-submission` sampai R1. |
| W47 | Tiga sumber berbeda soal wewenang Identity: `sidebar.md` (Reviewer = `reviewers.assign`, User & Verifikasi = `users.verify`/`users.manage`), DDD ("Operator" untuk penunjukan dan undangan, "Admin" untuk deaktivasi), BDD IAM-INVITE-01 (aktor `users.manage`). | Penulis rencana I1 + **pemilik, 2026-11-02**: daftar & detail user = `users.verify` atau `users.manage`; verifikasi, tolak, koreksi NIDN, berkas biodata = `users.verify`; manajemen reviewer = `reviewers.assign` (keluar dari `can:users.manage`); undangan = `users.verify` atau `users.manage` (Operator ikut menerbitkan, `[IAM-INVITE-09]`); deaktivasi, peran, hak akses langsung = `users.manage`. Preset undangan (W51) menjadi batas keamanan. |
| W48 | "Aktifkan" di OPERATOR-08-01 tidak punya BR. | Pemilik, 2026-11-02: BR-IAM-20 ditambahkan ke DDD (v2.5) dengan skenario IAM-REACT-01..05, dibangun di I1-1. |
| W49 | Kolom "Status" 08-01 menggabungkan `is_active` dan `verification_status`, yang dipisahkan DDD. | Penulis rencana I1: dua badge — akun ("Aktif"/"Nonaktif") dan verifikasi ("Menunggu Verifikasi"/"Ditolak", hanya bila belum terverifikasi). Deaktivasi hanya di detail, tidak di baris tabel. |
| W50 | Legacy punya buat user dengan password, sunting nama/email/password, serta hapus/aktifkan/nonaktifkan reviewer; wireframe dan DDD tidak. | Penulis rencana I1: semuanya dihapus (`admin.users.create/store/edit/update`, `DismissReviewer`, `reviewers.destroy/activate/deactivate`). Masa tugas diakhiri lewat "Edit Periode" (BR-REV-10, BR-RPT-09). |
| W51 | Undangan legacy menerima `permissions[]` bebas (eskalasi). | Penulis rencana I1: hak akses dari preset `InvitationPreset` (satu nilai, permission peran `researcher`), parameter palsu diabaikan, preset tak dikenal 422. Tautan tampil sekali lewat flash `issuedInvitationUrl`; "Buat Ulang" mengisi form; status menyebut sebabnya; maks. pemakaian dan Berlaku Sampai wajib, disimpan 23:59:59. |
| W52 | DDD Jalur 1 dan BR-IAM-13 menyebut koreksi NIDN, kode tidak punya jalurnya. | Penulis rencana I1: `CorrectNidn` oleh `users.verify`, tanpa notifikasi (BR-IAM-15) dan tanpa mengubah status; skenario IAM-VERIFY-13/14. |
| W53 | "Ditolak pada" (W5) tanpa kolom. | Penulis rencana I1: dari entri audit penolakan terbaru (pola W7), di kartu 08-02 dan `AccountVerificationData.rejectedAt`. |
| W54 | Badge "Beban Tinggi" 08-04 tanpa ambang global; BR-REV-04 mengukur per skema. | Penulis rencana I1: "Beban Tugas" = penugasan `active` yang evaluasinya belum beres; badge tidak digambar. |
| W55 | 08-03 menggambar tab "Log Aktivitas", "Ditambahkan oleh … · tanggal" pada hak akses tambahan, dan filter "Organisasi" di 08-01. | Penulis rencana I1: tidak dibangun; log menunggu `Reporting/AuditPage` di R1, filter organisasi mengikuti W28. |
| W56 | Undangan tidak punya item sidebar. | Penulis rencana I1: pintunya tombol "Undangan Registrasi" di header `UserIndexPage` dan EmptyState pencarian kandidat reviewer (IAM-REVIEWER-03), hanya bila `InvitationPolicy::manage`. |
| W57 | Penyuntingan peran bisa melepas role reviewer atau role legacy. | Penulis rencana I1: `RoleCatalog::ASSIGNABLE` = `researcher`, `operator`, `admin`; role reviewer hanya lewat penunjukan (BR-IAM-11); role legacy tidak ditawarkan dan tidak tersentuh. Akun Super Admin dan akun penyunting sendiri tidak bisa dinonaktifkan atau diubah perannya. |
| W58 | Berkas biodata pendaftar (W15) tidak punya pembaca. | Penulis rencana I1: `admin.verifications.files.show`, dijaga `users.verify`, dicakup ke submission biodata milik profil itu (lainnya 404). **Dihapus P1 (W61):** pemilik, 2026-11-02, aplikasi tidak menyimpan KTP karena data pribadi sensitif; BR-IAM-13 memverifikasi NIDN, bukan dokumen identitas. |
| W59 | BR-IAM-05 bolong: sesi tak diperiksa ulang, penugasan/pengganti/kepemilikan/anggota menerima akun nonaktif. | Penulis rencana I1: middleware `EnsureAccountActive` + pencabutan sesi database dan remember-me; akun nonaktif ditolak sebagai reviewer, pengganti, penerima kepemilikan, dan anggota. Penugasan berjalan tetap `active` dan ditandai "perlu diganti" (skenario IAM-DEACT-09..12, SM-OWNER-07). |
| W60 | "Seketika" dan kehadiran langsung butuh dorongan server. | Pemilik, 2026-11-02: kehadiran dibangun dari tabel `sessions` lewat `SessionRegistry` (BR-IAM-21, hanya untuk `SESSION_DRIVER=database`); dorongan seketika lewat Reverb = irisan RT1 setelah F99 (K7). |
| W61 | Biodata Form-Engine (`FormType` Biodata, `SaveBiodata`, gerbang `CheckBiodata` untuk role legacy `Mahasiswa`/`Tenaga Kependidikan`) tidak dikenal DDD, BDD, UX, maupun wireframe. DDD mendefinisikan profil lengkap sebagai telepon + bidang keahlian + foto dan memperingatkan agar tidak ada gerbang "user siap?" keempat. Pembanding `../evolusi-simpas`: satu-satunya berkas profil adalah foto. | Penulis rencana P1 + **pemilik, 2026-11-02** (tanpa KTP karena sensitif): seluruh rantai biodata, gerbang `check_biodata`, shared prop `biodataStatus`, dan pembaca berkas W58 dihapus. `user.biodata.index` menjadi redirect ke `/profile` sampai F99 (legacy AppSidebar, pola W46). Baris `FormType` "Biodata" (id 1) tetap ada supaya id lain tidak bergeser; **E2** tidak boleh menawarkannya untuk form baru. |
| W62 | "Bidang Keahlian" di frame 01-06/07 berupa select, tapi DDD hanya punya `expertise` string tanpa katalog. | Penulis rencana P1: input teks bebas. Katalog = data referensi baru; bila diinginkan, buka di C1. |
| W63 | UX IA menulis Lengkapi Profil untuk "status active", padahal BR-IAM-04 + IAM-VERIFY-06 membolehkan akun pending membuat draft bila profilnya lengkap. | Penulis rencana P1: terbuka bagi setiap pemilik `UserProfile` apa pun statusnya. Tujuan pasca-login: pending/rejected tetap ke layar verifikasi (IAM-VERIFY-01); akun lain dengan `submissions.create` dan profil belum lengkap ke Lengkapi Profil (flow diagram UX). User tanpa `UserProfile` dialihkan ke Profil Saya (nama, email, kata sandi saja); P1 tidak membuat baris profil baru (profil lahir `pending`, BR-IAM-12). |
| W64 | Profil Saya legacy mengizinkan ganti email sendiri. | Penulis rencana P1: email, NIDN, dan organisasi read-only (BR-IAM-01: pemeriksaan domain hanya saat registrasi; BR-IAM-02/13 + W52: NIDN dikoreksi Operator; BR-IAM-18: pindah unit oleh Operator). Nama route `profile.edit` dipertahankan. |
| W65 | Foto profil belum punya tempat simpan maupun pembaca. | Penulis rencana P1: disk privat (`profile-photos/`, nama acak), JPG/PNG maks 2 MB dicek isinya (`File::image`), dibaca hanya lewat `profile.photo.show` milik sendiri (tanpa parameter). "Crop sederhana" tidak dibangun; avatar sidebar tetap inisial. |
| W66 | Ganti kata sandi menulis langsung tanpa audit (BR-RPT-09). | Penulis rencana P1: `PasswordController` memanggil `ChangeOwnPassword` (audit "Kata sandi diganti" tanpa properti). Sesi lain tidak diakhiri (jalur `users.manage`, BR-IAM-21). |
| W67 | Frame 09-01 menampilkan status "Terjadwal" dan tanggal "Buka", padahal DDD tidak punya tanggal mulai: setiap `SubmissionDate` adalah batas akhir (BR-FE-01; `SubmissionPeriodGate`). Banner frame menulis "ditutup otomatis saat batas submit terlewat", padahal gerbangnya menunggu **seluruh** tenggat. | Penulis rencana E1: status = `displayStatus` apa adanya — Dibuka, Ditutup, Ditutup Operator, Belum Bertanggal. Banner: "…saat seluruh tenggatnya terlewat". |
| W68 | Frame menawarkan "Tutup", tapi belum ada jalur produksinya (hanya `SubmissionPeriod::closeAndNotifyDraftOwners()` yang dipakai test). | Penulis rencana E1: Action `CloseSubmissionPeriod` = Close Early (ubiquitous language): hanya periode yang masih terbuka, mengunci fase (BR-SCH-07), memberi tahu pemilik draft (BR-SM-10, event `SubmissionPeriodClosed`), beraudit. SM-ARCHIVE-02 diikat ulang ke Action ini. DDD tidak punya "buka kembali", jadi tidak dibangun. |
| W69 | Tombol "Arsip" di baris Ditutup tidak punya layar tujuan di DDD/frame mana pun. | Penulis rencana E1: tidak dibangun. Angka "Pengajuan Masuk" (induk terkirim, predikat Semua Pengajuan) menaut ke `admin.submissions.index?period=<id>` bagi pemegang `submissions.view-all`. |
| W70 | STATE-COVERAGE menuntut state FE-DELETE-10/11 di frame ini, padahal frame tidak menggambar aksi hapus. | Penulis rencana E1: aksi "Hapus" per baris; periode terhalang membuka dialog penjelasan (penghalang dari `SubmissionPeriodReferences`, BR-FE-21 "dinonaktifkan, bukan dihapus") dengan jalan keluar "Tutup Periode". |
| W71 | Buat/sunting periode tidak punya frame. | Penulis rencana E1: dialog di halaman daftar; tidak ada halaman detail periode. |
| W72 | Katalog label tanggal seeder hanya berisi "Mulai/Selesai …", padahal BR-FE-17 menuntut tenggat per tahap ("Batas Evaluasi", "Batas Revisi"); label baru hanya bisa dibuat lewat endpoint JSON legacy (`axios`). | Penulis rencana E1: label teks bebas + saran (`datalist`), dibuat saat periode disimpan (`SubmissionDateLabel::named`, trim). Endpoint `admin.submission-date-labels.store` dihapus. Tenggat yang dipakai detail tahap terkunci di dialog (BR-FE-07). **Digantikan K8 (E1b):** label teks bebas ditolak pemilik 2026-11-03; tenggat kini berjenis tetap × siklus. |
| W73 | `ExtendSubmissionDates` memberi tahu pemilik draft walau periodenya ditutup Operator — draft itu terarsip (BR-SM-13) dan tetap terkunci (BR-SCH-07), jadi bukan "DRAFT aktif" BR-SCH-08. Nilai kembaliannya juga tidak membedakan "tidak diperpanjang" dari "diperpanjang tanpa draft" (SCH-RULE-13). | Penulis rencana E1: periode `is_force_closed` tidak memberi tahu siapa pun; Action mengembalikan `DeadlineExtension{extended, notified}`, controller mem-flash `deadlineExtended` hanya bila ada tenggat yang mundur. |
| W74 | Filter "Tahun" di frame tidak menyebut tahun apa. | Penulis rencana E1: tahun salah satu `SubmissionDate`; filter Status di SQL dicocokkan test dengan `displayStatus`. |
| W75 | `NotifyActiveSubmissionPeriods`/`PeriodsOpeningToday` masih membaca tanggal pertama sebagai "periode dibuka" — bertentangan dengan W67 dan dengan `SubmissionPeriodOpened` DDD ("Semua active Researcher"). | Tidak disentuh E1 (bukan layar ini). Perlu keputusan pemilik: kapan `SubmissionPeriodOpened` terbit (saat periode dibuat?) — usul untuk irisan notifikasi/C1. |
| W76 | Frame 08-05 menonaktifkan tombol "Nonaktifkan" bagi Form yang dipakai pengajuan aktif ("…tidak dapat dinonaktifkan"), terbalik dari BR-FE-19/FE-DELETE-05: Form yang dipakai justru dinonaktifkan, bukan dihapus. | Penulis rencana E2: DDD menang. "Nonaktifkan"/"Aktifkan" selalu tersedia lewat `ConfirmDialog`. Catatan kaki: "Form yang pernah dipakai pengajuan tidak dapat dihapus — nonaktifkan agar berhenti menerima pengajuan baru. Menonaktifkan form memerlukan konfirmasi." |
| W77 | FE-DELETE-05 belum punya gerbang: Form nonaktif masih bisa dimulai pengajuannya. | Penulis rencana E2: `StartSubmission` menolak Form nonaktif (422 `form_id`: "Form ini sudah tidak menerima pengajuan baru."), kecuali memakai ulang draft yang masih boleh disunting (bukan pengajuan baru; pengajuan lama tetap terbaca). `ResearcherStartOptions` menyembunyikan Form nonaktif kecuali peneliti punya draft di sana. Terverifikasi di browser. |
| W78 | Aksi "Hapus" tidak ada di frame 08-05, padahal STATE-COVERAGE menuntut FE-DELETE-01..03. | Penulis rencana E2 (pola W70): setiap baris punya Hapus. Baris berpengajuan membuka dialog penjelasan yang menyebut jumlah pengajuan dan memberi jalan keluar "Nonaktifkan"; baris kosong membuka `ConfirmDialog`. |
| W79 | Banyak fitur Form legacy tanpa frame dan tanpa DDD. | Penulis rencana E2: dihapus — duplikat Form, hapus massal Form, wizard `FormBuilder` (`CompleteFormBuilderController`, `BuildCompleteForm`, `FormBuilderRequest`, `EvaluationFormWriter`), halaman detail Form, daftar kontrol akses global + sunting + massal, endpoint JSON `api.study-programs`, redirect lama `/forms` dan `/form-access-controls`. Aturan tahap yang diuji wizard (BR-FE-26, BR-SCH-07, BR-SCH-05) tetap ditegakkan lewat `CreateFormPhase` dan teman-temannya. Lihat W90 untuk yang ikut hilang. |
| W80 | "Tipe" Form dan Biodata. | Penulis rencana E2: "Tipe" = `FormType`. Biodata (`FormType::BIODATA_ID`) tidak ditawarkan untuk Form baru maupun sebagai tipe pengganti (sambungan W61); Form lama bertipe Biodata boleh disimpan dengan tipenya sendiri; mengubah Form lain menjadi Biodata ditolak 422. Filter daftar menawarkan semua tipe. |
| W81 | Arti "Pengajuan Aktif" di daftar. | Penulis rencana E2: Active Submission (`FormSubmission::scopeActive`: bukan REJECTED/WITHDRAWN, termasuk DRAFT dan anak monev). Penghalang hapus memakai semua pengajuan (BR-FE-19 "berapa pun statusnya"), jadi baris membawa dua angka. |
| W82 | Urut ulang field hanya lewat seret tidak terjangkau keyboard. | Penulis rencana E2: seret (`vuedraggable`, pegangan ikon) dan tombol Naik/Turun; urutan = indeks kiriman (`FormFieldWriter`). Terverifikasi di browser (Turun/Naik lalu simpan, urutan bertahan). |
| W83 | Field terarsip (BR-FE-12) tidak punya tempat di frame. | Penulis rencana E2: tampil di bawah daftar, read-only, berbadge "Diarsipkan" dengan tipe berlabel; tanpa pemulihan (tidak ada BR-nya). |
| W84 | FE-CHANGE-11/12/13 menyebut jumlah pemilih opsi, tapi `value` jawaban adalah teks bebas. | Penulis rencana E2: membuang opsi selalu soft delete (BR-FE-24); UI tidak menghitung "dipilih n kali". Satu hint di editor opsi: "Opsi yang dibuang berhenti ditawarkan untuk jawaban baru; jawaban lama yang memakainya tetap terbaca." |
| W85 | Frame menulis "3 draft berjalan", padahal penerima notifikasi adalah pemilik draft. | Penulis rencana E2: angka = pemilik draft (`AffectedDraftOwners::countForForm`, distinct `submitted_by`) — sama dengan penerima notifikasi. Teks: "{n} peneliti dengan draft berjalan akan terdampak perubahan ini. Centang untuk mengirimkan pemberitahuan." Toast: "Form diperbarui. {n} peneliti diberi tahu." Terverifikasi di browser. |
| W86 | Frame tidak menyebut route per tab dan alur sesudah simpan. | Penulis rencana E2: tab = route (`admin.forms.edit`, `admin.forms.access.index`); tab Phases menaut ke legacy `admin.form-phases.index` sampai E3. Mode buat: dua tab lain disabled; `store` → `admin.forms.edit` dengan "Form dibuat. Lanjutkan dengan Access Control."; "Simpan" → daftar; "Lanjut: Access Control" menyimpan dulu lalu pindah tab. |
| W87 | Frame Access Control tidak menyebut label permission/organisasi dan penyuntingan. | Penulis rencana E2: permission dari `FormAccessControl::ALLOWED_PERMISSIONS` berlabel `PermissionCatalog`; organisasi aktif berurut pohon, berlabel nama + " — beserta unit di bawahnya" bila punya turunan aktif (ADR 0013); aturan hanya tambah/hapus; aturan yang dipakai detail tahap tidak bisa dihapus (BR-FE-20): tombol disabled + "Dipakai {n} detail tahap". |
| W88 | Wewenang route Form belum eksplisit. | Penulis rencana E2: grup `['auth','can:schemes.manage']`; Admin 200, Operator 403 (Operator tidak memegang `schemes.manage` di `PermissionSeeder`). Terverifikasi di browser. |
| W89 | `StartChildSubmission` (laporan monev anak) tidak dijaga `forms.is_active`, berbeda dari `StartSubmission` (W77). | **Pemilik, 2026-11-03:** tetap tanpa gerbang. Menonaktifkan Form menghentikan *pengajuan baru* (BR-FE-19), bukan kewajiban melapor dari kegiatan yang sudah APPROVED (BR-MON); form yang dipakai laporan monev ditentukan konfigurasi tahap monev (E3), bukan flag ini. Diikat test `ChildSubmissionGateTest` "laporan tahap tetap bisa dimulai walau Form-nya dinonaktifkan"; dialog Nonaktifkan menyebutnya ("Laporan monev dari pengajuan yang sudah disetujui tetap bisa dikirim."). Kalimat ini ditambahkan ke BR-FE-19 (`ddd/generic/01_form_engine.md`) atas persetujuan pemilik, 2026-11-03: "Menonaktifkan Form hanya menghentikan pengajuan baru; laporan tahap dari pengajuan yang sudah APPROVED tetap bisa dibuat." |
| W90 | Pemeriksaan yang hanya hidup di wizard `FormBuilder` ikut hilang bersama use case-nya. | Penulis rencana E2 (putusan Task 3): menolak menautkan periode yang seluruh tenggatnya lewat, audit pemakaian ulang fase, flash info "Tenggat detail tahap", dan wajib-periode-ada. Tak satu pun BR di luar wizard. Penautan fase↔periode kini hanya di dialog periode E1 dan FormPhases legacy (E3); risikonya Operator dapat menautkan fase ke periode kedaluwarsa dari dialog periode (tidak berbahaya: gerbang BR-FE-01/BR-SCH-07 tetap menolak pengajuan). Efek samping: `form_phase_ids` ganda di `SubmissionPeriodRequest` didedupe (bug nyata yang terlihat saat pemetaan) dan `SubmissionPeriod::scopeUsable` dihapus. |
| W91 | Sebelum E2 tak ada yang membaca `forms.is_active`: Form yang sudah ada saat kolom ditambahkan (bawaan lama `false`), atau dimatikan lewat toggle legacy, diam-diam bernilai `false`. Sesudah E2 Form itu hilang dari langkah 1 wizard peneliti (W77). | **Pemilik, 2026-11-03:** data disamakan dengan perilaku lama lewat migration `2026_11_03_120000_activate_existing_forms` — semua Form nonaktif diaktifkan, **kecuali** tipe Biodata (W61); `down()` tidak membalik apa pun karena nilai lama tak pernah bermakna. Deploy E2 karenanya tidak mengubah apa yang dilihat peneliti; Admin menonaktifkan dengan sengaja lewat daftar Form Builder (beraudit). Langkah pra-deploy manual tidak diperlukan lagi. Form baru bawaannya aktif (`2026_11_03_110000_default_forms_is_active_to_true`). Migration dijalankan ke database sungguhan oleh pemilik, bukan oleh agent (AGENTS.md). |
| K9a | Tidak satu pun jalur produksi membaca `form_phases.is_active`, padahal penolakan hapus `ReferenceGuard` menyuruh Admin "Nonaktifkan saja agar tidak lagi dipakai pengajuan baru" (BR-FE-20 = perluasan BR-FE-19 ke tahap). | **Pemilik, 2026-11-03:** tahap nonaktif menutup pengajuan baru. `StartSubmission` menolak detail bertahap nonaktif (422 `form_phase_id`: "Tahap ini sudah tidak menerima pengajuan baru."), kecuali memakai ulang draft yang masih boleh disunting (pola W77). `ResearcherStartOptions` tidak menawarkan tahap nonaktif kecuali peneliti punya draft di Form itu. `StartChildSubmission` (laporan monev) tidak digerbangi (W89). FE-DELETE-15 ditulis. |
| K9b | Tidak satu pun jalur memeriksa bahwa tenggat detail milik periode tempat pengajuan dimulai; peneliti bisa memulai di periode Q sementara gerbang detail membaca tenggat periode P (ADR 0014). Menolak penautan bukan jawabannya: alur tahun baru mengharuskan keadaan antara (tautkan dulu, pindahkan tenggat sesudahnya). | **Pemilik, 2026-11-03:** (1) gerbang di titik pakai: `ResearcherStartOptions` hanya menawarkan detail yang Batas Submit-nya milik periode itu, `StartSubmission` menolaknya bila milik periode lain (422 `form_phase_id`: "Tahap ini belum bertenggat di periode ini.") sesudah pemakaian ulang draft; mulai pengajuan memilih lebih dulu detail yang Batas Submit-nya milik periode mulai, baru menurut `order` (`AccessibleFormPhaseDetail`, `submissionPeriodId`); (2) penautan tetap longgar: dialog "Hubungkan ke Periode" menawarkan periode yang belum ditutup paksa dan belum tertaut, lewat Action `LinkPhaseToPeriod` beraudit, route `periods.manage`; (3) kartu tahap memperingatkan "{n} detail masih bertenggat periode lain — perbarui tenggatnya agar terbuka di {periode}." untuk periode tertaut yang tidak memiliki Batas Submit semua detailnya; periode tertaut yang sudah tertutup penuh dilewati (`PhaseDeadlineGaps`, `SubmissionPeriodGate::isFullyClosed`) karena tidak akan pernah membuka tahap itu; (4) FE-WINDOW-13 ditulis dan diikat di tingkat mulai pengajuan. |
| W92 | "Tahap milik Form" tidak punya `form_id` di skema. | Penulis rencana E3: tahap milik Form bila salah satu detailnya memakai aturan akses Form itu (`FormPhase::usesForm`, sama dengan `AvailableChildPhases`); route `{formPhase}` di bawah `{form}` yang tidak memenuhinya 404. Satu tahap boleh memuat detail beberapa Form, jadi tampil di tab Phases tiap Form-nya dan editor menampilkan semua detailnya. Aktifkan/nonaktifkan/hapus dari tab Phases satu Form bekerja pada seluruh `FormPhase` bersama itu, dan teks konfirmasinya menghitung semua detailnya. |
| W93 | Frame 08-09 menggambar evaluasi reviewer sebagai detail tersendiri ("Reviewer — Evaluasi Proposal"); DDD (BR-FE-26, ubiquitous language) menaruhnya pada detail ber-`needs_review` dengan `evaluation_date_id` sendiri. | DDD menang: satu baris = satu `FormPhaseDetail`, judul "{tipe tahap} — {judul Form}", sub-baris akses dan tenggat (Batas Submit, Batas Evaluasi bila direview, Batas Revisi bila ada), badge Perlu Review/Tanpa Review. |
| W94 | Frame tidak punya isian judul tahap, padahal `title` wajib. | Kartu "Tahap" (Judul Tahap, Deskripsi, Status) mengawali `PhaseEditPage`. Mode buat: "Buat Tahap Baru" dengan satu detail kosong berakses aturan pertama Form; tanpa aturan akses, banner menaut ke tab Access Control dan "Simpan Tahap" disabled. |
| W95 | Form evaluasi butuh detail yang sudah tersimpan. | `store` mengalihkan ke sunting dengan pesan "Tahap dibuat. Tambahkan form evaluasi untuk detail yang direview."; `update` kembali ke tab Phases. Form evaluasi disimpan per kartu ("Simpan Form Evaluasi"), bukan lewat "Simpan Tahap": dua agregat, dua Action, tanpa Action komposit tanpa BR. |
| W96 | Urutan detail diturunkan `NormalisePhaseDetailOrder` dari Form; urutan kiriman diabaikan. | Daftar detail tanpa seret, nomor urut dari backend; payload tetap mengirim `order: index + 1` karena `FormPhaseRequest` mewajibkannya. |
| W97 | Form evaluasi "attached ke FormPhaseDetail yang `needs_review = true`" (ubiquitous language). | `needs_review` tidak bisa dimatikan selama detail punya form evaluasi aktif: penjaga `AssertEvaluatedDetailsStayReviewed` di `ReviseFormPhase`; UI: switch disabled + "Detail ini punya {n} form evaluasi aktif.", dibaca dari props server sehingga ikut mutakhir setelah form evaluasi dibuat di layar yang sama. |
| W98 | BR-FE-20 menyebut `FormPhaseDetail` "dinonaktifkan", tetapi agregatnya tidak punya `is_active` (DDD dan skema). | Selisih dicatat, tidak dibangun (butuh migrasi, tanpa frame). Hapus detail berpengajuan disabled dengan hint "Dipakai {n} pengajuan — nonaktifkan tahapnya bila detail ini tidak boleh dipakai lagi." |
| W99 | Dialog periode E1 menautkan tahap tanpa memeriksa pemilik tenggat detailnya. | Sengaja tidak diubah: penautan sebelum tenggat dipindah adalah langkah sah (K9b). Pengajuan dimulai di periode yang bukan pemilik tenggat detailnya ditutup di `StartSubmission`/`ResearcherStartOptions`, sehingga berlaku untuk setiap jalur penautan. |
| W100 | Legacy punya daftar tahap global + filter + hapus massal + endpoint JSON status, halaman detail tahap, daftar dan detail form evaluasi global (angka pemakaian), pratinjau, duplikat, urut ulang. Tanpa frame dan tanpa DDD. | Dihapus: `FormPhaseDirectory`, `FormPhaseListRequest`, `bulkDelete`, `updateStatus`, `EvaluationFormUsage`, `DuplicateReviewEvaluationForm`, `ReorderReviewEvaluationForms`, `FormPhaseFormOptions`, `FacultyOptions`. Form evaluasi tidak bisa dipindah ke detail lain (route ber-detail), sehingga penjaga `assertStaysWithItsAssignments` dihapus (Remove Dead Code). |
| W101 | Kartu tahap (08-08) tidak punya aksi aktif/hapus, padahal STATE-COVERAGE menuntut state FE-DELETE-06. | Pola W78: kartu menambah "Nonaktifkan"/"Aktifkan" dan "Hapus", badge "Nonaktif"; "{n} tahapan" = jumlah detail; "Periode: {nama, koma}" atau "Belum terhubung". |
| W102 | Pilihan akses detail dan tenggat butuh sumber. | Pilihan akses = aturan akses semua Form aktif ditambah Form yang sudah dipakai detail tahap ini, berlabel "{judul Form} — {permission} — {organisasi}"; detail baru memakai aturan pertama Form yang dibuka. Batas Evaluasi/Revisi difilter ke periode+siklus Batas Submit detail (BR-FE-26); tenggat tersimpan di luar pilihan tetap tampil dan tersimpan. Backend tetap penentu. |
| W103 | Field form evaluasi memakai `FieldTypeOptions` E2, yang hanya `{value, label, hasOptions}`, sedangkan batas bergantung pada jenis tipe. | Batas `min_length`/`max_length` hanya untuk tipe teks, `min_value`/`max_value` hanya untuk `number` (frame: "skor 1–7"). `FieldTypeOptions` mendapat kunci aditif `limitType: 'length'\|'value'\|null` dari konstanta `FieldType` (teks, textarea, email, url = panjang; number = nilai) karena FE tidak boleh menghitung jenis tipe sendiri; konsumen E2 mengabaikannya. Field yang sudah dijawab (`answerCount > 0`) tidak bisa diganti tipe atau dihapus (cermin BR-FE-23; `ReviewFormFieldWriter` sudah menolaknya). |
| W105 | `sidebar.md` dan label katalog menaruh Skema dan Konfigurasi Umum di `schemes.manage`, sedangkan `/admin/reference` dan channel notifikasi memakai `users.manage`; BR-IAM-17/18 menyebut Operator untuk organisasi, tetapi matriks peran tidak memberi Operator wewenang apa pun atasnya. | Skema dan Konfigurasi Umum (data acuan + channel) pindah ke grup `can:schemes.manage`; pemegang `users.manage` saja mendapat 403. **Pemilik 2026-11-07 (C1-3):** organisasi memakai permission baru `organizations.manage` (label "Mengelola struktur organisasi") untuk peran `operator` dan `admin`; Operator **tidak** diberi `users.manage` (membuka ubah peran, hak akses langsung, deaktivasi akun). DDD identity v2.6, Given IAM-ORG-09 dan `sidebar.md` ikut. Langkah pemilik di database yang sudah berjalan: `php artisan db:seed --class=PermissionSeeder` (perlu agar Admin dan Operator mendapat permission baru; `syncPermissions` menimpa perubahan manual pada peran bawaan). |
| W106 | Frame 08-04 punya field "Status" di form skema. | Buat/sunting skema = halaman sendiri (`schemes.create`/`schemes.edit`). Status hanya berubah lewat Nonaktifkan/Aktifkan di daftar (BR-SCH-01 di `DeactivateScheme`); form menampilkan badge status di header. Skema baru langsung dibuka di halaman suntingnya. |
| W107 | Frame menggambar editor kunci–nilai bebas untuk "Aturan Tambahan", padahal pembaca `rules` memeriksa `is_int`/list sehingga nilai teks diam-diam diabaikan. | Aturan bertipe dari `SchemeRuleCatalog`: `min_reviewer_count`, `max_reviewer_workload`, `max_student_members` (bilangan bulat ≥ 1) dan `allowed_output_types` (daftar kode tipe luaran). Label dikirim sebagai `options.integerRules`. Kunci di luar katalog ditolak saat dikirim, tetapi yang sudah tersimpan dipertahankan; field kosong membuang kuncinya. Pembacaan bertipe di `Scheme::positiveIntRule`/`codeListRule`. Pertanyaan pemilik: konfirmasi bahwa editor bebas memang tidak dibutuhkan. |
| W108 | `SaveScheme` menulis `rules = null` setiap kali halaman legacy menyimpan (legacy tidak pernah mengirim `rules`). | `rules` null = tidak disentuh; bila dikirim, digabung ke aturan tersimpan (`SchemeRuleCatalog::merge`). Skenario baru `[SCH-RULE-17]`. |
| W109 | `SchemeController::activate` menulis `is_active` langsung tanpa audit. | Action `ActivateScheme` ("Skema diaktifkan", old/new `is_active`); skema yang sudah aktif ditolak tanpa audit. |
| W110 | Form skema perlu pilihan tipe skema dan tipe luaran. | Tipe Skema = yang aktif plus yang sedang dipakai skema itu (SC-CONFIG-05); tipe luaran = yang aktif plus yang sudah terpilih (yang nonaktif berlabel "(nonaktif)"); TRL semuanya (tabelnya tanpa `is_active`). |
| W111 | Kolom "Tipe" daftar skema. | Kolom dan filter "Tipe" = Tipe Submission; filter status aktif/nonaktif, pencarian nama atau kode tidak peka huruf, paginasi 15 di server. |
| W112 | Frame Konfigurasi Umum memuat "Tipe Jurnal", "Tipe HKI", dan "Tipe Organisasi". | Tipe Jurnal/HKI tidak dibangun (tanpa tabel, BR, skenario). Tipe Organisasi bukan data acuan (dibaca registrasi dan laporan) → katalog kode di C1-2. Kartu "Organisasi & Anggaran" menjadi "Anggaran". Tab dibaca dari `ReferenceEntity::group` dan disaring di klien; channel notifikasi tampil di "Semua Kategori" dan "Lainnya". |
| W113 | Deklarasi channel kosong lolos validasi dan membuat event bisu; event tanpa baris tidak bisa ditetapkan dari layar. | ≥ 1 channel (FormRequest dan `DeclareNotificationChannels`), skenario baru `[NOT-CHANNEL-07]` (ID `-06` sudah dipakai). Event dari kelas notifikasi yang belum punya baris tampil "Belum dideklarasikan" + "Tetapkan Channel" (upsert); nama event di luar kelas → 404. Checkbox terakhir yang tercentang dimatikan. |
| W114 | Menyimpan data acuan dengan namanya sendiri ditolak "Nama itu sudah dipakai". | `ReferenceEntityRequest` mengabaikan id baris yang disunting pada aturan `unique`. |
| W115 | `CountReferenceEntityUsage` adalah Action yang hanya membaca dan dipanggil sekali per baris. | Query `ReferenceEntityUsage` (`withCount` atas `ReferenceEntity::usageRelations()`) dipakai layar dan `DeleteReferenceEntity`; hitungan yang tak terbaca dilempar, bukan dianggap nol. |
| W116 | `DeleteOrganization`, `DeactivateOrganization`, dan penjaga siklus `SaveOrganization` memakai `Organization::subtreeIds()`, yang CTE-nya berhenti di unit nonaktif: unit dengan anak nonaktif berprofil bisa dihapus (anaknya diam-diam menjadi akar, melanggar BR-IAM-08), dan memindah unit ke bawah keturunannya lewat unit nonaktif lolos (BR-IAM-17), lalu `Organization::parentChain` berputar tanpa henti pada lingkaran itu. | Query struktural `OrganizationSubtree`: tanpa filter aktif, tanpa cache, `UNION` sehingga data lama yang bersiklus tetap berhenti. `OrganizationCensus` memakainya untuk penjaga dan layar. `subtreeIds()`/`ancestorIds()` milik access control (BR-FE-02, IAM-ORG-08) tidak diubah. Pohon menampilkan unit bersiklus sebagai akar supaya tidak ada yang hilang. |
| W117 | `SaveOrganization` menerima induk nonaktif, padahal `MoveUserToOrganization` menolak tujuan nonaktif; `type` bebas teks. | Induk nonaktif ditolak saat membuat unit dan saat induk diganti ("Unit induk sudah nonaktif, jadi tidak menerima sub-unit baru.", skenario baru `[IAM-ORG-16]`); unit yang induknya tidak berubah tetap bisa diganti nama. `type` divalidasi terhadap `OrganizationTypeCatalog` (Institusi, Fakultas, Program Studi, Unit Kerja); pada sunting, tipe lama unit itu sendiri tetap diterima. "Tambah Sub-unit" tidak ditawarkan untuk unit nonaktif. |
| W118 | Frame IAM-ORG-08 menampilkan pratinjau "memutus akses 27 peneliti". | Tidak dibangun: BR-IAM-14 menolak deaktivasi selama ada user aktif di subtree, jadi angka itu selalu 0 saat konfirmasi. Konfirmasi Nonaktifkan menyebut profil yang tetap tersimpan (IAM-ORG-07), undangan yang ikut tidak berlaku, dan bahwa unit beserta turunannya berhenti ditawarkan serta memutus rantai akses Form. |
| W119 | Tidak ada jalur "Aktifkan kembali" unit organisasi. | **Pemilik 2026-11-07 (C1-3):** BR-IAM-22 baru. Pengaktifan oleh pemegang wewenang deaktivasi, untuk unit itu saja (turunan tetap nonaktif), ditolak selama induknya nonaktif, undangan yang masih sah berlaku lagi dan jumlahnya disebut di konfirmasi, diaudit "Organisasi diaktifkan kembali". Unit yang sudah aktif: tanpa perubahan dan tanpa audit. Event `OrganizationReactivated` dicatat di DDD. |
| W120 | Frame tidak menggambar jalur memindah user, padahal BR-IAM-18 adalah prasyarat deaktivasi BR-IAM-14. | Dialog "Pindahkan User" di panel unit terpilih (tampil bila subtree berisi profil): cari anggota subtree lewat partial reload `memberCandidates` (20 pertama, nama/email), pilih unit tujuan aktif selain unit asalnya. Jalur yang sama di halaman detail user tidak dibangun. |
| W121 | Laporan monev tidak bisa diisi lewat jalur mana pun: `SaveSubmissionDraft` me-resolve lewat `ResolveWizardSubmission` (`parent_submission_id IS NULL`), jadi simpan/submit laporan anak selalu 409 dan Monev II terkunci selamanya. | Jalur tulis sendiri `SaveMonevReport`/`SubmitMonevReport` (route `monev.reports.save`/`submit`), resolve dari id laporan. `SubmitFormSubmission` menolak laporan anak (422 `form_phase_detail_id`) supaya tidak memancarkan `ProposalSubmitted`. |
| W122 | Pembekuan BR-MON-08 hanya dijaga saat laporan dibuat; draft yang dibuat sebelum penarikan masih bisa disimpan/disubmit. | `MonevAccessGate::assertReportOpen` dipanggil simpan dan submit; skenario `[MON-CYCLE-13]`. |
| W123 | Pemilik laporan anak = siapa pun yang memulainya; tim laporan (`research_members`) selalu kosong. | `StartChildSubmission` menulis `submitted_by` = ketua induk (pemicu tetap causer audit). Policy `startMonevReport` (invarian ADR 0015). `FormSubmission::teamSubmissionId()` dipakai `isAccessibleBy`, `SubmissionVisibility::ownedOrJoinedBy`, dan `ConflictOfInterestGuard`; skenario `[MON-CYCLE-14]`/`[MON-CYCLE-15]`. |
| W124 | Co-investigator induk bisa ditugaskan menilai laporan monev timnya (BR-REV-02). | Ditutup lewat `teamSubmissionId()`. Reviewer induk sengaja tidak dihitung konflik (saran BR-MON-07). Skenario `[MON-REVIEW-15]` di M1-2. |
| W125 | Gerbang menghitung semua detail ber-`order` lebih kecil sebagai pendahulu, termasuk detail pengajuan Form lain di tahap yang sama; legacy menamai semua siklus dengan judul tahap. | Satu definisi `MonevCycles::details` (tahap sama, `order` > detail induk) dipakai gerbang, layar, dan Action; detail lain 422 `form_phase_detail_id`. Nama dari `cycle` Batas Laporan (`DeadlineType::cycleName`), lalu `PhaseType`, lalu "Tahap {order}". |
| W126 | `OpenMonevCycle` tidak menyimpan apa pun: "Belum Aktif" tak bisa dibedakan dari "Menunggu Laporan" dan tiap klik mengirim ulang notifikasi. | Kolom `form_submissions.cycle_opened_at` + aktivasi memastikan laporan ada; pembukaan kedua 422 tanpa notifikasi `[MON-REVIEW-14]` (M1-2). |
| W127 | Pola UX "All locked: semua siklus belum diaktifkan operator" bertentangan dengan `[MON-CYCLE-02]`. | BDD menang: gerbangnya turunan keadaan, aktivasi hanya pemberitahuan (BR-MON-10). Daftar peneliti tidak menampilkan status aktivasi. |
| W128 | Siapa yang melihat Daftar Monev (menutup W1 untuk monev). | Induk diketuai/diikuti (BR-SM-07), APPROVED, atau WITHDRAWN dengan ≥ 1 laporan (`ResearcherMonevList`). Flag sidebar `navigation.hasMonevSubmission` (`HasMonevSubmission`, builder yang sama). `hasApprovedSubmission` tetap untuk Luaran sampai O1. |
| W129 | Wewenang operator monev. | Layar kelola dan aktivasi `can:submissions.view-all`; penugasan reviewer Policy `assignMonevReviewer` (`reviewers.assign`); perpanjangan memakai `admin.submissions.overrides.store` pada laporan (BR-FE-27). Nav "Kelola Siklus Monev" pindah ke `submissions.view-all` (M1-2). |
| W130 | Route monev. | `/monev` (`monev.index`), `/monev/{submission}/cycles/{detail}` (`monev.reports.store`), `/monev/reports/{report}/fill|draft|submit` dan `/monev/reports/{report}` (`monev.reports.show`); operator `monev.manage.*` di M1-2. `user.submissions.children.store` dihapus; `user.submissions.show` untuk laporan anak dialihkan ke `monev.reports.show`. |
| W131 | Yang tidak digambar. | Tanggal "Disetujui …" pada node Pengajuan (tak ada satu sumber), node "Upload Luaran" (O1), "· tepat waktu" di 05-03, filter "Status Siklus" operator (keadaan turunan, preseden W28), `DeadlineCountdown`, master-detail klik-node operator. |
| W132 | UX berbunyi "Redirect ke Daftar Monev" setelah submit laporan. | Frame menaut 05-03: submit mengarah ke `monev.reports.show` dengan toast "Laporan {nama siklus} disubmit.". |
| W133 | Status laporan anak setelah dikirim. | `SubmitMonevReport` memindah DRAFT → SUBMITTED (badge "Disubmit"), tidak pernah ke UNDER_REVIEW/APPROVED, tanpa event domain baru. |
| W134 | Alih kepemilikan induk meninggalkan laporan anak pada ketua lama. | **Pemilik 2026-11-07:** `TransferSubmissionOwnership` memindah `submitted_by` semua laporan anak dalam transaksi yang sama; audit induk mencatat `reports_moved`. Skenario `[SM-OWNER-08]`. |
| W135 | BR-SCH-07 lama: Close Early membekukan monev semua proyek APPROVED di periode itu. | **Pemilik 2026-11-07:** BR-SCH-07 v2.5 menutup tahap pengajuan saja (`SubmissionPeriodGate::isForceClosed` = Batas Submit tanpa siklus). Laporan anak tidak pernah archived (BR-SM-13 v2.5) dan tidak diberi tahu saat periode tutup (`PeriodDraftOwners`, BR-SM-10 v2.5). Skenario `[MON-CYCLE-16]`/`[MON-CYCLE-17]`, judul `[FE-OVERRIDE-11]` dipersempit. |
| W136 | Frame menugaskan reviewer sebelum aktivasi, tetapi kandidat reviewer dihitung atas laporan yang belum ada. | Kandidat siklus tanpa laporan dihitung atas laporan bayangan tak tersimpan (`MonevCycleManagement::candidateSubject`); konflik tetap tim induk (W124), GET tidak membuat laporan. Penugasan (`AssignCycleReviewer`) membuat laporannya dalam satu transaksi: penugasan yang ditolak tidak meninggalkan laporan kosong. "Aktifkan" hanya selama siklus menunggu laporan (`open`/`closed`), karena peneliti tidak menunggu aktivasi (W127). |
| W137 | Cek browser M1-2: submit laporan oleh akun belum terverifikasi tidak menampilkan apa pun (galat `verification_status` bukan milik satu field). | `useMonevReport.generalErrors` → banner denial di `ReportFormPage` (juga pembekuan/tenggat), dialog konfirmasi ditutup saat ditolak. `EndToEndFlowSeeder` tidak memverifikasi `peneliti@itk.ac.id`, jadi akun itu tidak bisa mengirim apa pun di dev. |
| W138 | Peran `researcher` memegang `outputs.manage` (DDD identity + `PermissionSeeder`), dan Policy `manageResearchOutput` menganggapnya jalan staf: setiap peneliti berperan bisa menambah/menyunting luaran pengajuan APPROVED mana pun (HTTP 302 untuk orang asing), sementara Operator tidak memegang izinnya. | **Pemilik 2026-11-08:** jalan staf = `outputs.manage` ∧ `submissions.view-all`; Operator mendapat `outputs.manage` (identity v2.7, research output v2.5); controller memeriksa `isAccessibleBy` sebelum Policy. Test memakai peran seeder sungguhan (`seededRole`). Skenario `[RO-OUTPUT-25]`. Langkah deploy: `php artisan db:seed --class=PermissionSeeder`. |
| W139 | Luaran digerbangi Batas Submit detail **pengajuan**, yang selalu sudah lewat saat disetujui (BR-FE-17), sehingga setiap penambahan luaran 403 tanpa override; kode tidak bisa mengenali detail "Upload Luaran" (DDD monev Detail 7). | **Pemilik 2026-11-08:** jenis tenggat `output` "Batas Akhir" tanpa siklus (BR-FE-25/26 v2.4). Detail Upload Luaran = tahap sama, sesudah detail induk, Batas Submit-nya berjenis `output` (`OutputUploadDetail::for`, `FormPhaseDetail::isOutputUpload`); tanpa detail itu penulisan tidak berbatas. Ia tidak direview, tidak menerima pengajuan (`StartSubmission`, `ResearcherStartOptions`), bukan siklus monev (`MonevCycles`), dan tidak ditutup Close Early (`SubmissionPeriodGate::isForceClosed`). Dialog periode menyembunyikan siklus untuk Batas Akhir; kolom Batas Submit detail tahap juga menawarkan Batas Akhir. Skenario `[RO-OUTPUT-26]`, `[RO-OUTPUT-27]`, `[MON-CYCLE-18]`. |
| W140 | `output_type_definitions.fields` kosong untuk semua tipe dan tak ada seeder maupun editornya: langkah 2 frame 06-02 tak punya isi, BR-RO-09 tak menegakkan apa pun. | `OutputTypeDefinitionSeeder` (idempoten, tidak menimpa nama/status pilihan Admin) untuk enam tipe DDD; entri `{key, label, type, required, options?, hint?, empty_label?}` dibaca `OutputFieldSpecData::fromEntry`. `ResearchOutputMetadata` menegakkan wajib, bentuk nilai (angka, tanggal `Y-m-d`, opsi, URL http/https), dan membuang kunci di luar spesifikasi; angka disimpan sebagai bilangan. Editor spesifikasi tidak dibangun (tanpa frame, W112). Langkah deploy: `php artisan db:seed --class=OutputTypeDefinitionSeeder`. |
| W141 | Berkas bukti luaran berupa string `file_path` kiriman klien, bisa menunjuk berkas lain di disk privat. | Unggahan sungguhan (`ResearchOutputRequest`: pdf/jpg/jpeg/png/doc/docx, maks 10 MB, ≤ 5 per simpan) ke `research-outputs/{pengajuan}` di `PrivateUploadDisk`; kolom `original_name` + `size` (migration `2026_11_08_100000_…`, belum dijalankan ke DB dev). Berkas tertulis dihapus bila transaksi gagal; audit mencatat `files_added`. Unduh `research-outputs.files.show` dijaga `isAccessibleBy`, 404 sesudah otorisasi. Hapus berkas tidak dibangun. Skenario `[RO-OUTPUT-28]`. |
| W142 | Siapa melihat Daftar Luaran (W1 untuk luaran). | Induk yang diketuai atau diikuti (BR-SM-07), APPROVED, atau WITHDRAWN yang punya luaran (RO-OUTPUT-10), tanpa paginasi. Bentuknya sama dengan Daftar Monev, jadi diekstrak ke `Queries/Submission/TeamParentSubmissions::query(User, keptBy)` (`children` / `researchOutputs`). Flag sidebar `navigation.hasOutputSubmission` menggantikan `hasApprovedSubmission`; `HasApprovedSubmission` dihapus (O1-2). |
| W143 | Reviewer membaca luaran. | Detail luaran dijaga `isAccessibleBy` (termasuk reviewer aktif). Alasan baca-saja reviewer dibaca dari `FormSubmission::hasAssignedReviewer` (kini publik). Tanpa entri menu dan tanpa tautan dari layar reviewer (tanpa frame); `[RO-OUTPUT-19]` diikat di `ResearchOutputShowTest`. |
| W144 | Koreksi Operator atas luaran. | Tab "Luaran (n)" di `Submission/ManageShowPage` (hanya APPROVED/WITHDRAWN) = `ManagedOutputsTab`: baris yang sama dengan daftar peneliti, "Tambah Luaran" bila `outputAccess.canWrite`, dan "Perpanjang Batas Unggah Luaran" lewat `ExtendDeadlineDialog` untuk detail Upload Luaran (`OperatorSubmissionDetail::overrideTargetFor`, Parameterize Method). `WithdrawDialog` menampilkan "Luaran Terdaftar: n" (sisa W29: "Siklus Monev Aktif"). Minor (ditunda): sesudah diperpanjang, tab menampilkan Batas Akhir asli tanpa tanggal override; detailnya ada di dialog perpanjangan. Cek browser O1-2 6/6 di `simpas_browser`. |
| W145 | Route luaran. | `research-outputs.{index,create,store,show,edit,update,files.show}` sesuai `ux/ia/06_research_output`; `create?submission=` bukan angka → 404; sunting dikirim multipart `POST` + `_method=put`. `user.submissions.outputs.*` dan `Submission\ResearchOutputController` dihapus. Tanpa route hapus (`[RO-OUTPUT-23]`, DELETE → 405). Yatim `ResearchOutputManager.vue` (F99) dipindah ke nama route `research-outputs.store` supaya `FrontendRouteNamesTest` tetap hijau. |
| W146 | Satu definisi "boleh menulis". | `OutputWriteAccess::for(User, FormSubmission)` menyusun penegak yang ada (status → Policy `manageResearchOutput` → `PhaseAccessGate` atas `OutputUploadDetail`) dengan kalimat frame. Dipakai daftar, halaman tambah/sunting (GET tertolak = 403, bukan halaman denial), detail, dan tab Operator. `overrideUntil` tanpa alasan (BR-FE-16). Minor: lewat batas, Operator pun membaca kalimat "Hubungi Operator LPPM" (satu definisi untuk semua pembaca). |
| W147 | Yang tidak digambar dari UX. | Warna badge per tipe (badge outline seperti frame), grid kartu ikon langkah 1 (select seperti frame), skeleton form dan optimistic update, node "Upload Luaran" di `Monev/IndexPage` (W131), tautan reviewer (W143). `OutputInfoCard` rencana tidak dibuat (SectionCard + InfoGrid langsung di `ShowPage`). Hint "Tipe Luaran" frame menyebut enam tipe secara statis; tipe tambahan Admin tidak tersebut. |
| W148 | Siapa membuka statistik. | Gate `view-statistics` (`StatisticsPolicy::view`) = `reporting.export` ∨ `reporting.view-audit-log`, pemetaan `reporting.view` K1 yang sama dengan navigasi. Tanpa `submissions.view-all` angka tetap tampil, tetapi tautan drill-down dan daftar bernama tidak dikirim (`can.viewSubmissions`, `StatisticsOverview::run(…, $withLinks)`), karena tujuannya 403. |
| W149 | Definisi "Submission" di statistik. | Induk terkirim tanpa laporan monev (`ReportingSubmissions::base`), WITHDRAWN terhitung. Tahun = tahun submit pertama (`SubmissionYear`, di atas `FirstSubmitAt` W7). Fakultas/prodi = unit pengaju di pohon (`UnitFaculty`). `OperatorSubmissionList` menerima `year` dan menampilkan select "Tahun" (frame 02-01 tidak menggambarnya), supaya filter dari drill-down terlihat dan bisa dilepas. Pilihan tahun statistik selalu memuat tahun berjalan (nilai default) dan "Semua tahun" (`year=all`). |
| W150 | Definisi KPI. | Total Submission (basis W149); Total Anggaran Disetujui = jumlah `budget_line_items.total` tanpa baris terhapus pada state Approved, utuh (`Rupiah::format`, "Rp 6.500.000"); Luaran Terkumpul = luaran pada basis; Reviewer Aktif = `ReviewersWithinTenure` ∧ user aktif, meta "Internal n · Eksternal m", tanpa filter tahun; Tingkat Persetujuan = Disetujui ÷ (Disetujui + Ditolak), penyebut 0 → "—". |
| W151 | Bentuk grafik (skill dataviz). | Donut frame diganti segmented bar satu warna + daftar baris bertaut (tujuh state melebihi tiga slot tervalidasi; warna status dicadangkan). Skema per tahun = kolom berkelompok tiga tahun terakhir, mengabaikan filter Tahun. Fakultas/Prodi dan Luaran per Tipe = batang horizontal berperingkat, maks 10 + "Lainnya". Palet `lib/chartPalette.ts` = slot 1–3 acuan (lolos validator light; slot 3 < 3:1 → "Lihat tabel"). Label status memakai label state (Menunggu Review, Sedang Direview, …). |
| W152 | Statistik menjawab sekarang / sudah / berikutnya (permintaan pemilik 2026-11-08). | Tab *Saat Ini*: Pengajuan Berjalan (empat tahap berjalan, umur terlama sejak submit pertama), Siklus Monev, Cakupan Reviewer (0/1/2/3+ penugasan aktif), Beban Reviewer (evaluasi belum selesai per reviewer aktif + jumlah tanpa tugas); tanpa filter Tahun, tautan drill-down tanpa `year`. Tab *Riwayat*: tren per bulan (per tahun bila "Semua") pengajuan disubmit dan luaran terdaftar (`research_outputs.created_at`), tren kosong = "Tidak ada data"; kinerja per skema memakai `KpiDefinitions` yang sama dengan Ringkasan, pengajuan tanpa skema = baris "Tanpa skema". Tab *Tindak Lanjut*: tiga kartu (Menunggu Penugasan Reviewer, Menunggu Perbaikan Peneliti, Disetujui tanpa Luaran), maks 5 terlama dulu; tanpa `submissions.view-all` hanya angka. Umur = fakta, bukan ambang SLA. Semua tautan null tanpa `submissions.view-all` (W148). |
| W153 | Status monev agregat. | `MonevCycles::current` (siklus pertama yang belum Done) + `MonevBoardStatus::forParent` = satu definisi; himpunan induk `MonevCycleBoard::parents` dipakai papan dan `MonevStatusCounts`. Papan tidak punya filter status (W131), jadi keempat baris menaut ke papan berfilter periode/skema. N+1 siklus per induk dibiarkan (skala SIMPAS). |
| W154 | Ekspor tabular. | Lima jenis DDD, satu `ExportDefinition` per jenis: `label`, `filterKeys`, `headings`, `rowCount` (ambang BR-RPT-02, setelah scope dan filter, `[RPT-EXPORT-13]`), `rows`. Jenis berbasis pengajuan = induk terkirim dalam `SubmissionVisibility` (tanpa Draft/laporan monev); Rekap Monev satu baris per siklus (`MonevCycles`, dihitung per siklus di SQL); Audit Log dari `AuditScope`, subjek "{Kelas} #{id}" dan IP kosong sampai R1-4. Filter `year_from`/`year_to`/`scheme`/`status`; kunci di luar jenisnya dibuang `RequestExport` (`[RPT-EXPORT-12]`: filter tidak melebarkan scope). Format `xlsx` (bawaan) / `csv` lewat maatwebsite/excel. Riwayat mengirim `parameters` terbaca, `file_expires_at`, `link_expires_at`, `kind` (cetak menaut ke halaman pengajuannya). FE `jobState`: gagal → antrean/proses → terhapus → tautan basi → siap; jam berjalan menggambar ulang tautan basi tanpa klik; polling 10 detik hanya selama ada ekspor berjalan. |
| W155 | Audit Log. | IP: middleware web `CaptureAuditIp` → `Context::addHidden('audit.ip')` → `AuditTrail` menulis `activity_log.ip_address`; Context ikut ke queued job (IP peminta), scheduler/CLI null → "—", entri lama tetap null (BR-RPT-04). Subjek "{konteks} — {nama}" (`AuditSubjectLabel`; tanpa nama/terhapus "{konteks} #{id}", tipe lain "{Kelas} #{id}"); Pelaku "{nama} ({semua peran DDD, urutan `RoleCatalog`})", "Sistem" tanpa aktor, "User #{id}" bila akunnya terhapus (`AuditActorLabel`). Filter frame: cari (aksi, nama pelaku, judul form pengajuan), Waktu 7/30/90 hari/semua (bawaan 7, nilai asing → bawaan), Tipe Aksi (deskripsi dalam cakupan penanya, RPT-AUDIT-05), Pelaku (peran DDD/Sistem). IP hanya bagi `submissions.view-all` di layar, dialog, dan ekspor (UX pattern 07). "Lihat" = dialog nilai lama → baru, tanpa aksi Hapus/Sunting (RPT-AUDIT-02/03). Route `reporting.audit.index`; `audit-log.index` dihapus. |
| W156 | Route statistik. | `GET /reporting/stats/{tab?}` (`reporting.stats`), tab ∈ overview/now/history/follow-up, lainnya 404; hanya props tab aktif dihitung, panel Ringkasan di prop `overview`. Href tab membawa filter yang ada di URL; filter memuat ulang tab yang sama (`useFilters` menerima parameter route). `admin.stats.*`, `StatController`, empat halaman `Statistics/*`, dan Query statistik legacy dihapus. |
| W157 | `/` merender `WelcomePage` (K3), dan pengumuman publiknya sudah ada di halaman login sejak A1. | F99-1: `GET /` (`home`) mengalihkan tamu ke `login` dan user masuk ke `dashboard`. `WelcomePage`, `PublicAnnouncementFeed`, nama `welcome`, dan layar Roles/Permissions (`RoleController`, `PermissionController`) dihapus. |
| W158 | Penugasan form evaluasi terpisah (`assign-evaluation-forms`, `assign-forms-bulk`, `form-assignments.remove`) dan bacaan evaluasi legacy (`reviewer.evaluation-form.{show,submitted,progress,download-summary}`) tanpa frame, skenario, atau pemakai v2. | **Pemilik, 2026-11-08:** dihapus beserta Action/Query yang hanya mereka pakai, juga `ReviewerFormAssignmentPolicy::view`. DDD hanya mengenal auto-assign saat penugasan (`AssignReviewers` menugaskan form wajib) dan duplikasi ke pengganti. Reviewer yang sudah bertugas tetap melihat setiap form aktif di halaman evaluasinya dan bisa memulainya sendiri (`StartEvaluationForm`), tetapi form baru tidak ditugaskan otomatis kepadanya, jadi form wajib baru tidak mengikatnya. Kartu form evaluasi di `PhaseEditPage` kini memberi tahu: "Form baru tidak otomatis ditugaskan ke reviewer {n} pengajuan yang sedang direview; reviewernya tetap bisa memulainya dari halaman evaluasi." (Rumusan awal "set form dibekukan saat penugasan" keliru, dikoreksi review akhir F99-1.) (`EvaluationSectionData.reviewsInProgress`: pengajuan pada detail itu ber-reviewer aktif, berstatus Submitted/UnderReview/NeedsRevision/Resubmitted). Tambah/lepas form untuk reviewer yang bertugas = irisan baru bila kelak dibutuhkan. |
| W159 | Route ulir/komentar kembar: `admin.review-threads.store`, `review-threads.store`, `admin.review-comments.store`, `reviewer.review-comments.store`. | F99-1: tersisa `reviewer.review-threads.store` dan `review-comments.store`; Action yang memutuskan siapa boleh. Test admin/operator pindah ke route yang sama dengan hasil yang sama. |
| W160 | Jalur simpan legacy `user.form-submission.{save-draft,submit}` (hand-off S2b). | F99-1: route dan `UserFormController::saveDraft/submitForm` dihapus; `SubmissionAnswersRequest` dilebur ke `WizardStepRequest` (Collapse Hierarchy); flag `clearsEmptyAnswers` dan `FieldAnswerWriter::fill` dihapus. Test berskenario (FE-LOCK, FE-WINDOW-10, SM-STATE-04, FE-TEMPORAL-09, SM-SUBMIT-07/08/18) pindah ke `user.submissions.wizard.save`/`.submit`. |
| W161 | BR-FE-14 tidak tegak untuk jawaban: `SaveSubmissionDraft` tidak menggeser `updated_at`, jadi dua tab dengan cap yang sama sama-sama lolos. | F99-1: `touch()` hanya bila `FieldAnswerWriter::overwrite` melaporkan jawaban yang berubah, di transaksi yang sama; `[FE-LOCK-02] simpanan kedua dari cap yang sama ditolak 409` dan `[FE-LOCK-01] Lanjut tanpa mengubah isian tidak menggeser cap kunci` (klien selalu mengirim semua field non-berkas). Progres wizard tetap tidak menyentuh `updated_at`. Resolusi cap 1 detik (`timestamp(0)`). `SaveMonevReport` punya celah yang sama (di luar F99-1). |
| W162 | `auth.user.roles`/`is_reviewer`, prop `flash.message/error/success`, dan Pinia hanya dipakai layar legacy. | **Pemilik, 2026-11-08:** keduanya dihapus dari shared props; `Auth.user` = `User \| null` dengan `User` = `id, name, email, permissions`; accessor `is_reviewer` beserta kolom `users.is_reviewer` yang tak pernah ditulis dibuang (migration); `flash` tinggal `workloadWarnings` (pesan lain lewat flash native → toast); `pinia` dilepas dari `app.ts` dan `package.json`. Setelah merge: `npm ci` supaya `node_modules` ikut bersih. |
| W163 | Galat 403/404 tampil sebagai halaman bawaan Laravel (W10); URL tak dikenal tidak melewati middleware `web`, jadi user yang masuk pun tampil sebagai tamu. | F99-2: `App\Http\ErrorPage::respond` di `withExceptions` merender `Error/ErrorPage` untuk 403/404 selalu, 500/503 hanya bila debug mati; 419 → kembali (303) dengan toast "Sesi kedaluwarsa. Silakan ulangi." (`AuthLayout` kini ikut memasang `Toaster`); permintaan JSON tidak disentuh. Pesan 403 diteruskan hanya bila ditulis manusia (`abort`/`AuthorizationException` bermesan); bawaan Policy dan middleware Spatie berbahasa Inggris, jadi diganti copy halaman. `Route::fallback` ber-middleware `web` supaya 404 tahu siapa yang masuk; akibatnya GET ke URL khusus POST kini 404, bukan 405. |
| W164 | REV-DISCUSS-06 "penulis asli tetap terbaca": `ReplaceReviewer` memindahkan `reviewer_id`, jadi ulir warisan tertulis nama pengganti (W24). | **Pemilik, 2026-11-08:** kolom `review_summaries.author_reviewer_id` (FK `reviewers`, `restrictOnDelete`), diisi pemegangnya saat ulir dibuat (event `creating` model, tidak fillable) dan tidak pernah diubah; `reviewer_id` tetap pemegang yang berhak me-resolve. `ReviewThreadData.reviewer` = penulis, `inheritedBy` = pemegang bila berbeda; kartu ulir "Sari · diwarisi Andi". |
| W165 | IP audit di balik reverse proxy (E2E-09). | F99-2: `config/trustedproxy.php` membaca env `TRUSTED_PROXIES` (dipisah koma atau `*`; kosong = tidak ada). Middleware `TrustProxies` bawaan Laravel sudah membaca kunci itu, jadi rencana `TrustConfiguredProxies` tidak dibutuhkan. Topologi tetap keputusan deploy. |
| W166 | BR-REV-05/06 hanya diputuskan saat ulir ditutup: pengajuan yang reviewernya selesai tanpa membuka ulir menggantung di UNDER_REVIEW selamanya (ditemukan smoke F99-2). ADR 0006 menulis "dipanggil setiap kali evaluasi atau summary berubah". | F99-2: `SubmitEvaluationForm` memanggil `EvaluateSubmissionOutcome`; yang terakhir kini hanya memutuskan dari SUBMITTED/UNDER_REVIEW/RESUBMITTED (status lain dibiarkan, bukan `TransitionNotFound`). **Pertanyaan untuk pemilik:** ulir hanya bisa dibuka sesudah evaluasi reviewer itu beres (BR-FE-06), jadi evaluasi reviewer *terakhir* langsung memutuskan — ia tidak sempat membuka ulir sendiri sebelum APPROVED. Bila itu tidak diinginkan, perlu aturan DDD baru (mis. reviewer menandai "selesai meninjau"). |
| W167 | `EndToEndFlowSeeder` tidak bisa menjalankan alur yang ia cetak: peneliti tanpa `submissions.view-own`, periode tanpa tautan tahap (ADR 0014), Form tanpa pemilih skema, akun `pending`, tanpa form evaluasi. | F99-2: peran DDD dari `PermissionSeeder` (researcher, operator, reviewer_internal), `SubmissionPeriodPhase`, field `scheme_selector`, profil `active`, form evaluasi "Penilaian Substansi". Smoke `E2E_FIXTURE=full-flow` berjalan di atasnya; fixture `operator-flow.php` tetap berlaku. |

Selisih wireframe↔DDD yang ditemukan saat mengerjakan irisan dicatat di sini
dengan kode `W1`, `W2`, …, beserta keputusan yang diambil.

## 10. Temuan untuk review e2e akhir (F99)

Temuan minor yang sengaja tidak diperbaiki di irisannya, karena di luar cakupan atau perlu
keputusan lintas layar. F99 memeriksa ulang setiap baris lewat smoke Playwright dan
memperbaikinya, lalu menandai kolom Status. Irisan berikutnya menambah baris di sini, tidak
menghapusnya.

| # | Irisan | Temuan | Dampak bagi pengguna | Arah perbaikan | Status |
| --- | --- | --- | --- | --- | --- |
| E2E-01 | R1-3 | `AppLayout` `main` tanpa `min-w-0`, jadi tabel lebar mendorong seluruh halaman ke samping di 390px: Export Data 487px, daftar Operator 687px (sudah ada sebelum R1-3). | Di ponsel seluruh halaman bergeser horizontal, bukan hanya tabelnya. | `min-w-0` pada `main` di `AppLayout`, lalu cek tiap layar bertabel di 390px. | ☑ 2026-11-09 — `min-w-0` pada `SidebarInset` (anak baris flex yang sebenarnya mendorong), satu `<main>`, aksi `PageHeader` `flex-wrap`; 8 layar bertabel 390px di browser, 0 galat konsol (commit penutup F99-2) |
| E2E-02 | R1-3 | Galat validasi dari "Coba Lagi"/"Ekspor Ulang" (mis. skema baris itu sudah dihapus) masuk ke error halaman yang tidak ditampilkan field mana pun. | Tombol tampak tidak bereaksi. | Tampilkan galat `router.post` sebagai toast atau banner di `ExportPage`. | ☑ 2026-11-08 — `onError` toast di `ExportPage` (`4eccb56`) |
| E2E-03 | R1-3 | Polling riwayat `only: ['jobs']` tetap menghitung ulang `ExportFormOptions` (tahun, skema, periode) tiap 10 detik, karena props itu array, bukan closure. | Tidak terlihat; kueri tambahan selama ekspor berjalan. | Bungkus pilihan formulir dalam closure (atau `Inertia::optional`) di `Reporting\ExportController::index`. | ☑ 2026-11-08 — satu prop lazy `options` (`f1e6ecf`) |
| E2E-04 | R1-3 | Baris `export_jobs` dari sebelum migration `format` adalah berkas CSV, tetapi terbaca `xlsx` (default kolom). | "Ekspor Ulang" pada baris lama menghasilkan Excel, bukan CSV. | Isi `format` baris lama dari ekstensi `file_path` saat deploy, atau terima selisih ini. | ☑ 2026-11-08 — backfill di migration yang sama, belum pernah di-deploy (`5412952`) |
| E2E-05 | R1-3 | Label "Rentang Tahun" (`for="export-years"`) tidak menunjuk kontrol mana pun; kedua select tahun hanya ber-`aria-label` "Dari tahun"/"Sampai tahun". | Klik label tidak memfokuskan apa pun; pembaca layar membaca label tanpa kontrol. | `fieldset` + `legend` "Rentang Tahun" untuk dua select. | ☑ 2026-11-08 — `fieldset` + `legend` (`6665de6`) |
| E2E-06 | R1-4 | Subjek laporan monev (pengajuan anak) tertulis "Submission — {judul form}", padahal frame menulis "Monev I — Smart Farming". | Operator tidak bisa membedakan entri laporan monev dari entri pengajuan induknya tanpa membuka detail. | Di `AuditSubjectLabel`, pengajuan ber-`parent_submission_id` memakai nama siklus (`MonevCycles`) + judul induk. | ☑ 2026-11-09 — "Monev I — {judul induk}" (`1111ff7`) |
| E2E-07 | R1-4 | Pencarian Audit Log hanya mencocokkan aksi, nama pelaku, dan judul form pengajuan (rencana W155); subjek lain (Skema, Periode, User, Form, Pengumuman) tidak ikut dicari. | Mengetik "DIPA" tidak menemukan entri "Skema — DIPA", padahal placeholder berbunyi "Cari subjek / pelaku...". | Peta kolom nama per tipe di `AuditSubjectLabel` dipakai juga oleh `AuditLogList::search` (`orWhereHasMorph` per tipe). | ☑ 2026-11-09 — `AuditSubjectLabel::NAME_COLUMNS` + `searchable`, termasuk judul induk laporan monev (`1111ff7`) |
| E2E-08 | R1-4 | Pelaku yang akunnya sudah di-soft-delete tertulis "User #{id}", bukan namanya. | Nama pelaku hilang dari jejak audit lama; tetap bukan "Sistem". | Muat `causer` dengan `withTrashed()` di `AuditLogList`/`AuditLogExport`. | ☑ 2026-11-09 — causer tanpa scope soft delete di daftar dan ekspor (`1111ff7`) |
| E2E-09 | R1-4 | `CaptureAuditIp` memakai `$request->ip()`, dan `trustProxies` belum dikonfigurasi di `bootstrap/app.php`. | Bila produksi berada di balik reverse proxy (nginx, load balancer, Cloudflare), semua entri audit mencatat IP proxy, bukan IP pelaku. Memercayai semua proxy tanpa proxy sungguhan membuka pemalsuan IP lewat `X-Forwarded-For`. | Keputusan pemilik saat deploy: `->withMiddleware(fn ($m) => $m->trustProxies(at: [IP proxy]))` sesuai topologi server ITK. | ☑ kode (W165); topologi = keputusan deploy (`1111ff7`) |
| E2E-10 | R1-4 | Pencarian Audit Log meneruskan `%`/`_` dari input ke `whereLike` tanpa di-escape. | Mengetik `%` mencocokkan semua entri dalam cakupan (cakupan BR-RPT-05 tetap utuh); hanya hasil cari yang terlalu lebar. | Escape `%`, `_`, dan `\` sebelum `whereLike`. | ☑ 2026-11-09 — `LikePattern::contains` (`1111ff7`) |
| E2E-11 | F99-2 | "Judul" di langkah 7 wizard, detail, dan daftar pengajuan adalah judul **Form**, bukan jawaban "Judul Penelitian". | Semua pengajuan pada Form yang sama tampil berjudul sama ("Pengajuan Penelitian 2026"); peneliti dan Operator harus membuka detail untuk membedakannya. | Keputusan pemilik: field judul berperan khusus (seperti `scheme_selector`) atau kolom judul pengajuan. | ☐ |
| E2E-12 | F99-2 | Langkah 6 wizard selalu berbunyi "Proposal PDF wajib ada sebelum submit", juga pada Form tanpa field berkas; submit tetap lolos. | Copy menyatakan aturan yang tidak ditegakkan. | Tampilkan hanya bila Form punya field berkas wajib, atau tegakkan lewat Form. | ☐ |
| E2E-13 | F99-2 | [REV-OUTCOME-09] persetujuan hampa (`needs_review = false`, ADR 0017) tidak punya pemicu di aplikasi: `SubmitFormSubmission` tidak memanggil `EvaluateSubmissionOutcome`. | Pengajuan pada tahap tanpa review menggantung di SUBMITTED. | Panggil saat submit; periksa dulu dampaknya pada laporan monev bertahap tanpa review (`MonevCycles` membaca `canProceed`, bukan status). | ☐ |
| E2E-14 | F99-2 | "Cetak Surat Keterangan Diterima" ditawarkan pada pengajuan yang masih Menunggu Review/Sedang Direview (pertanyaan pemilik yang sama sejak S2: juga pada Draft). | Peneliti bisa mencetak surat "diterima" sebelum ada keputusan. | `PrintableDocumentChoices` menyaring per status — keputusan pemilik. | ☐ |
