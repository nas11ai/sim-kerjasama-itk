# BDD — Skenario Bisnis SIMPAS v2

Spesifikasi perilaku sistem dalam format Gherkin. Berdampingan dengan `../ddd/`
(aturan domain) dan `../ux/` (bentuk antarmuka).

**Rancangan lengkap dan alasannya:** `docs/design/bdd-gherkin-baseline.md`.

## Aturan singkat

- Keyword Gherkin **English**, prosa **Bahasa Indonesia**, istilah domain mengikuti `../ddd/02_ubiquitous_language.md`.
- Skenario **deklaratif** — bicara aturan bisnis, bukan klik. Frame wireframe ikut sebagai tag, tidak sebagai langkah.
- Setiap skenario berjudul `[AREA-TOPIK-NN] Kalimat penjelas`. ID **permanen**: skenario yang dihapus tidak melepaskan nomornya untuk dipakai ulang.

## Tag wajib

| Tag | Kardinalitas |
| --- | ------------ |
| `@BR-XX-NN` | ≥ 1, dan BR-nya harus benar-benar terdefinisi sebagai baris tabel di `../ddd/` |
| `@M0`–`@M5` | tepat 1 |
| `@happy` / `@boundary` / `@denial` | tepat 1. Kelas menggambarkan **hasil aksi aktor**, bukan sikap aturannya: `@happy` aksi berhasil, `@denial` aksi ditolak, `@boundary` kasus di tepi aturan (dipakai saat justru batasnya yang jadi pokok skenario, terlepas dari hasilnya). Laporan coverage memakai ini untuk menunjukkan BR mana yang belum punya jalur gagal. |
| `@<FRAME-ID>` | 0..n |

## Kosakata langkah

Frasa di bawah sudah terbentuk sendiri selama Wave 1a dan M1, lalu **dibakukan** — pakai
ini apa adanya, jangan bikin varian. Tanpa daftar ini, "Given waktu sekarang", "Given saat
ini", dan "Given tanggal hari ini" akan hidup berdampingan dan masing-masing menuntut
fixture-nya sendiri.

