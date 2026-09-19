# Rencana Refactor — Bentuk, Batas, dan Penegakannya

**Versi:** 1.4
**Tanggal:** 2026-09-19
**Status:** Disetujui, belum dikerjakan

Dokumen ini menetapkan **bentuk** kode di repo ini, **batas** yang mengikatnya, dan
**mesin** yang menegakkan batas itu. Ia tidak menambah satu pun fitur dan tidak
menyentuh `docs/requirement-analysis/`.

Versi 1.4 menyelaraskan tabel assertion dengan rencana implementasinya: assertion 5
mengikuti plafon (bukan sisa nol), dan assertion penempatan direktori dicabut karena
redundan dengan assertion 1.

Versi 1.3 menambahkan dua aturan gaya yang bisa diukur: identifier berbahasa Inggris
dan prosa komentar maksimal tiga baris ([§2.6](#26-gaya-penamaan-dan-komentar)).

Versi 1.2 mengoreksi target `mixed` dari ambang 0 menjadi plafon 48 → 12: dua belas di
antaranya melintasi batas yang bukan milik kita, dan memaksakan angka nol di sana hanya
akan ditempuh lewat cast yang justru dilarang AGENTS.md aturan 5.

Versi 1.1 menambahkan lapis kontrak API ([§2.4](#24-kontrak-api-dan-kenapa-ia-dipisah-sejak-sekarang)).
API untuk aplikasi lain direncanakan setelah migrasi data lama, dan bentuk keluarannya
diputuskan sekarang dengan satu alasan: supaya refactor ini tidak dikerjakan dua kali.

Ia ditulis untuk dipakai lintas sesi. Yang membuatnya bertahan bukan dokumen ini
melainkan dua berkas plafon yang ditegakkan test — dokumen bisa tidak dibaca, test
tidak bisa tidak dijalankan. Bagian [§6](#6-cara-memakainya-di-sesi-berikutnya)
menjelaskan cara memakainya tanpa membaca sisanya.

---

## 1. Diagnosis

Seluruh angka di bawah diukur pada 2026-09-19 dengan tokenizer PHP dan pemindaian
blok enam-baris, bukan dengan perkiraan.

### 1.1 Yang ternyata sehat

| Ukuran | p50 | p75 | p90 | p95 | p99 | max |
| ------ | --: | --: | --: | --: | --: | --: |
| LOC per method | 8 | 16 | 30 | 44 | 77 | 303 |
| Cyclomatic complexity | 1 | 2 | 4 | 6 | 10 | 32 |
| Parameter per method | 1 | 1 | 2 | 3 | 4 | 9 |
| `return` per method | 1 | 1 | 2 | 3 | 4 | 6 |

Hanya **9 dari 978** method ber-cc di atas 10, dan hanya **5** yang berparameter lebih
dari empat.

**Konsekuensinya menentukan seluruh rencana ini:** batas ala PHPCS yang lazim
(`cyclomatic ≤ 10`, `method ≤ 50 baris`) kalau dipasang hari ini hanya menangkap
sembilan dan empat puluh dua pelanggaran, lalu diam selamanya. Bloat di repo ini
**tidak hidup di tingkat method.**

### 1.2 Yang tidak sehat

| Tempat | Angka |
| ------ | ----- |
| `app/Http` | 63 berkas, **10.082 baris** — dua kali lipat `app/Actions` |
| `SubmissionViewController` | **1353 baris** (AGENTS.md mencatat ~1000; ia tumbuh) |
| `app/Models` | 54 berkas, 5.050 baris, **123 method logic** |
| `FormSubmission` | 697 baris, 23 relasi, **31 method logic** |
| Komponen Vue | 314 berkas, **48.356 baris**; 35 di antaranya >500 baris |
| Duplikasi | **1670 blok** enam-baris, menyentuh **135 dari 574** berkas |
| PHPStan tier ketat | level 9; naik ke 10 berbiaya **18 error dari 3 penyebab** |
| PHPStan tier default | level 5; pada level 10 berbiaya **1284 error** |
| `mixed` tertulis | **48** kemunculan, 16 di antaranya satu trait |

Dua bukti yang paling telanjang:

```php
// app/Models/SubmissionPeriod.php — model yang menutup periode DAN mengirim surat
public function closeAndNotifyDraftOwners(): void
{
    $this->update(['is_force_closed' => true]);
    Deliver::to($owners, new SubmissionPeriodClosedNotification($this));
}
```

`Pages/EvaluationForms/ShowPage.vue` dan `Pages/Reviewer/EvaluationForm/ShowPage.vue`
berbagi **340 blok enam-baris** — praktis kembar.

### 1.3 Sumbu yang hilang

`app/Actions/` menampung sisi tulis dengan rapi. Tidak ada tempat bagi **sisi baca**,
jadi query menumpuk di controller. Itu akar 1353 baris `SubmissionViewController`, dan
alasan `app/Data` hanya berisi satu berkas sementara controller merakit array mentah.

---

## 2. Bentuk: pola folder

```
app/
  Actions/          sisi TULIS — satu use case, satu Action        [ada]
  Queries/          sisi BACA  — satu pertanyaan, satu Query       [BARU]
  Data/
    Request/        masuk — hasil validasi, masukan Action
    View/           keluar ke Inertia — berbentuk layar
    Contract/       keluar ke API — kontrak dengan konsumen luar
  Policies/         wewenang                                       [ada, 2 berkas]
  Models/           relasi, cast, scope, booted() guard, predikat domain
  Support/
    Guards/         penolakan berbasis aturan
    Gates/          gerbang waktu & akses
  Http/Controllers/ cangkang tipis, tanpa logika
```

### 2.1 Kontrak controller

| Arah | Alur |
| ---- | ---- |
| Tulis | `FormRequest → Data\Request → Action → redirect` |
| Baca (layar) | `Query → Data\View → Inertia::render` |
| Baca (API) | `Query → Data\Contract → response()->json` |

Controller tidak memuat apa pun selain merangkai keempatnya.

### 2.2 Kenapa `Queries/` direktori baru, bukan scope di Model

`phpstan-strict.neon` memakai **daftar-larangan**, bukan daftar-izin. Setiap direktori
baru di `app/` otomatis tunduk level 9 sejak lahir, sementara `app/Models` dikecualikan.
Query di `Queries/` jadi ketat tanpa biaya; query sebagai scope di Model lolos
pemeriksaan.

### 2.3 Penamaan

- Satu `Query` menjawab **satu pertanyaan** dan punya satu method publik. Namanya
  kalimat: `SubmissionDetailForResearcher`, `ReviewerWorkloadPerScheme`.
- Satu `Action` = satu use case. Nama yang mengandung "dan" berarti dua Action.
- Satu `Data` = satu bentuk payload yang benar-benar dikirim ke satu layar.

### 2.4 Kontrak API, dan kenapa ia dipisah sejak sekarang

API untuk aplikasi lain **direncanakan**, bukan diandaikan: ia dibangun setelah aplikasi
ini selesai dan data lama dimigrasikan ke tabel baru. Karena itu bentuk keluarannya
diputuskan sekarang — memutuskannya nanti berarti membongkar setiap endpoint dua kali.

Yang membedakan payload layar dari kontrak API bukan arahnya, melainkan **siapa yang
berhak mengubah bentuknya**:

| | Pemiliknya | Kalau bentuknya berubah |
| --- | --- | --- |
| `Data\View` | layar itu sendiri | wajar — memang harus ikut berubah |
| `Data\Contract` | **konsumennya** | aplikasi pihak lain rusak |

Kalau keduanya tinggal di satu direktori, suatu hari ada yang merapikan DTO demi satu
layar dan diam-diam mematahkan konsumen luar. Pemisahan inilah yang membuat kekeliruan
itu terlihat di review dan bisa ditegakkan test.

**Kaitannya dengan migrasi data lama.** Kalau API memaparkan atribut model, memindahkan
data lama ke tabel baru mematahkan setiap konsumen — bentuk tabel bocor jadi bentuk
kontrak. Kalau ia memaparkan field yang disebut satu per satu di `Data\Contract`,
migrasi itu tidak terlihat dari luar sama sekali. Lapis ini bukan kerapian; ia prasyarat
migrasi yang aman.

**Tiga aturan yang mengikat:**

1. `View` boleh menyusun `Contract`; **`Contract` tidak pernah menyusun `View`**. Kalau
   dibalik, layar jadi pemilik kontrak dan seluruh gunanya hilang.
2. `Contract` **menyebut setiap field secara eksplisit**. Dilarang menyebar atribut model
   — `toArray()`, `only([...])`, atau hasil `get([...])` yang langsung dikirim. Itu
   persis cara kolom database jadi kontrak tanpa ada yang memutuskannya.
3. `Contract` tidak boleh punya properti bertipe Model.

**Keadaan hari ini yang dijawabnya:** ada **22** pemanggilan `response()->json()`, nol
`App\Http\Resources`, dan satu berkas di `app/Data`. Yang paling rapi dari 22 itu pun
menetapkan kontraknya lewat argumen `get()` di tengah controller:

```php
$availableForms = $formPhaseDetail->activeReviewEvaluationForms()
    ->get(['id', 'title', 'description', 'is_required', 'order']);

return response()->json($availableForms);
```

Tidak ada satu tempat pun yang bisa dibaca orang untuk tahu bentuk apa yang dijanjikan
endpoint itu. Dan karena `app/Http` dikecualikan dari tier ketat, tidak ada pemeriksaan
apa pun yang menjaganya.

**Versioning: datar dulu, dengan pemicu yang jelas.** Tidak ada `V1/` hari ini — sampai
versi kedua benar-benar ada, bertingkat cuma spekulasi, dan memindah namespace internal
saat itu murah karena yang dilihat konsumen bentuk kawatnya, bukan namespace-nya. Yang
**tidak** murah diretrofit adalah URL: begitu API dibangun, route-nya membawa segmen
versi sejak hari pertama (`/api/v1/...`). Konsumen yang terlanjur mengikat diri ke URL
tanpa versi tidak bisa dilepaskan belakangan.

---

### 2.5 Garis untuk Model

Satu pertanyaan penentu per method:

| Pertanyaan | Jawab | Tempatnya |
| ---------- | ----- | --------- |
| Mengubah state atau mengirim sesuatu? | ya | `Actions/` |
| Menjawab pertanyaan untuk ditampilkan? | ya | `Queries/` |
| Invarian yang wajib benar di **setiap** jalur Eloquent? | ya | tetap di Model |

Yang tetap tinggal di Model, beserta alasannya:

- **`booted()` guard.** BR-RPT-04 sudah menetapkan presedennya: *"Policy hanya dilewati
  kalau ada yang memanggilnya, sedangkan penjagaan di model ikut menutup tinker,
  seeder, dan penghapusan lewat relasi."*
- **Predikat domain yang DDD sendiri tempatkan di model** — `isAccessibleBy()`
  (BR-SM-07) dan `isArchived()` (BR-SM-13, yang DDD tegaskan *computed*, bukan kolom).
- Relasi, cast, scope sederhana.

Sisanya — 123 method dikurangi golongan di atas — pindah.

**Efek sampingan yang bukan kebetulan:** `app/Models` menyimpan 244 error level 10 dan
dikecualikan dari tier ketat. Setiap method yang pindah langsung tunduk level 10, jadi
pemindahannya sekaligus menggerus utang itu tanpa perlu ukuran terpisah.

---

### 2.6 Gaya penamaan dan komentar

**Identifier berbahasa Inggris.** Variabel, method, kelas, dan properti memakai bahasa
Inggris; prosa komentar dan dokumen tetap bahasa Indonesia. Keadaan sekarang: **171
kemunculan** identifier berbahasa Indonesia di 43 berkas, **126 di antaranya satu kata**
(`$tercatat`). Perubahannya ikut menumpang saat berkasnya dibedah — bukan gelombang
tersendiri.

**Prosa komentar maksimal tiga baris.** Yang dibatasi **prosa**, bukan anotasi: docblock
`@param`, `@return`, `@throws`, dan `@var` dikecualikan, karena level 10 justru menuntut
docblock yang kaya dan membatasinya akan membuat dua aturan repo ini berkelahi.

Keadaan sekarang setelah anotasi dikecualikan: p50 satu baris, p75 tiga, p90 lima,
terpanjang empat belas. **234 blok** melewati tiga baris.

Batasnya bukan soal selera ringkas. Komentar panjang di repo ini hampir selalu berisi
dua hal yang bercampur — apa yang dilakukan kode, dan kenapa keputusannya diambil. Yang
kedua tempatnya di dokumen keputusan (`docs/design/`, register pertanyaan terbuka), di
mana ia bisa ditemukan orang yang mencarinya. Yang tertinggal di kode cukup tiga baris.

---

## 3. Batas

Ambang keras diletakkan **di antara p90 dan p95 kode yang sekarang sudah sehat**. Kode
baru harus bisa memenuhinya tanpa akrobat — p50 method di repo ini 8 baris — tapi cukup
ketat untuk menolak yang mulai menggelembung. Angka dari artikel umum akan meleset ke
dua arah sekaligus.

| Metrik | p50 | p90 | **Ambang keras** | **Plafon awal** |
| ------ | --: | --: | ---------------: | --------------: |
| LOC berkas PHP | 63 | 171 | **200** | 1353 |
| LOC berkas Vue | 35 | 533 | **250** | 1240 |
| LOC per method | 8 | 30 | **40** | 303 |
| Cyclomatic complexity | 1 | 4 | **8** | 32 |
| Parameter (termasuk constructor) | 1 | 2 | **4** | 9 |
| `return` per method | 1 | 2 | **4** | 6 |
| `mixed` tertulis | — | — | — | **48 → 12** |
| Prosa komentar | 1 | 5 | **3** | 234 |
| Identifier non-Inggris | — | — | **0** | 171 |
| `response()->json` tanpa DTO `Contract` | — | — | **0** | 22 |
| Model bocor ke `Data\Contract` | — | — | **0** | 0 |
| Blok duplikat lintas berkas | — | — | **0 baru** | 1670 |
| PHPStan tier ketat | 9 | — | **10** | — |
| PHPStan tier default | 5 | — | **10** | 1284 |

### 3.1 Keputusan yang perlu dibaca dua kali

**Tier ketat langsung level 10, hari pertama.** Ongkosnya 18 error dari 3 penyebab.
Menundanya membuat kode baru lahir dengan `mixed` yang nanti harus dibongkar lagi.

**Tier default 5 → 10 sebagai plafon, bukan sekali lompat.** 1284 error, hampir
seluruhnya di `app/Http` (959) dan `app/Models` (244) — persis dua tempat yang dibongkar
gelombang 2 dan 3. Angkanya turun sebagai **akibat** refactor. Ia jadi alat ukur paling
jujur: kalau 1284 tidak bergerak, refactor-nya belum menyentuh apa pun yang penting.

**`mixed` diberi plafon 48 → 12, bukan ambang 0.** Angka 12 itu sisa yang tidak bisa
dihilangkan tanpa berbohong: enam tanda tangan `rules()` milik Laravel, satu aksesor
JSON generik (`Scheme::getRule`), dua parameter `AuditTrail::record` yang memang
menerima bentuk sembarang, dan tiga kolom JSON yang bentuknya berubah per baris.
Tiga puluh enam sisanya **wajib** jadi DTO atau penyempitan tipe — 16 di antaranya
`toArray()` notifikasi yang bentuknya sudah tetap, dan empat `$attributes` Action yang
memang tempatnya `Data\Request`.

AGENTS.md aturan 5 berlaku penuh: cast, `@phpstan-ignore`, dan pelebaran tipe untuk
menghilangkan error tetap dilarang. Menurunkan angka ini hanya sah lewat memberi data
sebuah bentuk.

**`response()->json` tanpa kontrak diberi plafon 22, bukan ambang 0.** Berbeda dari
`mixed` yang terpusat dan bisa dihabisi sekali jalan, 22 endpoint ini tersebar di
controller yang sedang dibongkar gelombang 3 — jadi angkanya turun bersama pembongkaran
itu, bukan sebagai pekerjaan tersendiri. Yang **tidak** diberi kelonggaran adalah
kebocoran model ke `Data\Contract`: ambangnya 0 sejak hari pertama, karena satu
kebocoran saja sudah menjadikan bentuk tabel sebagai kontrak.

**Vue diberi ambang 250 meski p75-nya 204.** UI dibangun ulang mengikuti wireframe
SIMPAS v2, jadi angka ini mengikat kode baru. Plafon 1240 tetap dicatat supaya komponen
lama tidak tumbuh sambil menunggu digantikan.

**Duplikasi ditegakkan dua cara.** Plafon global 1670 yang hanya menyusut, plus aturan
keras: berkas baru tidak boleh memperkenalkan blok yang sudah ada di berkas lain. Yang
kedua mencegah lahirnya `ShowPage.vue` kembar berikutnya.

### 3.2 Yang sengaja tidak dibatasi

**Jumlah method per kelas.** Terdengar masuk akal, tapi di repo ini salah sasaran:
`FormSubmission` punya 23 relasi yang seluruhnya sah, dan batas jumlah method akan
menghukum relasi alih-alih logic. Batas LOC berkas menangkap hal yang sama tanpa efek
samping itu.

---

## 4. Penegakan

Dua berkas di akar repo, bersebelahan dengan `phpstan.neon` dan `pint.json`, plus satu
test Pest.

### 4.1 `quality-budget.txt`

```
# Plafon kualitas kode. Ditegakkan tests/Unit/Architecture/QualityBudgetTest.php.
# Angka hanya boleh TURUN. Menaikkannya berarti menambah utang dan harus dibahas
# eksplisit di PR — aturan yang sama dengan pending.txt dan excludePaths di
# phpstan-strict.neon.

# Ambang keras — berlaku untuk setiap berkas DI LUAR quality-legacy.txt
hard.php.file.loc:     200
hard.vue.file.loc:     250
hard.method.loc:        40
hard.method.cc:          8
hard.method.params:      4
hard.method.returns:     4
hard.comment.prose:      3
hard.contract.leak:      0

# Plafon yang menyusut
max.legacy.files:      112
max.mixed:              48
max.comment.overflow:  234
max.id.nonenglish:     171
max.untyped.json:       22
max.duplicate.blocks: 1670
max.phpstan.default:  1284
```

### 4.2 `quality-legacy.txt`

Seratus dua belas baris, satu berkas per baris — 44 PHP dan 68 Vue. Inilah **antrean
kerja lintas sesi**, bukan sekadar daftar pengecualian.

Sebarannya: `app/Http` 18, `app/Actions` 12, `app/Models` 7, `app/Support` 4, lainnya 3,
Vue 68.

### 4.3 `QualityBudgetTest`

Tujuh assertion, bentuknya meniru `BddCoverageTest`:

| # | Assertion | Yang dicegahnya |
| - | --------- | --------------- |
| 1 | Berkas di luar daftar memenuhi seluruh ambang keras | Kode baru lahir gemuk |
| 2 | **Daftar tidak memuat berkas yang sudah memenuhi ambang** | Berkas beres tapi dibiarkan di daftar |
| 3 | Daftar tidak memuat berkas yang tidak ada | Daftar jadi arsip hantu |
| 4 | Tidak ada plafon yang terlampaui | Utang bertambah diam-diam |
| 5 | `mixed` tidak melewati plafonnya | DTO diakali cast |
| 6 | Blok duplikat tidak melewati plafonnya | Duplikasi bertambah diam-diam |
| 7 | Berkas non-legacy tidak berduplikasi satu sama lain | Komponen kembar berikutnya |
| 8 | `Data\Contract` tidak punya properti bertipe Model dan tidak menyebar atributnya | Bentuk tabel jadi kontrak tanpa ada yang memutuskan |
| 9 | `Data\Contract` tidak menyusun `Data\View` | Layar jadi pemilik kontrak |
| 10 | Setiap `response()->json()` melewati DTO `Contract` | Kontrak ditetapkan diam-diam di tengah controller |
| 11 | Prosa komentar ≤ 3 baris di luar daftar legacy | Keputusan menumpuk di kode alih-alih di dokumen |
| 12 | Identifier non-Inggris tidak melewati plafonnya | Penamaan bercampur dua bahasa |

**Assertion 2 adalah engselnya.** Ia menyalin `BddCoverageTest` assertion 5
(*"pending.txt tidak memuat skenario yang sudah diimplementasi"*), dan dialah yang
membuat daftarnya menyusut sendiri: begitu sebuah berkas dibereskan, test **gagal**
sampai barisnya dihapus. Tidak ada jalan membereskan kode tanpa memperbarui daftar, dan
tidak ada jalan memperbarui daftar tanpa benar-benar membereskan kode.

**Assertion 10 menutup celah yang paling luput.** `app/Http` dikecualikan dari tier
ketat, jadi tanpa assertion ini `response()->json()` boleh mengirim apa pun tanpa satu
pun pemeriksaan — termasuk seluruh kolom sebuah tabel.

**Assertion penempatan direktori dicabut sebelum ditulis.** Rancangan awal memuat
assertion "berkas baru masuk `Actions/` bukan `Http/`". Ia ternyata **redundan**:
controller yang menggemuk sudah melanggar ambang di assertion 1 lewat ukuran dan
kompleksitas method-nya. Dua assertion untuk satu kegagalan hanya menambah tempat yang
harus diperbaiki saat aturannya berubah.

**Assertion 12 sengaja lemah, dan itu disebutkan.** Ia memakai daftar kata, jadi ia
menangkap kemunculan yang sudah dikenal — bukan mendeteksi bahasa. Gunanya mencegah
kemunduran pada kata yang sudah terdaftar, bukan menjamin seluruh penamaan Inggris.
Yang terakhir itu urusan review.

**Batasan yang jujur:** assertion 7 mengandalkan konvensi direktori, jadi ia tidak bisa
menghentikan orang menaruh logic di `Support/` yang memang sah. Ia menangkap kasus yang
paling sering — controller menggemuk — bukan seluruh bentuk penyelewengan.

---

## 5. Urutan pengerjaan

Tiga prinsip:

1. **Pasang pagar sebelum membedah apa pun**, supaya pekerjaannya terukur dan bukan cuma
   terasa.
2. **Kerjakan dari daun ke akar**, karena model dipanggil controller — membereskan
   controller lebih dulu berarti memindahkan panggilan yang sama dua kali.
3. **Hasilkan bentuk yang API-nya tinggal ditambahkan.** Setiap `Query` yang datanya
   kelak dipaparkan ke luar mengembalikan `Data\Contract` sejak sekarang. Tujuannya
   tunggal: saat API dibangun nanti, yang ditambahkan hanya route dan autentikasi —
   bukan membongkar ulang apa yang baru saja dirapikan.

| # | Gelombang | Isi | Ukuran keberhasilan |
| - | --------- | --- | ------------------- |
| 0 | **Pasang pagar** | Dua berkas plafon, `QualityBudgetTest`, tier ketat 9 → **10** (3 penyebab) | Gerbang hijau; mulai detik ini tidak ada yang bisa memburuk |
| 1 | **Habisi `mixed` yang bisa dihabisi** | 48 → 12; 16 `toArray()` notifikasi dan 4 `$attributes` Action jadi DTO | `max.mixed: 12`, dan sisanya seluruhnya punya alasan |
| 2 | **Model** | 123 method logic dipilah lewat §2.5; pindah ke `Actions/` dan `Queries/` | 244 error level 10 turun; 7 berkas keluar daftar |
| 3 | **Controller** | 18 berkas `Http`; `SubmissionViewController` 1353 → cangkang; 22 `response()->json` jadi DTO `Contract` | 959 error turun; 18 berkas keluar daftar; `max.untyped.json` → 0 |
| 4 | **Actions yang melanggar** | 12 berkas | 12 berkas keluar daftar |
| 5 | **Tier default 5 → 10** | Sisa yang tidak rontok sebagai efek samping gelombang 2–4 | `max.phpstan.default` → 0 |
| 6 | **Vue legacy** | 68 berkas; sebagian besar kemungkinan hilang karena **dihapus** saat layarnya diganti | `max.legacy.files` → 0 |

**Kenapa gelombang 0 tidak boleh ditawar.** Ambang kerasnya harus hidup sebelum UI baru
ditulis. Kalau tidak, 65 layar SIMPAS v2 lahir tanpa pagar dan percakapan ini terulang
enam bulan lagi dengan angka yang lebih besar.

**Kenapa `mixed` didahulukan meski terdengar sepele.** Ia memaksa DTO pertama lahir.
Setelah bentuk data punya nama, gelombang 2 dan 3 jadi pemindahan alih-alih perancangan
— dan itu jauh lebih aman.

**Kenapa kontrak API dikerjakan di gelombang 3, bukan ditunda sampai API-nya ada.**
Ke-22 endpoint JSON itu hidup di controller yang memang sedang dibongkar. Menyentuhnya
sekarang berbiaya hampir nol; menyentuhnya nanti berarti membuka kembali berkas yang
sudah dinyatakan selesai — persis kerja dua kali yang seluruh urutan ini dirancang untuk
menghindarinya.

**Risiko terbesar di gelombang 2.** Memindahkan 123 method dari model menyentuh hampir
seluruh codebase. Yang menahannya 820 test yang sudah ada. Kalau ada gelombang yang
harus dikerjakan paling kecil-kecil, ini.

**Definisi selesai:** `quality-legacy.txt` kosong, `max.phpstan.default: 0`,
`max.duplicate.blocks` menyusut signifikan, dan `excludePaths` di `phpstan-strict.neon`
tinggal daftar yang memang tidak akan pernah ketat.

---

## 6. Cara memakainya di sesi berikutnya

Tanpa perlu membaca bagian mana pun di atas:

```bash
composer run quality            # QualityBudgetTest ikut jalan; ia test Pest biasa
head -20 quality-legacy.txt     # ambil satu berkas dari antrean
#  ... bedah mengikuti §2 ...
#  hapus barisnya dari quality-legacy.txt
#  turunkan max.legacy.files di quality-budget.txt
composer run quality            # assertion 2 memastikan keduanya konsisten
```

Kalau sebuah angka harus **naik**, itu bukan kesalahan test — itu keputusan menambah
utang, dan tempatnya dibahas di PR, bukan diselesaikan diam-diam.

---

## 7. Yang tidak dikerjakan dokumen ini

- **Tidak menambah fitur.** Murni bentuk.
- **Tidak menyentuh `docs/requirement-analysis/`.** DDD dan BDD tidak berubah karena
  kode dirapikan.
- **Tidak merancang UI baru.** Layar SIMPAS v2 punya jalurnya sendiri; rencana ini hanya
  memastikan ia lahir di dalam pagar.
- **Tidak membangun API.** Tidak ada route `/api`, skema autentikasi, rate limit,
  versioning berjenjang, maupun dokumentasi OpenAPI. Yang dikerjakan hanya bentuk
  keluarannya — `Data\Contract` beserta tiga aturannya — supaya API nanti tinggal
  ditambahkan. Pembangunan API-nya sendiri punya spec tersendiri, dan prasyaratnya dua:
  rencana ini selesai, dan migrasi data lama tuntas.
