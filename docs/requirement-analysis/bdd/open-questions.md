# Register Kontradiksi & Pertanyaan Terbuka

Kontradiksi antara DDD, wireframe, dan kode legacy **tidak diputuskan sepihak** saat
menulis skenario. Ia dicatat di sini dengan opsi dan rekomendasi, diputuskan pemilik
produk, baru skenarionya mengunci keputusan itu.

## Terbuka

*Kosong.*

Bagian ini tidak akan lama kosong, dan memang tidak dirancang untuk kosong: register ini
hidup selama implementasi berjalan, dan kontradiksi berikutnya akan muncul persis seperti
#25–#28 muncul — saat kode ditulis, bukan saat spec dibaca. Tempatnya tetap di sini,
bukan parkir di berkas milestone.

Yang berubah bukan bahwa utangnya habis, melainkan bahwa utangnya kembali jujur: tidak
ada lagi entri yang duduk di *Terbuka* padahal keputusannya sudah mendarat.

## Terselesaikan

Entri **#29–#34 diputuskan atas wewenang yang didelegasikan** pemilik produk untuk
irisan M3 ("kamu boleh memutuskan sendiri, asalkan mengikuti best practice yang sudah
ada, sesuai kebutuhan bisnis, dan mudah di-develop serta di-maintain"). Keenamnya
mengikuti sumber yang sudah ada — DDD sebagai sumber kebenaran, wireframe sebagai
konfirmasi kedua — dan tidak satu pun memutuskan arah yang bertentangan dengan
keduanya. Alasan tiap keputusan ditulis lengkap supaya bisa dibantah kalau keliru.

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
| 24 | `Gate::before` membuat Super Admin menembus `BudgetLineItemPolicy` — pada APPROVED, admin bisa menyunting anggaran sementara pemiliknya tidak | **`update-budget` jadi invarian domain.** Bypass itu melanggar BR-BUD-02 *dan* BR-BUD-09, yang menyebut daftar tertutup `submitted_by`/`co_investigator`. Ability masuk `INVARIANT_ABILITIES`; konsekuensinya berlaku di semua status, bukan hanya terminal. [ADR 0015](../../adr/0015-gate-before-never-bypasses-domain-invariants.md) diperluas; dikunci `[BUD-LOCK-03]` dan `[BUD-WHO-04]`. Lihat §24.1 | 2026-09-04 |
| 25 | Delegasi submit ke `SubmitFormSubmission` yang diwajibkan spec irisan HTTP tidak pernah masuk plan | **Didelegasikan.** `submitForm()` menyusut jadi orkestrasi tipis dan meneruskan keputusannya ke Action. Alasan penahan yang tercatat di register **keliru** — cabang `needs_review` yang disebut harus ikut dipindahkan tidak pernah bekerja (`Draft → Approved` tidak diizinkan state machine, selalu `TransitionNotFound`). Cabangnya dicabut lewat [ADR 0016](../../adr/0016-submit-never-approves.md); dikunci `[SM-SUBMIT-18]`. Lihat §25.1 | 2026-09-06 |
| 26 | `guardRequiredDocuments()` menyaring `is_required` mentah dan mengabaikan `FormField::isRequiredFor()` | **`isRequiredFor()` akhirnya punya pemanggil produksi.** Kedua jalur memakai `RequiredFieldGuard::assertComplete()`, yang juga berhenti membatasi diri pada field bertipe `file` — setiap field wajib diperiksa. Temporal Field Binding BR-FE-11 menyala di produksi; `FE-TEMPORAL-01..09` keluar dari `pending.txt`. Lihat §26.1 | 2026-09-06 |
| 27 | BR-SM-02 tidak ditegakkan pada jalur resubmit | **Resubmit mengecek ulang, dengan jangkar yang sama.** `ResubmitFormSubmission` memanggil guard yang sama memakai `created_at` asli — revisi tidak menarik field wajib baru ke pengajuan yang sudah berjalan, jadi mengecek ulang bukan memindahkan gawang. Dikunci `[SM-SUBMIT-19]`. Lihat §27.1 | 2026-09-06 |
| 28 | Draft baru tidak bisa dimulai setelah pengajuan Form itu berakhir terminal | **Diperbaiki di pencariannya, bukan di gerbangnya** — nol gerbang dicabut. `ResolveWizardSubmission` mencocokkan `(form_id, submitted_by, submission_period_id)` berstatus `DRAFT` atau `NEEDS_REVISION` — persis yang diizinkan `FormSubmissionPolicy::update()`. Baris terminal melahirkan draft baru; resolver kosong menjawab `409`. Dikunci `[SM-WIZARD-12]`. Lihat §28.1 | 2026-09-06 |
| 29 | Jalur undangan melahirkan akun `pending` atau `active`? BDD bilang `pending`, DDD dan wireframe bilang `active` | **`active` langsung — BDD-nya yang salah.** Verifikasi memverifikasi **NIDN**; mitra eksternal tidak punya NIDN, jadi `pending` berarti antrian yang tidak akan pernah bisa dibersihkan siapa pun. Operator sudah menyaring saat menerbitkan token. `[IAM-REG-03/08]` dikoreksi; **BR-IAM-12** baru menyatakan bekal awal tiap jalur secara utuh (role, organisasi, permission, status); dikunci `[IAM-REG-12..14]`. Lihat §29.1 | 2026-09-07 |
| 30 | Penolakan verifikasi NIDN tidak punya aturan maupun skenario, padahal tombolnya ada di layar | **Dinomori sebagai BR-IAM-13.** Wireframe `OPERATOR-08-02` sudah menuntut alasan wajib yang dikirim ke pendaftar, dan DDD sudah menggambar cabangnya — yang tidak ada cuma nomornya. Penolakan dibuat **tidak terminal**: email dan NIDN yang terlanjur terpakai membuat pendaftaran ulang mustahil (BR-IAM-02), jadi penolakan permanen berarti mematikan akun gara-gara satu digit salah ketik. Dikunci `[IAM-VERIFY-07..10]` | 2026-09-07 |
| 31 | Wireframe organisasi menawarkan **Nonaktifkan**, DDD dan BDD cuma mengenal **hapus** | **Keduanya ada, dengan ambang berbeda.** Hapus (BR-IAM-08) ditolak selama ada UserProfile apa pun; nonaktif (**BR-IAM-14** baru) ditolak selama ada user **aktif**. Menyamakannya berarti memilih antara mustahil menonaktifkan fakultas yang pernah berisi orang, atau menghapus unit yang jejaknya masih dibaca. Organisasi nonaktif memutus rantai leluhur access control dan membatalkan tokennya. Dikunci `[IAM-ORG-04..08]`. Lihat §31.1 | 2026-09-07 |
| 32 | BR-IAM-02 menuntut NIDN unik "di seluruh sistem", sementara jalur undangan tidak pernah meminta NIDN | **Keunikan berlaku atas nilai yang terisi.** Dibaca harfiah, akun undangan kedua akan bentrok dengan yang pertama pada nilai kosong yang sama — dan seluruh jalur mitra eksternal mati di pendaftar kedua. Bunyi BR-IAM-02 diperjelas; dikunci `[IAM-REG-15]` | 2026-09-07 |
| 33 | Tombol **Cabut** undangan ada di `ADMIN-08-01`, tapi pencabutan bukan salah satu sebab token tidak berlaku di BR-IAM-06 | **Ditambahkan sebagai sebab keempat.** BR-IAM-06 sudah menyebut kata "dicabut", tapi hanya sebagai akibat penghapusan organisasi — bukan sebagai aksi Operator yang berdiri sendiri. Tanpa itu, token yang bocor tidak punya cara dimatikan selain menunggu kedaluwarsa. Dikunci `[IAM-REG-16]` | 2026-09-07 |
| 34 | BR-IAM-11 tidak menyatakan apa yang terjadi kalau orang yang sudah reviewer ditunjuk lagi | **Penunjukan idempoten per orang.** `OPERATOR-08-04` menampilkan satu baris per reviewer dengan aksi *Edit Periode* — record kedua akan membuat penghitungan beban (BR-REV-04) dan keterlihatan skor (BR-REV-11) membaca dari himpunan yang ambigu, dan itu tidak gagal mencolok, ia cuma salah. Bunyi BR-IAM-11 diperluas; dikunci `[IAM-REVIEWER-04..06]` | 2026-09-07 |
| 35 | BR-RO-07 berbunyi "**hanya** `submitted_by` dan `co_investigator`", sementara tabel Access Control di dokumen yang sama memberi Operator/Admin akses penuh lewat `outputs.manage` | **Keduanya benar pada sumbu yang berbeda; bunyi BR-nya yang kabur.** BR-RO-07 bicara soal siapa **di dalam tim penelitian** — di situ `member` dan Reviewer memang read-only. Staf LPPM ada di sumbu lain: luaran memasok pelaporan, dan koreksi datanya tugas mereka. Sengaja **tidak** mengikuti preseden ADR 0015 yang menjadikan `update-budget` invarian domain — anggaran adalah komitmen peneliti, luaran adalah laporan fakta, dan menyamakan keduanya akan mengunci Operator dari data yang justru ia pertanggungjawabkan. Bunyi BR-RO-07 diperjelas; dikunci `[RO-OUTPUT-19]` (reviewer read-only) dan `[RO-OUTPUT-20]` (Operator boleh) | 2026-09-08 |
| 36 | Tabel *Events yang Dikonsumsi* di `03_notification.md` menyebut penerima yang berbeda dari BR-IAM-15 dan BR-MON-10 untuk tiga event | **Katalognya yang tidak lengkap, bukan BR-nya yang salah.** `MonevStageOpened` paling telanjang: activity diagram di `02_monev.md` sudah menggambar panah ke **peneliti dan reviewer** sejak awal, sementara tabel notifikasi hanya menyebut peneliti — dua bagian DDD yang sama saling menegasikan. Tiga barisnya diperbaiki jadi dua penerima, dan katalognya sekalian dilengkapi tujuh event yang lahir di M3/M4 tapi tidak pernah didaftarkan (`UserVerificationRejected`, `UserDeactivated`, `FormChanged`, `PhaseOverrideGranted`, `SubmissionDeadlineExtended`, `ExportCompleted`, `NewRegistrationAwaitingVerification`). Prinsip yang dipakai: **penerima adalah pihak yang harus bertindak**, bukan pihak yang kebetulan disebut. Dikunci `[NOT-CHANNEL-05/06]`. Lihat §36.1 | 2026-09-08 |


### 36.1 Katalog event versus aturan penerimanya — jejak keputusan

Tiga baris di tabel *Events yang Dikonsumsi* menyebut satu penerima, sementara
aturan yang mengatur event itu menuntut dua:

| Event | Katalog | BR |
| ----- | ------- | -- |
| `UserRegistered` | User baru (welcome) | Operator pemegang `users.verify` (BR-IAM-15) |
| `MonevStageOpened` | Lead Researcher | Lead Researcher **dan** reviewer siklus itu (BR-MON-10) |
| `MonevEvaluationSubmitted` | LPPM Operator | Lead Researcher (BR-MON-10) |

Yang menentukan bukan mana yang lebih baru, melainkan mana yang punya alasan.
Ketiganya ternyata **bukan pertentangan melainkan kekuranglengkapan**: kedua
penerima sama-sama punya alasan, dan katalognya hanya menyebut satu.

- `UserRegistered` — pendaftar butuh sambutan, Operator butuh tahu ada antrian
  yang harus ia kerjakan. Menghapus salah satunya meninggalkan seseorang
  menunggu tanpa tahu apa yang ditunggu.
- `MonevStageOpened` — peneliti menunggu mengisi, reviewer menunggu menilai.
  Dan di sini DDD sudah menjawab sendiri: activity diagram `02_monev.md`
  menggambar `E --> F` ke peneliti **dan** `E --> J` ke reviewer. Tabel
  notifikasinya yang tertinggal dari diagramnya sendiri.
- `MonevEvaluationSubmitted` — peneliti karena hasilnya langsung terbuka
  untuknya (BR-MON-09), Operator karena dialah yang membuka siklus berikutnya.

Prinsip yang dipakai untuk memutuskan, dan yang sekarang tertulis di katalognya:
**penerima adalah pihak yang harus bertindak atas peristiwa itu**, bukan pihak
yang kebetulan berkepentingan. Itu yang membedakan pemberitahuan dari siaran.

Katalognya sekalian dilengkapi tujuh event yang lahir saat M3 dan M4
diimplementasikan tapi tidak pernah didaftarkan. Ketertinggalan itu sendiri yang
paling perlu dicatat: katalog event adalah satu-satunya tempat di DDD yang
menyatakan **siapa mendengar apa**, dan katalog yang tidak lengkap membuat
pertanyaan "siapa yang tahu kalau X terjadi" tidak bisa dijawab tanpa membaca
kode.

### 29.1 Status akun jalur undangan — jejak keputusan

Tiga sumber, dua jawaban:

| Sumber | Bunyinya |
| ------ | -------- |
| DDD, *Jalur 2 — Invitation Link* | `F --> G[Status: active langsung]` |
| Wireframe `SHARED-01-03` | "Akun Anda langsung aktif setelah mendaftar — tanpa perlu verifikasi NIDN"; tombolnya *Daftar & Masuk*, menuju dashboard |
| Wireframe `ADMIN-08-01` | "Undang peneliti eksternal lewat link registrasi — **akun langsung aktif tanpa verifikasi NIDN**" |
| BDD `[IAM-REG-03]`, `[IAM-REG-08]` | "akun terbentuk berstatus **pending**" |

Yang menyendiri adalah BDD-nya, dan ia menyendiri karena keliru — bukan karena
mengunci keputusan yang belum diambil.

Alasan substantifnya lebih kuat daripada sekadar hitungan suara. Verifikasi di sistem
ini memverifikasi **NIDN**. Mitra eksternal tidak punya NIDN — tidak ada field-nya di
`SHARED-01-03`, dan BR-IAM-02 baru saja diperjelas bahwa keunikan NIDN hanya berlaku
atas nilai terisi (register #32). Akun undangan berstatus `pending` karena itu adalah
akun yang menunggu pemeriksaan atas data yang tidak ia miliki: antrian
`OPERATOR-08-02` akan berisi baris yang tidak bisa diverifikasi maupun ditolak dengan
jujur oleh Operator mana pun.

Penyaringannya memang sudah terjadi, hanya di tempat lain: Operator menerbitkan token
untuk organisasi tertentu dengan kuota dan masa berlaku tertentu. Itulah momen
kurasinya. Meminta kurasi kedua setelahnya bukan menambah keamanan, cuma menambah
langkah.

Yang lahir dari sini bukan cuma koreksi dua skenario. Menuliskan alasannya memaksa
menyadari bahwa **status awal cuma satu dari empat hal** yang ditentukan jalur
registrasi — role, organisasi, permission, dan status — dan tidak satu pun dari empat
itu pernah dinyatakan sebagai aturan bernomor. Keempatnya sekarang **BR-IAM-12**,
dengan `[IAM-REG-12]` menjaga sisi mandiri dan `[IAM-REG-13/14]` sisi undangan.

`[IAM-REG-14]` khususnya menjaga akibat yang paling mudah lolos: pendaftar undangan
tidak boleh muncul di antrian verifikasi. Implementasi yang cuma mengganti nilai
status awal akan tetap memasukkannya ke antrian kalau antriannya dibangun dari
"semua user yang belum diverifikasi" alih-alih "semua user berstatus `pending`".

### 31.1 Hapus versus nonaktifkan organisasi — jejak keputusan

`ADMIN-08-02` tidak punya tombol Hapus. Yang ada tombol **Nonaktifkan**, dalam keadaan
`disabled`, dengan keterangan: *"Unit dengan user aktif tidak dapat dinonaktifkan —
pindahkan user-nya terlebih dahulu."*

DDD dan BDD sebaliknya hanya mengenal penghapusan (BR-IAM-08, `[IAM-ORG-01..03]`).
Padahal agregat `Organization` di DDD yang sama sudah punya `is_active`, dan CTE
leluhur di dokumen itu sudah menyaring dengan `WHERE o.is_active = true` — mesinnya
ada, aturannya tidak.

Menyatukan keduanya jadi satu operasi memaksa memilih salah satu kerugian:

- **Kalau hanya ada hapus** — fakultas yang pernah berisi orang tidak akan pernah bisa
  dipensiunkan, karena BR-IAM-08 memblokir selama ada UserProfile apa pun. Reorganisasi
  kampus akan menumpuk unit mati yang tetap muncul di OrgTreePicker selamanya.
- **Kalau hanya ada nonaktif** — unit yang salah ketik saat dibuat dan belum pernah
  dipakai tidak bisa dibersihkan, cuma disembunyikan.

Keduanya tetap ada, dengan ambang yang sengaja berbeda: hapus melihat **UserProfile apa
pun**, nonaktif melihat **user aktif**. Perbedaan itu bukan kelalaian — ia justru
inti perkaranya, dan `[IAM-ORG-06]` mengunci celah di antaranya: unit yang seluruh
user-nya sudah nonaktif **boleh dinonaktifkan tapi belum boleh dihapus**.

Dua akibat lain ikut dinyatakan, karena keduanya tidak akan gagal mencolok kalau
terlewat. `[IAM-ORG-07]` menjaga bahwa deaktivasi tidak menghapus apa pun — jebakan
klasik "nonaktifkan" yang diimplementasikan sebagai cascade delete. Dan `[IAM-ORG-08]`
menjaga akibatnya pada access control: unit nonaktif di tengah rantai memutus
penelusuran leluhur, sehingga Form yang dipasang di atasnya berhenti terlihat oleh
prodi di bawahnya. Itu perilaku yang benar — Form seharusnya tidak mengalir lewat unit
yang sudah dipensiunkan — tapi ia hanya benar kalau memang ditulis.

### 35.1 Siapa boleh menyunting luaran — jejak keputusan

Dua pernyataan di `03_research_output.md` yang saling menegasikan, dan keduanya
ditulis dengan yakin:

| Letak | Bunyinya |
| ----- | -------- |
| Tabel *Access Control* | `Operator / Admin` → **Full access** via `outputs.manage` |
| BR-RO-07 | "**Hanya** `submitted_by` dan `co_investigator` yang bisa tambah atau edit output" |

Yang menyesatkan bukan salah satunya melainkan kata **"hanya"**, yang membaca
seolah menutup seluruh dunia padahal maksudnya menutup satu himpunan: anggota
tim yang bukan penanggung jawab. Tabelnya justru lebih lengkap — ia menyebut
empat peran, termasuk Reviewer yang tidak disinggung BR-nya sama sekali.

Godaannya adalah mengikuti preseden yang sudah ada. Register #24 menjadikan
`update-budget` invarian domain persis karena BR-BUD-02/09 menyebut daftar
tertutup, dan `Gate::before` yang melewatinya dinilai melanggar aturan. Pola
kalimatnya identik di sini.

Preseden itu sengaja **tidak** diikuti, karena yang dijaga berbeda:

- **Anggaran adalah komitmen peneliti.** Angkanya mengikat apa yang ia janjikan
  akan dibelanjakan. Operator yang menyuntingnya diam-diam mengubah janji orang
  lain, dan itulah yang ADR 0015 tutup.
- **Luaran adalah laporan fakta.** Ia memasok pelaporan LPPM ke luar, dan
  merapikan datanya — tipe yang salah pilih, DOI yang keliru ketik — memang
  pekerjaan staf. Mengunci Operator dari sana berarti kesalahan data hanya bisa
  diperbaiki dengan meminta peneliti yang kegiatannya sudah selesai.

Dua skenario baru mengunci masing-masing sisi. `[RO-OUTPUT-19]` menjaga bahwa
Reviewer tetap read-only — satu-satunya peran di tabel yang tidak pernah punya
skenario sebelumnya. `[RO-OUTPUT-20]` menjaga bahwa Operator memang boleh, jadi
keputusan ini tidak bisa dibatalkan diam-diam oleh Policy yang ditulis belakangan
dengan membaca kata "hanya" secara harfiah.

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

### 24.1 Super Admin menembus kunci anggaran — jejak keputusan

Muncul saat irisan vertikal Budget diimplementasikan (Task 7–10).

`BudgetLineItemPolicy::update()` menolak penyuntingan anggaran pada APPROVED, REJECTED,
dan WITHDRAWN — BR-BUD-02, dan `[BUD-LOCK-01]` menguncinya. Tapi ability `update-budget`
tidak lolos ke Policy untuk Super Admin: `Gate::before` di `AppServiceProvider`
mengembalikan `true` lebih dulu untuk setiap ability di luar `INVARIANT_ABILITIES`
(`submit`, `resubmit`) sesuai ADR 0015.

Terbukti langsung, bukan dugaan: pada submission APPROVED, `$superAdmin->can('update-budget', $s)`
bernilai `true` sementara `$owner->can(...)` bernilai `false`. Jadi pemilik pengajuan
terkunci, administrator tidak.

`[BUD-LOCK-01]` tetap hijau karena skenarionya memang bercerita tentang peneliti, bukan
Super Admin — kunci ini bocor di jalur yang belum ada skenarionya.

**Keputusan (2026-09-04): `update-budget` masuk `INVARIANT_ABILITIES`.**

Yang dilanggar ternyata **dua** aturan, bukan satu. Selain BR-BUD-02, bypass itu juga
melanggar BR-BUD-09 — aturan itu menyebut daftar tertutup (`submitted_by` dan
`co_investigator`, `member` read-only) dan Super Admin tidak ada di dalamnya.

Kriterianya sama persis dengan `submit` di ADR 0015: bukan soal seberapa tinggi
kewenangan seseorang, melainkan apakah ability itu menyandang invarian domain. Angka
anggaran pada pengajuan yang sudah diputuskan adalah dasar pencairan dana — koreksi
setelahnya menempuh transisi status yang meninggalkan jejak, bukan suntingan senyap.

Konsekuensinya melampaui status terminal: Super Admin tidak lagi menyunting anggaran
pengajuan orang lain pada status mana pun. Itu bunyi BR-BUD-09 yang selama ini tidak
ditegakkan, bukan efek samping yang ditoleransi.

Opsi ability terpisah (`override-budget-lock`) ditolak — menambah mekanisme untuk
kebutuhan yang belum terbukti ada.

### 25.1 Delegasi submit ke `SubmitFormSubmission` yang diwajibkan spec tidak pernah masuk plan

Spec irisan (`docs/design/bdd-coverage/m1-http-slice.md` §3.3) menyebut ini sebagai
satu dari tiga defect, dan §4 **mewajibkan perbaikannya dalam batas tertentu**. Dua
kalimat §4 harus dibaca bersama-sama: "Irisan ini **tidak** menulis ulang
`submitForm()`" *dan* — di paragraf berikutnya — "`submitForm()` diubah seperlunya saja
untuk **mendelegasikan keputusan submit ke Action itu**". Jadi yang dilarang menulis
ulang method-nya; yang diperintahkan mendelegasikan keputusan submit-nya. Mandat
berbatas, bukan larangan.

Mandat itu hilang di tahap perencanaan: tabel File Structure di plan tidak pernah
mencantumkan `UserFormController::submitForm()`, sehingga tidak ada satu pun dari 8 task
yang menyentuhnya. Catatan versi pertama entri ini keliru menyebut §4 "melarang" dan
menyebut tidak ada skenario yang membatasi jalur HTTP submit — keduanya salah:
`[SM-SUBMIT-07]` menuntut `melihat error per field pada "Proposal PDF"`, yang menurut
tabel lapis §2 dibuktikan di HTTP. Koreksi itu dicatat di sini supaya register tidak
mengabadikan alasan yang tidak benar.

Keadaan sekarang: `submitForm()` (sekitar baris 419) masih memvalidasi dan menyetel
status secara inline, dan tidak pernah memanggil Action-nya — sudah diverifikasi nol
referensi ke `SubmitFormSubmission` di controller itu. Akibatnya gerbang BR-SM-01,
BR-SM-02, dan BR-SM-16 yang ditambahkan irisan ini ke Action **tidak ditegakkan di
jalur yang sungguh-sungguh dipakai peneliti**.

Penyambungannya tidak sepele, dan itu alasan teknis kenapa ia tidak dikerjakan sebagai
tambalan di akhir: `submitForm()` berpindah ke `Approved` ketika
`FormPhaseDetail.needs_review` bernilai false, sementara `SubmitFormSubmission` selalu
berpindah ke `Submitted`. Mendelegasikan tanpa memindahkan percabangan itu akan
mengubah perilaku form yang tidak butuh review; memindahkannya berarti Action ikut
menumbuhkan cabang `needs_review` beserta skenario yang menetapkan perilakunya.

**Saat dicatat, belum ditutup.** Menutupnya berarti irisan lanjutan yang
menyambungkan delegasi keputusan submit ke `SubmitFormSubmission` *disertai* skenario
yang menetapkan nasib cabang `needs_review`.

**Ditutup 2026-09-06** — irisan penutupan register
(`docs/design/bdd-coverage/m1-register-closure.md`).
Delegasi mendarat: `submitForm()` kini meneruskan keputusannya ke `SubmitFormSubmission`.

Catatan koreksi, karena register tidak boleh mengabadikan alasan yang tidak benar:
**alasan teknis yang tercatat di entri ini keliru.** Yang disebut di atas sebagai
percabangan yang harus ikut dipindahkan — `needs_review` yang menyetujui langsung —
tidak pernah bekerja. `SubmissionStatus::config()` tidak mengizinkan `Draft → Approved`,
jadi cabang itu selalu melempar `TransitionNotFound` dan membatalkan seluruh submit-nya.
Nol test dan nol seeder menyentuhnya. Yang selama ini menahan delegasi bukan perilaku
yang harus dilestarikan, melainkan cacat yang tak seorang pun pernah lewati.

Cabangnya dicabut lewat [ADR 0016](../../adr/0016-submit-never-approves.md); dikunci
`[SM-SUBMIT-18]`.

### 26.1 `guardRequiredDocuments()` mengabaikan `FormField::isRequiredFor()`

Gerbang BR-SM-02 yang ditambahkan irisan ini menyaring field wajib dengan
`where('is_required', true)` mentah, mengabaikan
`FormField::isRequiredFor(FormSubmission $submission)`
(`app/Models/FormField.php:63`), yang sudah mengenkode aturan pengikatan temporal
BR-FE-11. Akibatnya, field `file` wajib yang ditambahkan ke Form yang sedang hidup
setelah sebuah draft ada akan selamanya memblokir draft itu untuk disubmit —
bertentangan dengan `[FE-TEMPORAL-02]`.

Tidak diperbaiki di sini karena sudah diverifikasi `UserFormController::submitForm()`
versi legacy menyaring dengan cara identik (`is_required` mentah di baris 456,
`fieldType->name === 'file'` di baris 478) — jadi Action ini untuk sekarang justru
**mencerminkan** jalur HTTP; memperbaiki hanya salah satunya akan membuat keduanya
menyimpang. `isRequiredFor()` sudah punya unit test tapi nol pemanggil produksi.
`FE-TEMPORAL-01..09` semuanya masih tercatat di `pending.txt`.

**Saat dicatat, belum ditutup.** Menutupnya berarti irisan BR-FE-11 yang memperbaiki
kedua jalur sekaligus.

**Ditutup 2026-09-06** — irisan penutupan register
(`docs/design/bdd-coverage/m1-register-closure.md`).

`isRequiredFor()` akhirnya punya pemanggil produksi. Kedua jalur — submit dan resubmit —
memakai `RequiredFieldGuard::assertComplete()` yang menyaring dengan `isRequiredFor()`,
bukan `is_required` mentah. Kekhawatiran "memperbaiki hanya salah satunya akan membuat
keduanya menyimpang" terjawab dengan memperbaiki keduanya sekaligus lewat satu guard.

Guard-nya juga tidak lagi terbatas pada field bertipe `file`: **setiap** field wajib
diperiksa. `FE-TEMPORAL-01..09` keluar dari `pending.txt`.

### 27.1 BR-SM-02 tidak ditegakkan pada jalur resubmit

`ResubmitFormSubmission` (ditambahkan irisan ini) tidak memanggil
`guardRequiredDocuments()`, sehingga aturan proposal wajib tidak ditegakkan pada
transisi NEEDS_REVISION → RESUBMITTED.

Dinilai bukan celah untuk irisan ini: tabel cakupan §5 spec membatasi baris revisi
hanya pada BR-SM-04 dan BR-SM-03, tidak satu pun dari 27 skenario membatasi dokumen
pada resubmit, dan submission NEEDS_REVISION sudah pasti pernah lolos gerbang dokumen
itu saat masuk pertama kali. Dicatat karena peneliti yang mengosongkan response field
proposalnya sebelum resubmit bisa melewati BR-SM-02 lewat jalur ini.

**Saat dicatat, belum ditutup.** Menutupnya berarti skenario yang memutuskan apakah
resubmit perlu mengecek ulang dokumen.

**Ditutup 2026-09-06** — irisan penutupan register
(`docs/design/bdd-coverage/m1-register-closure.md`).

**Diputuskan: resubmit mengecek ulang.** `ResubmitFormSubmission` memanggil
`RequiredFieldGuard::assertComplete()` yang sama dengan jalur submit, memakai **jangkar
temporal yang sama** — `$submission->created_at` yang asli, bukan waktu revisi.

Jangkarnya yang membuat keputusan ini aman: revisi tidak menarik field wajib baru ke
dalam pengajuan yang sudah berjalan, jadi mengecek ulang tidak berarti memindahkan
gawang. Celah yang dicatat di atas tertutup — peneliti yang mengosongkan response
proposalnya sebelum resubmit tidak lagi lolos. Dikunci `[SM-SUBMIT-19]`.

### 28.1 Draft baru tidak bisa dimulai setelah pengajuan Form itu berakhir terminal

`UserFormController` mencari submission wizard dengan
`FormSubmission::firstOrCreate(['form_id', 'submitted_by'])` (baris 372 pada
`saveDraft()`, dan bentuk yang sama pada `submitForm()`) — **satu baris per (Form,
peneliti), tanpa memperhitungkan periode**. Gerbang BR-SM-05 yang ditambahkan Task 3
tepat di bawahnya (`abort_unless($user->can('update', $submission), 403)`) karena itu
menolak dengan 403 setiap kali baris tunggal itu berada di status terminal.

Akibatnya peneliti yang pengajuannya untuk sebuah Form pernah REJECTED tidak akan pernah
bisa memulai draft baru untuk Form yang sama lewat wizard. Itu bertentangan dengan
`[SM-SUBMIT-05]` — yang dibuktikan di lapis Action pada irisan yang sama — yang justru
menetapkan bahwa mengajukan ulang ke skema yang sama setelah ditolak **boleh**. Dua
paruh irisan ini saling bertentangan di lapis HTTP.

Gerbangnya sengaja **tidak** dicabut. Perilaku sebelumnya lebih buruk: `saveDraft()`
menghapus seluruh `formFieldResponses` milik submission terminal itu lalu menulis ulang
isinya — menyunting diam-diam record yang seharusnya beku. Gagal menutup lebih aman
daripada gagal membuka.

Arah perbaikannya bukan di gerbang melainkan di pencariannya: `firstOrCreate` harus
ter-scope ke submission yang belum terminal (dan, mengikuti ADR 0005 serta predikat
index BR-SM-01, kemungkinan besar juga ke `submission_period_id`), sehingga status
terminal melahirkan baris baru alih-alih membentur baris lama.

**Saat dicatat, belum ditutup.** Menutupnya berarti skenario yang menetapkan apa yang
dilihat peneliti saat membuka wizard sebuah Form yang pengajuan sebelumnya sudah
berakhir.

**Ditutup 2026-09-06** — irisan penutupan register
(`docs/design/bdd-coverage/m1-register-closure.md`).

Diperbaiki persis di arah yang diusulkan entri ini: **di pencariannya, bukan di
gerbangnya.** Tidak satu pun gerbang dicabut.

`firstOrCreate` diganti `ResolveWizardSubmission` — pencarian murni yang mencocokkan
`(form_id, submitted_by, submission_period_id)` dengan status `DRAFT` **atau**
`NEEDS_REVISION`. Ikutnya `NEEDS_REVISION` bukan kelonggaran melainkan keharusan: kalau
resolver hanya mencocokkan `DRAFT`, pengajuan yang sedang direvisi jadi tak terjangkau
`save-draft` dan alur revisi ikut mati. Dua status itu persis yang diizinkan
`FormSubmissionPolicy::update()`, jadi resolver dan Policy bicara hal yang sama.

Baris terminal tidak pernah cocok, sehingga `StartSubmission` melahirkan baris baru
alih-alih membentur baris lama — pertentangan dengan `[SM-SUBMIT-05]` hilang. Resolver
yang kosong pada `save-draft`/`submit` menjawab `409`, dan klien memulihkannya dengan
`POST /submissions`. Dikunci `[SM-WIZARD-12]`.