Tabel pertama adalah kosakata Wave 1a; tabel kedua yang tumbuh dari sepuluh berkas M1.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given waktu sekarang "<datetime>"` | Menetapkan jam sistem |
| `Given submission "<id>" berstatus <STATUS>` | Menyiapkan submission pada status tertentu |
| `Given submission "<id>" milik "<orang>"` | Menetapkan `submitted_by` |
| `Given "<orang>" berorganisasi "<org>" dan punya permission "<perm>"` | Menyiapkan aktor beserta akses |
| `Given skema "<nama>" dengan <atribut>` | Menyiapkan skema beserta rule-nya |
| `When "<orang>" membuka fase "<fase>"` | Aksi membuka satu FormPhaseDetail |
| `When "<orang>" membuka daftar Form` | Aksi melihat Form yang bisa diakses |
| `When Operator <aksi> "<id>"` | Aksi operator atas sebuah submission |
| `Then akses ditolak dengan <kode>` | Penolakan berkode HTTP |
| `Then penyimpanan ditolak dengan <kode>` | Penolakan saat menyimpan |
| `Then status "<id>" menjadi <STATUS>` | Perubahan status yang teramati |
| `Then <aktor> menerima notifikasi <jenis>` | Efek notifikasi |

Kosakata yang tumbuh selama M1 (Submission & Budget). Bentuk `Given submission` di bawah
adalah versi lengkap dari tiga baris terpisah di tabel pertama — pakai yang selengkap
yang skenarionya butuh, jangan menambah varian di antaranya.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given submission "<id>" milik "<orang>" berskema "<skema>" berstatus <STATUS>` | Menyiapkan submission lengkap dengan pemilik, skema, dan status |
| `Given submission "<id>" milik "<orang>" tanpa skema berstatus <STATUS>` | Padanannya untuk Form tanpa scheme_selector |
| `Given "<orang>" terdaftar sebagai <peran> di "<id>"` | Menyiapkan anggota penelitian beserta perannya |
| `Given "<orang>" ditugaskan sebagai reviewer "<id>"` | Menyiapkan penugasan reviewer |
| `When "<orang>" memulai pengajuan baru pada "<form>"` | Aksi membuat draft di langkah 1 |
| `When "<orang>" submit "<id>"` | Aksi submit oleh researcher — bedakan dari `When Operator <aksi>` |
| `When "<orang>" resubmit "<id>"` | Aksi resubmit dari NEEDS_REVISION |
| `When "<orang>" membuka detail "<id>"` | Aksi membuka satu submission |
| `When "<orang>" menyunting isi "<id>"` | Aksi mengubah isi pengajuan |
| `When "<orang>" menyunting line item "<id>"` | Aksi mengubah anggaran yang sudah ada |
| `When "<orang>" menambahkan line item bertotal <N> ke "<id>"` | Aksi menambah anggaran |
| `When "<orang>" menambahkan target luaran bertipe "<tipe>" ke "<id>"` | Aksi menambah target luaran |
| `When "<orang>" mengubah target luaran "<id>"` | Aksi mengubah target luaran |
| `Then detail terbuka` | Akses diberikan |
| `Then penyimpanan berhasil` | Penyimpanan diterima |
| `Then penyuntingan berhasil` | Penyuntingan diterima |
| `Then penyuntingan ditolak dengan <kode>` | Penolakan saat menyunting |
| `Then status "<id>" tetap <STATUS>` | Status yang sengaja **tidak** berubah — pasangan negatif dari `Then status "<id>" menjadi <STATUS>` |
| `Then perubahan tersimpan` | Perubahan diterima |
| `Then submit ditolak karena <alasan>` | Penolakan submit beserta alasan domainnya |
| `Then penambahan ditolak karena <alasan>` | Penolakan penambahan beserta alasan domainnya |
| `Then penyimpanan ditolak karena <alasan>` | Penolakan penyimpanan beserta alasan domainnya |
| `Then perubahan tercatat di jejak audit dengan nilai lama dan nilai baru` | Efek audit trail |
| `Then "<id>" ditandai diarsipkan` | Hasil computed `isArchived()` |

