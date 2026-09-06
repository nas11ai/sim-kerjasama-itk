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
verifikasi NIDN, permission, dan organisasi.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given "<orang>" berstatus <pending\|aktif\|tidak aktif>` | Keadaan akun |
| `Given "<orang>" sudah terverifikasi` | Akun yang NIDN-nya sudah diverifikasi |
| `Given profil "<orang>" <sudah\|belum> lengkap` | Kelengkapan profil — gerbang yang berbeda dari status akun |
| `Given "<orang>" terdaftar dengan NIDN "<nidn>"` | Menyiapkan NIDN yang sudah terpakai |
| `Given token undangan sah milik organisasi "<org>"` | Token yang masih berlaku |
| `Given token undangan dengan max_uses <n> dan used_count <n>` | Kuota token |
| `Given token undangan yang expires_at "<datetime>"` | Token yang bisa kedaluwarsa |
| `Given organisasi "<org>" punya UserProfile di dalamnya` | Organisasi yang tidak boleh dihapus |
| `Given organisasi "<org>" tanpa UserProfile di seluruh subtree-nya` | Padanannya yang boleh dihapus — perhatikan kata subtree |
| `When "<orang>" mendaftar mandiri dengan email "<email>"` | Jalur registrasi mandiri |
| `When "<orang>" mendaftar lewat token itu` | Jalur registrasi undangan |
| `When "<orang>" login` | Aksi masuk |
| `When Operator memverifikasi NIDN "<orang>"` | Aksi verifikasi |
| `When Operator menonaktifkan "<orang>"` | Deaktivasi user |
| `When Operator menunjuk "<orang>" sebagai reviewer <internal\|eksternal>` | Penunjukan reviewer — bukan pembuatan akun |
| `When admin menghapus organisasi "<org>"` | Penghapusan organisasi |
| `Then akun "<orang>" terbentuk berstatus pending` | Akun baru |
| `Then pendaftaran ditolak karena <alasan>` | Penolakan registrasi beserta alasan domainnya |
| `Then login ditolak karena <alasan>` | Penolakan login |
| `Then penghapusan berhasil` | Penghapusan diterima |
| `Then penghapusan ditolak karena <alasan>` | Penolakan penghapusan |
| `Then penunjukan ditolak karena <alasan>` | Penolakan penunjukan reviewer |
| `Then "<orang>" punya permission "<perm>"` | Permission efektif |
| `Then "<orang>" tidak punya permission "<perm>"` | Pasangan negatifnya |
| `Then used_count token itu menjadi <n>` | Efek pemakaian token |

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

## Daftar berplafon

`pending.txt` dan `uncovered.txt` adalah utang yang terlihat. Mesin menjaga plafonnya;
review PR menjaga arahnya **hanya turun** — pembagian kerja yang sama dengan
`excludePaths` di `phpstan-strict.neon`.
