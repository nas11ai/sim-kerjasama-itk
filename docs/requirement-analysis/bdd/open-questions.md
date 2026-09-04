# Register Kontradiksi & Pertanyaan Terbuka

Kontradiksi antara DDD, wireframe, dan kode legacy **tidak diputuskan sepihak** saat
menulis skenario. Ia dicatat di sini dengan opsi dan rekomendasi, diputuskan pemilik
produk, baru skenarionya mengunci keputusan itu.

## Terbuka

_Kosong._ Kedelapan entri terakhir (#16–#23) ditutup 2026-09-04.

Register ini tetap hidup: kontradiksi berikutnya akan muncul saat skenario M1 mulai
diimplementasikan, dan tempatnya di sini — bukan parkir di berkas milestone, kekeliruan
pembukuan yang sudah dikoreksi.

### 9.1 Anggaran melebihi batas skema — UI vs aturan

`RESEARCHER-02-04` (wizard langkah 5) memuat kalimat:

> "Jika total melebihi batas maksimum skema, muncul peringatan — **Anda tetap dapat
> melanjutkan submit**."

BR-BUD-01 (kanonik) dan BR-SM-06 (cermin) berbunyi sebaliknya: total **tidak boleh**
melebihi `scheme.max_budget`. Ini bukan perbedaan penekanan — UI menurunkan batas keras
finansial menjadi peringatan lunak.

**Keputusan (2026-09-04): aturan menang, UI menyesuaikan.** Anggaran menyangkut
kepatuhan dana dan salahnya baru ketahuan saat audit; itu kelas kesalahan yang paling
mahal ditemukan belakangan. Kalimat di `RESEARCHER-02-04` diganti menjadi pernyataan
bahwa pengajuan tidak dapat disubmit selama total masih melebihi batas. Skenario
`[BUD-LIMIT-01]` mengunci penolakan itu.

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

**Keputusan (2026-09-04): monev dikecualikan secara eksplisit** lewat **BR-MON-09**
yang baru di BC Monev — bukan dengan membiarkan BR-REV-12 ditafsir bebas. Alasannya
kebutuhan bisnis: tujuan monev adalah mengoreksi arah kegiatan sebelum selesai, dan itu
mustahil kalau catatan reviewer baru terlihat setelah siklusnya berakhir.

Wireframe monev ternyata **sudah benar**; yang kurang adalah aturannya. BR-REV-12 kini
menautkan pengecualian ini supaya tidak terbaca sebagai kelalaian. Dikunci
`[MON-REVIEW-07..09]` — yang terakhir menjaga pengecualian tetap sempit dengan
membuktikan gerbang pengajuan awal tidak ikut longgar.

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

Ini **bukan** kontradiksi — tidak ada yang saling bertentangan. Ini pekerjaan desain
yang belum dilakukan, dan ia mendahului implementasi.

**Keputusan (2026-09-04): kelimanya dibuatkan layar.** Delapan frame baru, wireframe
naik dari 57 ke 65. Rinciannya di [gap-closure](../../design/gap-closure.md); yang
paling menuntut keputusan desain adalah Target Luaran, yang ditempatkan di **langkah 3
wizard** karena pilihan tipenya dibatasi skema (BR-SM-14) sehingga tidak bisa mendahului
pemilihan skema — plus tab tersendiri di halaman detail, karena BR-SM-15 membolehkannya
diubah setelah wizard selesai.

Dengan layarnya ada, `[SM-TARGET-01..09]`, `[SM-MEMBER-01..03]`, `[SM-WIZARD-01..09]`,
dan `[NOT-INAPP-01..03]` tidak lagi terhalang.

### 12.1 Jendela kerja setelah submit

Contoh lifecycle memberi Detail 1 (Pengajuan) dan Detail 2 (Evaluasi Pengajuan) deadline
yang sama: `"Batas Submit"`. Dibaca bersama BR-FE-08 ("akses di-hard-block jika deadline
lewat"), dua alur menjadi mustahil:

| Alur | Kapan terjadi | Deadline detailnya | Akibat |
| ---- | ------------- | ------------------ | ------ |
| Evaluasi reviewer | Setelah pengajuan ditutup | `Batas Submit` | Jendela kerja nol |
| Revisi researcher | Setelah review selesai | `Batas Submit` (Detail 1) | BR-BUD-03 menjanjikan anggaran bisa disunting saat NEEDS_REVISION, tapi detailnya sudah terkunci |

Keempat label `SubmissionDate` yang dikenal — `Batas Submit`, `Batas Monev I`,
`Batas Monev II`, `Batas Akhir` — tidak memuat tanggal untuk evaluasi maupun revisi.

**Sebagian sudah ditutup.** BR-FE-17 menetapkan bahwa deadline sebuah detail mengikat
aktor detail itu sendiri, dan setiap tahap setelah submit wajib punya `SubmissionDate`
sendiri. Modelnya sudah mendukung — BR-FE-07 mewajibkan tiap detail punya
`submission_date_id`; yang keliru hanya contohnya.

**Keputusan (2026-09-04): tanggal tetap per periode.** Tiga pilihan yang ditimbang:

| Opsi | Bentuk | Konsekuensi |
| ---- | ------ | ----------- |
| Tanggal tetap per periode | `Batas Evaluasi` dan `Batas Revisi` sebagai `SubmissionDate` baru | Paling sederhana, seragam untuk semua pengajuan, memakai mesin yang sudah ada |
| Relatif per pengajuan | "14 hari sejak status NEEDS_REVISION" | Adil bagi yang direview belakangan, tapi butuh tanggal per-submission — mekanisme baru |
| Tanpa batas, ditutup Operator | Revisi terbuka sampai Operator menutup periode | Paling luwes, tapi menghilangkan tekanan tenggat dan menggantungkan penutupan pada ingatan Operator |

**Opsi pertama dipilih.** Ia memakai `SubmissionDate` yang sudah ada, otomatis mewarisi
BR-SCH-08 (perpanjangan wajib memberi tahu pemilik draft), dan keterlambatan individual
tetap ditangani `FormSubmissionOverride` — persis pembagian kerja yang ditetapkan
[ADR 0014](../../adr/0014-time-boxed-access-single-override-mechanism.md). Opsi kedua
ditolak karena menambah mekanisme waktu keempat, yang justru dilarang ADR itu; opsi
ketiga ditolak karena menggantungkan penutupan periode pada ingatan Operator.

`Batas Evaluasi` dan `Batas Revisi` kini terdaftar sebagai label `SubmissionDate` baku di
[`02_ubiquitous_language.md`](../ddd/02_ubiquitous_language.md), dan contoh lifecycle di
[`supporting/02_monev.md`](../ddd/supporting/02_monev.md) sudah dikoreksi supaya tidak
menularkan kekeliruan yang sama. Berapa panjang tiap jendela adalah konfigurasi periode —
bukan aturan — jadi tidak perlu BR sendiri.


## Terselesaikan

| # | Perkara | Keputusan | Tanggal |
| - | ------- | --------- | ------- |
| 5 | BR-BUD-06 menyebut "period config" yang tidak terdefinisi; entitas `SubmissionOuter` ternyata luaran yang dijanjikan, bukan data anggaran | Pindah ke BC Submission sebagai `SubmissionTargetOutput`; BR-BUD-06 dicabut, diganti BR-SM-14 dan BR-SM-15; pembatas tipe memakai `scheme.rules.allowed_output_types` yang sudah ada — tidak ada config baru. Target tetap bisa diubah pasca-APPROVED dengan audit trail wajib; hanya `submitted_by` yang boleh mengubah. Jejak lengkap: spec §8.1 | 2026-09-03 |
| 6 | Arah penelusuran org tree di BR-FE-02 terbalik di DDD — Form ber-scope luas justru tak terlihat oleh peneliti di prodi | **Arah anak-menjangkau-leluhur disetujui.** `FormAccessControl.organization_id` harus ancestor-or-self dari organisasi user. Ditulis sebagai [ADR 0013](../../adr/0013-form-access-control-matches-ancestor-organizations.md); pseudocode, CTE, dan bunyi BR-FE-02 di DDD sudah dikoreksi. Lihat §6.1 | 2026-09-04 |
| 7 | "Active Submission" di BR-SCH-01 tidak terdefinisi — apakah APPROVED menghalangi penonaktifan skema? | **APPROVED dihitung aktif.** Active = `status NOT IN ('rejected', 'withdrawn')` — predikat yang sudah dipakai index unik BR-SM-01. Definisi masuk `02_ubiquitous_language.md`, bunyi BR-SCH-01 diperjelas, dan `[SCH-RULE-15]` mengunci perilakunya. Lihat §7.1 | 2026-09-04 |
| 8 | Dua skenario menguji aturan yang tidak punya nomor BR | **Keduanya diberi nomor.** BR-FE-16 (visibility `reason` override) dan BR-SCH-10 (skema tidak aktif tidak ditawarkan); tag `[FE-OVERRIDE-09]` dan `[SCH-RULE-14]` dipindahkan ke nomor barunya. Lihat §8.1 | 2026-09-04 |
| 9 | Wireframe membolehkan submit dengan anggaran melebihi batas skema | **Aturan menang, UI menyesuaikan.** BR-BUD-01 tetap batas keras; kalimat "tetap dapat melanjutkan submit" di `RESEARCHER-02-04` diganti pernyataan penolakan. Dikunci `[BUD-LIMIT-01]`. Jejak: [gap-closure §5](../../design/gap-closure.md) | 2026-09-04 |
| 10 | Apakah BR-REV-12 berlaku untuk siklus monev | **Monev dikecualikan eksplisit** lewat BR-MON-09 baru — hasil evaluasi terlihat begitu reviewer submit, karena monev bersifat formatif. Pengecualian dijaga tetap sempit oleh `[MON-REVIEW-09]`. Jejak: [gap-closure §6](../../design/gap-closure.md) | 2026-09-04 |
| 11 | Lima kebutuhan bisnis belum punya layar sama sekali | **Delapan frame baru dibuat** (57 → 65): wizard langkah 2/3/4/6/7, Target Luaran, Notifikasi, dan detail pengajuan ditolak. Target luaran ditempatkan di langkah 3 karena pilihannya dibatasi skema. Jejak: [gap-closure](../../design/gap-closure.md) | 2026-09-04 |
| 1 | `Gate::before` memberi Super Admin setiap ability tanpa syarat, sementara Action menegakkan BR-SM-04 — UI menampilkan tombol Submit yang dijamin gagal | **Invarian domain tidak pernah dilewati.** `submit`/`resubmit` dikecualikan dari bypass lewat konstanta `INVARIANT_ABILITIES`; ability lain tetap di-bypass. Admin yang harus mengajukan untuk orang lain memakai transfer kepemilikan. [ADR 0015](../../adr/0015-gate-before-never-bypasses-domain-invariants.md); dikunci `[SM-SUBMIT-13/14]` | 2026-09-04 |
| 2 | Auto-reject di kode warisan fork vs BR-REV-07/BR-REV-14 | **Bukan pertanyaan — arahnya sudah diputuskan [ADR 0006](../../adr/0006-rejection-is-manual-only.md).** Yang tersisa pekerjaan: `[REV-OUTCOME-08]` mengunci perilakunya, ditambah penjagaan struktural (`transitionTo(Rejected::class)` di luar `RejectSubmission` harus nol) yang dicatat di `non-behavioral.md` | 2026-09-04 |
| 3 | Enum `SubmissionStatus` frontend 5 nilai vs backend 8 | **Frontend menyusul, bukan backend melunak.** Sisi wireframe sudah ditutup (kedelapan status punya representasi). Enum TypeScript wajib memuat 8 nilai dan **diturunkan dari kontrak Inertia**, bukan didaftar tangan — [ADR 0007](../../adr/0007-inertia-props-as-frontend-contract.md). `[SM-STATE-02]` memakai kedelapannya | 2026-09-04 |
| 4 | Perilaku user-facing saat index unik BR-SM-01 kena belum ditetapkan | **Dicegat sebelum menyentuh database** — pesan validasi menyebut pengajuan aktif yang sudah ada beserta tautannya, dan **draft tetap DRAFT serta tetap bisa disunting**. Index tetap ada sebagai jaring pengaman lomba-balap, dengan pesan yang sama. Aturannya **BR-SM-16** baru; dikunci `[SM-SUBMIT-15..17]` | 2026-09-04 |
| 12 | Tidak ada tanggal untuk tahap setelah submit — evaluasi dan revisi memakai ulang `"Batas Submit"` | **Tanggal tetap per periode.** `Batas Evaluasi` dan `Batas Revisi` ditambahkan sebagai label `SubmissionDate` baku; contoh lifecycle dikoreksi. Memakai mesin yang sudah ada, otomatis mewarisi BR-SCH-08, dan keterlambatan individual tetap ditangani override ([ADR 0014](../../adr/0014-time-boxed-access-single-override-mechanism.md)). Aturannya **BR-FE-17**; dikunci `[FE-WINDOW-01..04]` | 2026-09-04 |
| 13 | Apa arti `can_proceed` secara operasional — siapa yang menyetelnya? | **Pertanyaannya salah bentuk.** `canProceed()` adalah *method* di aggregate, bukan kolom — BR-MON-02 menulisnya `can_proceed = true` seolah kolom. Tidak ada yang menyetelnya: ia turunan keadaan, sejajar `isArchived()`. Bernilai true kalau detail sudah disubmit DAN, bila `needs_review = true`, evaluasinya selesai tanpa summary `open`. BR-MON-02 diperjelas, istilah masuk ubiquitous language; dikunci `[MON-CYCLE-03..05]` | 2026-09-04 |
| 14 | Apakah `not_required` dihitung "selesai" untuk pemicu BR-REV-05/06 | **Ya — kalau tidak, ia kebuntuan permanen.** BR-REV-05/06 semula menulis "semua `evaluation_status = completed`"; dibaca harfiah, reviewer `not_required` tidak akan pernah menjadi `completed` dan submission terkunci selamanya tanpa ada yang salah. Bunyi keduanya dikoreksi jadi "`completed` atau `not_required`", seragam dengan BR-FE-06 yang sudah begitu sejak awal; dikunci `[REV-OUTCOME-06]` | 2026-09-04 |
| 15 | Siapa me-resolve `ReviewSummary` kalau reviewer-nya sudah `replaced` | **Reviewer pengganti mewarisinya.** Tanpa aturan ini summary itu tak akan pernah bisa ditutup — reviewer lama tidak berwenang, reviewer baru tidak merasa berhak — dan submission terkunci di NEEDS_REVISION. Penulis asli tetap terbaca dari record lama (BR-REV-10). Aturannya **BR-REV-15** baru; dikunci `[REV-DISCUSS-06]` | 2026-09-04 |
| 16 | Wizard boleh dilompati mundur-maju bebas, atau hanya maju berurutan? | **Navigasi bebas; gerbangnya prasyarat data, bukan urutan langkah.** Kendala sesungguhnya bukan nomor langkah melainkan ketergantungan data — anggaran butuh `scheme_id`. Mengunci berurutan menyiksa: memperbaiki judul di langkah 2 saat di langkah 6 tak boleh menuntut mundur empat layar. Langkah 7 boleh dibuka kapan saja; tombol Submit-nya yang bergerbang. **BR-SM-17** baru; dikunci `[SM-WIZARD-08/10/11]` | 2026-09-04 |
| 17 | User `pending` boleh menyimpan DRAFT, atau hanya dilarang submit? | **Boleh menyimpan DRAFT.** Verifikasi menggerbang *komitmen*, bukan *persiapan* — tidak ada yang mengikat sampai submit, sementara verifikasi NIDN makan waktu berhari-hari. BR-IAM-04 sendiri bilang "bisa login"; kalau login tapi tak bisa apa-apa, layar Pending Verification jadi jalan buntu. Bunyi BR-IAM-04 diperjelas; dikunci `[IAM-VERIFY-06]` | 2026-09-04 |
| 18 | Nasib undangan yang belum terpakai saat organisasinya dihapus | **Token dicabut, bukan memblokir penghapusan.** Token basi tidak boleh menyandera reorganisasi selamanya; sebaliknya token menggantung ke organisasi mati juga tidak boleh. Operator melihat berapa token akan tercabut sebelum mengonfirmasi, dan undangan yang terlanjur dibuka mendarat di layar token tidak berlaku yang sudah ada — nol layar baru. BR-IAM-06 diperluas; dikunci `[IAM-REG-10/11]` | 2026-09-04 |
| 19 | Target luaran dan realisasi wajib cocok tipenya? | **Sengaja TIDAK ditegakkan.** Penelitian memang menyimpang — janji artikel jurnal bisa berakhir sebagai paten. Memaksa cocok mendorong peneliti berbohong di target atau menyembunyikan luaran nyata; keduanya lebih buruk daripada ketidakcocokannya. Prinsip: **batasi yang harus benar (taksonomi), ukur yang sebaiknya benar (pemenuhan)**. Pemenuhan jadi metrik pelaporan, bukan validasi lintas-aggregate. Didokumentasikan di `03_research_output.md` | 2026-09-04 |
| 20 | Siklus monev yang di-freeze bisa dibuka lagi kalau penarikan dibatalkan? | **Tidak — WITHDRAWN terminal.** Membalikkan penarikan menuntut seluruh pembekuan hilir ikut dipulihkan dengan benar, untuk peristiwa yang jarang. State machine sengaja dijaga **asiklik** karena itu jauh lebih murah dinalar dan diaudit. Kalau penarikan keliru, jalurnya pengajuan baru yang merujuk yang lama. BR-SM-11 diperluas; dikunci `[SM-WITHDRAW-05]` dan `[MON-CYCLE-12]` | 2026-09-04 |
| 21 | Daftar lengkap event yang butuh email versus cukup in-app | **Pertanyaannya tidak perlu dijawab di muka — jadikan konfigurasi.** Channel per tipe event mengikuti preseden `scheme.rules` JSONB: di-seed dengan default, disunting Admin tanpa deploy. Aturannya satu — setiap event wajib punya deklarasi channel, dan yang tanpa deklarasi ditolak saat dipublish. Daftar isinya bukan aturan, jadi tidak dibekukan di DDD. **BR-NOT-06** baru; dikunci `[NOT-CHANNEL-03/04]` | 2026-09-04 |
| 22 | Aksi apa saja yang wajib masuk audit log | **Diturunkan dari arsitektur, bukan daftar manual.** Kebalikan #21: ini justru **tidak boleh** jadi konfigurasi — orang bisa mematikan audit. Karena AGENTS.md mewajibkan satu Action = satu use case yang mengubah state, "setiap Action mencatat audit" otomatis berarti "setiap perubahan yang seseorang bertanggung jawab atasnya". Daftar yang mustahil ketinggalan karena bukan daftar. **BR-RPT-09** baru; dikunci `[RPT-AUDIT-06/07]` + penjagaan struktural di `non-behavioral.md` | 2026-09-04 |
| 23 | Researcher boleh mengekspor data miliknya sendiri? | **Boleh, ter-scope dengan aturan yang sama persis seperti BR-RPT-05.** Ekspor tidak melahirkan konsep scoping kedua — query dan batasnya sama, hanya pemanggilnya berbeda. **BR-RPT-10** baru; dikunci `[RPT-EXPORT-08/09]` | 2026-09-04 |

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