Tabel ketiga adalah hasil **tinjauan Fase B**: frasa yang terbukti dipakai tiga kali
atau lebih di seluruh korpus `.feature` tapi belum pernah dibakukan. Dimasukkan karena
sudah terbukti, bukan karena diperkirakan.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given submission "<id>" dibuat pada "<datetime>"` | Menetapkan jangkar Temporal Field Binding |
| `Given FormField "<nama>" dibuat pada "<datetime>" dengan is_required true` | Field wajib beserta saat berlakunya |
| `Given FormField "<nama>" dibuat pada "<datetime>" dengan is_required false` | Padanannya untuk field opsional |
| `Given Form "<form>" tidak punya field bertipe scheme_selector` | Form yang memang tanpa skema |
| `Given periode berjalan aktif dan belum force closed` | Periode masih terbuka |
| `Given periode berjalan berstatus is_force_closed` | Periode ditutup paksa Operator |
| `Given "<orang>" berperan Super Admin dan bukan submitted_by "<id>"` | Menyiapkan aktor berwenang luas yang bukan pemilik pengajuan — uji bypass `Gate::before` terhadap invarian domain |
| `When admin menonaktifkan skema "<skema>"` | Deaktivasi skema |
| `When lead researcher menyimpan "<id>" dengan last_updated_at "<datetime>"` | Penyimpanan yang menguji optimistic lock |
| `When Lead Researcher submit "<id>" tanpa mengisi "<field>"` | Submit yang sengaja belum mengisi satu field — uji negatif Temporal Field Binding |
| `When Operator membuat override untuk "<id>" pada fase "<fase>" yang berlaku sampai "<datetime>"` | Operator memberi perpanjangan akses satu fase |
| `Then submit berhasil` | Submit diterima — pasangan positif `Then submit ditolak karena <alasan>` |
| `Then submit ditolak dengan error validasi pada field "<nama>"` | Penolakan submit yang menyebut field-nya |
| `Then "<id>" muncul di daftar` | Kehadiran baris di daftar |
| `Then "<id>" tidak muncul di daftar` | Ketidakhadirannya — pasangan negatif |
| `Given fase pengajuan "<id>" tidak membutuhkan review` | Menetapkan `needs_review` false pada FormPhaseDetail submission |

Tabel keempat tumbuh dari M2 (Review). Seluruhnya kosakata BC Review — penugasan,
evaluasi, ulir diskusi, dan keterlihatan skor — plus jendela kerja per tahap BR-FE-17.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given skema "<skema>" dengan min_reviewer_count <n>` | Ambang penugasan yang berasal dari `scheme.rules`, bukan konstanta |
| `Given skema "<skema>" dengan max_reviewer_workload <n>` | Batas beban reviewer |
| `Given "<orang>" dan "<orang>" ditugaskan sebagai reviewer "<id>"` | Dua reviewer sekaligus — bentuk jamak dari baris di tabel kedua |
| `Given "<orang>" berstatus reviewer_internal` | Tipe reviewer yang boleh melihat skor rekannya |
| `Given "<orang>" berstatus reviewer_external` | Padanannya yang tidak pernah boleh |
| `Given "<orang>" berstatus reviewer dengan masa tugas "<tanggal>" sampai "<tanggal>"` | Rentang `start_date`–`end_date` reviewer |
| `Given "<orang>" sedang memegang <n> penugasan review aktif` | Beban berjalan, untuk menguji BR-REV-04 |
| `Given evaluasi "<orang>" atas "<id>" berstatus <draft\|submitted\|completed>` | Keadaan `ReviewFormResponse` |
| `Given evaluation_status "<orang>" pada "<id>" adalah <pending\|completed\|not_required>` | Keadaan penugasan, berbeda dari status jawabannya |
| `Given ada ReviewSummary berstatus open pada "<id>"` | Ulir diskusi yang masih terbuka |
| `Given tidak ada ReviewSummary berstatus open pada "<id>"` | Pasangan negatifnya — pemicu APPROVED |
| `Given detail <fase> berbatas "<label>" pada "<tanggal>"` | Jendela kerja satu `FormPhaseDetail` |
| `When Operator menugaskan "<orang>" sebagai reviewer "<id>"` | Aksi penugasan |
| `When Operator mengganti reviewer "<orang>" dengan "<orang>" pada "<id>"` | Aksi penggantian |
| `When Operator menolak "<id>" dengan alasan tertulis` | Penolakan manual — satu-satunya jalan ke REJECTED |
| `When "<orang>" submit evaluasinya atas "<id>"` | Reviewer menyelesaikan evaluasi |
| `When "<orang>" menyunting evaluasinya atas "<id>"` | Uji penguncian BR-FE-05 |
| `When "<orang>" membuat ReviewSummary pada "<id>"` | Membuka ulir diskusi |
| `When "<orang>" menandai ReviewSummary itu resolved` | Menutup ulir |
| `When "<orang>" membuka hasil review "<id>"` | Aksi melihat skor |
| `When sistem mengevaluasi hasil review "<id>"` | Pemicu transisi otomatis BR-REV-05/06 — bukan aksi manusia |
| `Then penugasan berhasil` | Penugasan diterima |
| `Then penugasan ditolak karena <alasan>` | Penolakan penugasan beserta alasan domainnya |
| `Then penugasan "<orang>" pada "<id>" berstatus replaced` | Jejak penggantian yang tidak dihapus |
| `Then evaluation_status "<orang>" pada "<id>" menjadi completed` | Perubahan status penugasan |
| `Then ReviewSummary itu berstatus <open\|resolved>` | Keadaan ulir setelah aksi |
| `Then "<orang>" melihat skor "<orang>"` | Keterlihatan antar reviewer |
| `Then "<orang>" tidak melihat skor "<orang>"` | Pasangan negatifnya |
| `Then "<orang>" melihat skor reviewer` | Keterlihatan untuk researcher |
| `Then "<orang>" tidak melihat skor reviewer` | Pasangan negatifnya |
| `Then status "<id>" tidak menjadi <STATUS>` | Menyatakan ketiadaan transisi — dipakai `REV-OUTCOME-08` |
| `Then penambahan berhasil` | Penambahan diterima |

Tabel kelima tumbuh dari M3 (Identity & Access) — registrasi, token undangan,
verifikasi NIDN, permission, organisasi, dan pemberitahuan perubahan keadaan akun.
Perhatikan tiga sumbu keadaan yang sengaja dibedakan: status verifikasi
(`pending`/`aktif`/`rejected`), keaktifan akun (`aktif`/`tidak aktif`), dan
kelengkapan profil. Ketiganya menggerbang aksi yang berbeda.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given "<orang>" berstatus <pending\|aktif\|tidak aktif\|rejected>` | Keadaan akun — `pending`/`aktif`/`rejected` sumbu verifikasi, `tidak aktif` sumbu keaktifan |
| `Given "<orang>" sudah terverifikasi` | Akun yang NIDN-nya sudah diverifikasi |
| `Given profil "<orang>" <sudah\|belum> lengkap` | Kelengkapan profil — gerbang yang berbeda dari status akun |
| `Given profil "<orang>" terisi nomor telepon tapi tanpa bidang keahlian` | Profil separuh terisi — menguji `isComplete()` yang cuma memeriksa satu field |
| `Given "<orang>" terdaftar dengan NIDN "<nidn>"` | Menyiapkan NIDN yang sudah terpakai |
| `Given "<orang>" memilih organisasi "<org>" di OrgTreePicker` | Organisasi yang dipilih pendaftar pada jalur mandiri |
| `Given token undangan sah milik organisasi "<org>"` | Token yang masih berlaku |
| `Given token undangan itu memberi permission "<perm>"` | Bekal permission yang dibawa token (BR-IAM-12) |
| `Given token undangan itu sudah dicabut Operator` | Token yang dicabut sebelum kuotanya habis |
| `Given token undangan dengan max_uses <n> dan used_count <n>` | Kuota token |
| `Given token undangan yang expires_at "<datetime>"` | Token yang bisa kedaluwarsa |
| `Given organisasi "<org>" punya UserProfile di dalamnya` | Organisasi yang tidak boleh **dihapus** |
| `Given organisasi "<org>" tanpa UserProfile di seluruh subtree-nya` | Padanannya yang boleh dihapus — perhatikan kata subtree |
| `Given organisasi "<org>" punya user aktif di dalamnya` | Organisasi yang tidak boleh **dinonaktifkan** — ambang yang berbeda dari dua baris di atas |
| `Given organisasi "<org>" tidak punya user aktif langsung` | Pasangannya untuk menguji penelusuran keturunan |
| `Given organisasi "<org>" punya UserProfile yang seluruh user-nya tidak aktif` | Unit yang boleh dinonaktifkan tapi belum boleh dihapus |
| `Given organisasi "<org>" berstatus nonaktif` | `is_active = false` — memutus rantai leluhur dan membatalkan token |
| `When "<orang>" mendaftar mandiri dengan email "<email>"` | Jalur registrasi mandiri |
| `When "<orang>" mendaftar lewat token itu` | Jalur registrasi undangan |
| `When "<orang>" melengkapi profilnya` | Aksi yang user lakukan sendiri — uji negatif BR-IAM-15 |
| `When "<orang>" login` | Aksi masuk |
| `When Operator memverifikasi NIDN "<orang>"` | Aksi verifikasi |
| `When Operator menolak verifikasi NIDN "<orang>" <dengan alasan tertulis\|tanpa alasan>` | Aksi penolakan verifikasi, beserta uji negatif alasannya |
| `When Operator menonaktifkan "<orang>"` | Deaktivasi user |
| `When Operator mencabut direct permission "<perm>" dari "<orang>"` | Pencabutan permission langsung — bukan pencabutan role |
| `When Operator menunjuk "<orang>" sebagai reviewer <internal\|eksternal>` | Penunjukan reviewer — bukan pembuatan akun |
| `When Operator menunjuk "<orang>" sebagai reviewer <internal\|eksternal> dengan masa tugas "<tanggal>" sampai "<tanggal>"` | Bentuk lengkapnya, saat masa tugas yang jadi pokok |
| `When admin menghapus organisasi "<org>"` | Penghapusan organisasi |
| `When admin menonaktifkan organisasi "<org>"` | Deaktivasi organisasi — pasangannya yang berambang berbeda |
| `Then akun "<orang>" terbentuk berstatus <pending\|aktif>` | Akun baru; `pending` jalur mandiri, `aktif` jalur undangan (BR-IAM-12) |
| `Then akun "<orang>" berstatus <pending\|aktif\|rejected>` | Status verifikasi setelah keputusan Operator |
| `Then keduanya terdaftar tanpa NIDN` | Keunikan NIDN yang hanya berlaku atas nilai terisi |
| `Then "<orang>" berperan "<role>"` | Role Spatie yang melekat |
| `Then "<orang>" <muncul\|tidak muncul> di antrian verifikasi NIDN` | Isi antrian verifikasi |
| `Then pendaftaran ditolak karena <alasan>` | Penolakan registrasi beserta alasan domainnya |
| `Then penolakan ditolak karena <alasan>` | Penolakan atas aksi menolak — mis. alasan yang wajib tapi kosong |
| `Then login ditolak karena <alasan>` | Penolakan login |
| `Then penghapusan berhasil` | Penghapusan diterima |
| `Then penghapusan ditolak karena <alasan>` | Penolakan penghapusan |
| `Then deaktivasi berhasil` | Deaktivasi diterima |
| `Then deaktivasi ditolak karena <alasan>` | Penolakan deaktivasi, user maupun organisasi |
| `Then UserProfile di organisasi itu tetap utuh` | Bukti deaktivasi organisasi tidak menghapus apa pun |
| `Then penunjukan ditolak karena <alasan>` | Penolakan penunjukan reviewer |
| `Then record reviewer "<orang>" tetap satu` | Idempotensi penunjukan (BR-IAM-11) |
| `Then "<orang>" berstatus reviewer dengan masa tugas "<tanggal>" sampai "<tanggal>"` | Masa tugas hasil penunjukan, yang kemudian ditegakkan BR-REV-09 |
| `Then "<orang>" punya permission "<perm>"` | Permission efektif |
| `Then "<orang>" tidak punya permission "<perm>"` | Pasangan negatifnya |
| `Then used_count token itu menjadi <n>` | Efek pemakaian token |
| `Then "<orang>" menerima notifikasi berisi alasan penolakan` | Alasan yang ikut terkirim, bukan sekadar fakta penolakan (BR-IAM-13) |
| `Then Operator menerima notifikasi in-app` | Sisi Operator dari BR-IAM-15 — pendaftar baru butuh diverifikasi |

Tabel keenam tumbuh dari M4 (Monev & Research Output). Perhatikan `siklus` sebagai
pembeda: penugasan reviewer monev selalu per siklus, tidak pernah diwarisi.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given detail "<detail>" berstatus needs_review <true\|false>` | Menentukan apakah evaluasi ikut jadi syarat `canProceed()` |
| `Given detail "<detail>" berbatas "<label>" pada "<tanggal>"` | Deadline satu FormPhaseDetail |
| `Given laporan "<detail>" pada "<id>" <sudah\|belum> disubmit` | Keadaan child FormSubmission monev |
| `Given tidak ada FormSubmissionOverride berlaku untuk "<id>"` | Menegaskan ketiadaan override |
| `Given "<orang>" ditugaskan sebagai reviewer siklus "<detail>" pada "<id>"` | Penugasan per siklus monev |
| `Given "<orang>" ditugaskan sebagai reviewer "<id>" dengan masa tugas berakhir "<tanggal>"` | Reviewer awal, untuk menguji daftar saran BR-MON-07 |
| `Given submission "<id>" bertipe <Research\|CommunityService>` | SubmissionType — penentu tipe luaran yang sah |
| `Given "<id>" punya luaran bertipe "<tipe>"` | Luaran yang sudah ada |
| `When "<orang>" membuat laporan "<detail>" pada "<id>"` | Membuat child submission monev |
| `When "<orang>" mengisi laporan "<detail>" pada "<id>"` | Menyimpan isi laporan monev |
| `When Operator menugaskan "<orang>" sebagai reviewer siklus "<detail>" pada "<id>"` | Penugasan reviewer monev |
| `When Operator membuka daftar saran reviewer siklus "<detail>" pada "<id>"` | Melihat saran, bukan pewarisan |
| `When "<orang>" menambahkan luaran bertipe "<tipe>" ke "<id>"` | Menambah Research Output |
| `When "<orang>" menyunting luaran "<id>"` | Menyunting Research Output |
| `When "<orang>" membuka daftar luaran "<id>"` | Membaca luaran — tidak butuh override |
| `When "<orang>" membuka hasil evaluasi siklus "<detail>" pada "<id>"` | Keterlihatan hasil monev BR-MON-09 |
| `Then "<orang>" <melihat\|tidak melihat> skor dan catatan monev` | Pasangan positif-negatif keterlihatan monev |
| `Then "<orang>" <muncul\|tidak muncul> di daftar saran reviewer` | Isi daftar saran |
| `Then luaran bertipe "<tipe>" masih terbaca` | Luaran yang bertahan sebagai data historis |
| `Then SubmissionReviewer baru terbentuk untuk siklus itu` | Bukti BR-MON-06 — bukan reuse record |
| `Then pembatalan ditolak karena <alasan>` | Penolakan pembatalan penarikan |

Tabel ketujuh tumbuh dari M5 (Reporting, Notification & Config) — lapisan yang
menyentuh seluruh sistem tapi tidak punya alur bisnisnya sendiri.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given hasil ekspor berjumlah <n> baris` | Ambang 100 baris BR-RPT-02 |
| `Given ada entri audit untuk "<id>"` | Menyiapkan jejak audit |
| `Given "<orang>" punya notifikasi in-app` | Notifikasi yang sudah ada |
| `Given event <Event> dideklarasikan hanya in-app` | Deklarasi channel yang bisa diubah |
| `Given event <Event> belum punya deklarasi channel` | Event tanpa deklarasi — harus ditolak |
| `Given kanal email sedang tidak bisa dihubungi` | Menguji fire-and-forget BR-NOT-03 |
| `Given <Entity> "<nama>" masih dipakai <relasi> aktif` | Entity acuan yang tidak boleh dihapus |
| `When Operator meminta ekspor data submission` | Aksi ekspor |
| `When "<orang>" meminta ekspor data submission miliknya` | Ekspor ter-scope Researcher |
| `When Operator membuka audit log` | Melihat jejak audit |
| `When event <Event> dipublish untuk "<orang>"` | Memicu notifikasi |
| `When admin mengubah channel <Event> menjadi <daftar>` | Mengubah konfigurasi channel |
| `When admin menghapus <Entity> "<nama>"` | Penghapusan entity acuan |
| `When admin mengganti nama <Entity> itu menjadi "<nama>"` | Penggantian nama entity acuan |
| `Then ekspor selesai langsung` | Ekspor sinkron |
| `Then ekspor dijadwalkan sebagai background job` | Ekspor asinkron |
| `Then hasil ekspor <memuat\|tidak memuat> "<id>"` | Isi hasil ekspor — pasangan positif-negatif |
| `Then entri audit baru tercatat untuk "<id>"` | Efek audit |
| `Then penghapusan ditolak karena audit log bersifat append-only` | Penjagaan BR-RPT-04 |
| `Then "<orang>" <menerima\|tidak menerima> notifikasi <in-app\|email>` | Channel yang benar-benar dipakai |
| `Then publikasi ditolak karena <alasan>` | Penolakan publish event |

**Aturannya:** kalau langkah yang kamu butuhkan tidak ada di sini, boleh menambah — tapi
tambahkan barisnya ke tabel ini dalam PR yang sama. Frasa yang dipakai sekali dan tidak
pernah lagi adalah tanda skenarionya terlalu spesifik, atau kosakatanya belum ketemu.

Nilai di dalam tanda kutip adalah parameter; sisanya harus persis. Istilah domain
mengikuti [ubiquitous language](../ddd/02_ubiquitous_language.md) — `NEEDS_REVISION`,
bukan "perlu revisi".

## Menambah skenario

1. Tulis skenario di file `.feature` yang temanya cocok. Kalau file melewati ~25 skenario, temanya dua — pecah.
2. Tambahkan ID-nya ke `pending.txt` dan naikkan `# max:` di sana.
3. Kalau ini skenario pertama untuk sebuah BR, hapus BR itu dari `uncovered.txt` dan turunkan `# max:`-nya. Assertion 9 menolak BR yang sudah punya skenario tapi masih tercatat di sana.
4. Jalankan `composer run quality`.

Saat test Pest-nya ditulis, sebutkan ID di deskripsi test:

```php
it('[FE-TEMPORAL-01] field yang lahir setelah submission tidak diwajibkan', function () {
```

lalu hapus ID itu dari `pending.txt` dan turunkan plafonnya.

## Frame yang sengaja tidak punya skenario

Setelah M5 mendarat, **55 dari 65 frame** terikat ke minimal satu skenario. Sepuluh
sisanya sengaja dibiarkan, dan alasannya dicatat di sini supaya "belum ditulis" tidak
pernah tertukar dengan "memang tidak perlu".

| Frame | Alasan |
| ----- | ------ |
| `SHARED-01-07` Profil Saya | Tidak ada BR; penyuntingan profil biasa |
| `SHARED-01-08` Lupa Password | Mekanisme bawaan Laravel, tidak diatur BR mana pun |
| `SHARED-01-09` Reset Password | Sama seperti di atas |
| `SHARED-02-03` Dashboard Operator | Angkanya turunan submission yang sudah dijaga M1–M2; tidak ada aturan khusus dashboard |
| `SHARED-02-04` Dashboard Admin | Sama seperti di atas |
| `OPERATOR-07-01` Dashboard Statistik | BR-RPT-01 dan BR-RPT-06 keduanya non-behavioral |
| `OPERATOR-08-01` Daftar User | Layar daftar; aturannya ada di detail (`OPERATOR-08-03`) |
| `ADMIN-08-05` Daftar Form | Layar daftar, sejajar `OPERATOR-08-01` |
| `ADMIN-08-07` Edit Form — Access Control | Batasannya schema-level (`permission` + `organization_id` NOT NULL); perilakunya diuji dari sisi konsumsi lewat `FE-ACCESS-01..06` |
| `ADMIN-08-08` Edit Form — Phases | BR-FE-07 non-behavioral; jendela kerjanya diuji `FE-WINDOW-04` di `ADMIN-08-09` |

**Aturannya:** kalau nanti muncul aturan bisnis untuk salah satu layar ini, aturan itu
ditambahkan ke `../ddd/` lebih dulu sebagai baris tabel bernomor, baru skenarionya —
bukan sebaliknya. Preseden: BR-FE-18 lahir begitu, dari prosa yang sudah ada di
`01_form_engine.md` tapi belum pernah dinomori.

## Daftar berplafon

`pending.txt` dan `uncovered.txt` adalah utang yang terlihat. Mesin menjaga plafonnya;
review PR menjaga arahnya **hanya turun** — pembagian kerja yang sama dengan
`excludePaths` di `phpstan-strict.neon`.
