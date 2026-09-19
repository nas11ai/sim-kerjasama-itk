# Rencana Refactor — Bentuk, Batas, dan Penegakannya

**Versi:** 1.0
**Tanggal:** 2026-09-19
**Status:** Disetujui, belum dikerjakan

Dokumen ini menetapkan **bentuk** kode di repo ini, **batas** yang mengikatnya, dan
**mesin** yang menegakkan batas itu. Ia tidak menambah satu pun fitur dan tidak
menyentuh `docs/requirement-analysis/`.

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
  Data/             bentuk payload, spatie/laravel-data            [ada, 1 berkas]
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
| Tulis | `FormRequest → Data → Action → redirect` |
| Baca | `Query → Data → Inertia::render` |

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

### 2.4 Garis untuk Model

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
| `mixed` tertulis | — | — | **0** | 48 |
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

**`mixed` diberi ambang 0, bukan plafon.** Empat puluh delapan kemunculan itu sedikit
dan terpusat. Penyelesaiannya **wajib** lewat DTO atau penyempitan tipe; AGENTS.md
aturan 5 sudah melarang cast, `@phpstan-ignore`, dan pelebaran tipe untuk menghilangkan
error, dan larangan itu berlaku penuh di sini.

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
hard.mixed:              0

# Plafon yang menyusut
max.legacy.files:      112
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
| 5 | `mixed` = 0 di luar daftar | DTO diakali cast |
| 6 | Tidak ada blok duplikat baru | Komponen kembar berikutnya |
| 7 | Berkas baru masuk `Actions/`, `Queries/`, `Data/`, atau `Policies/` — bukan `Http/` | Controller gemuk lagi |

**Assertion 2 adalah engselnya.** Ia menyalin `BddCoverageTest` assertion 5
(*"pending.txt tidak memuat skenario yang sudah diimplementasi"*), dan dialah yang
membuat daftarnya menyusut sendiri: begitu sebuah berkas dibereskan, test **gagal**
sampai barisnya dihapus. Tidak ada jalan membereskan kode tanpa memperbarui daftar, dan
tidak ada jalan memperbarui daftar tanpa benar-benar membereskan kode.

**Batasan yang jujur:** assertion 7 mengandalkan konvensi direktori, jadi ia tidak bisa
menghentikan orang menaruh logic di `Support/` yang memang sah. Ia menangkap kasus yang
paling sering — controller menggemuk — bukan seluruh bentuk penyelewengan.

---

## 5. Urutan pengerjaan

Dua prinsip: **pasang pagar sebelum membedah apa pun**, supaya pekerjaannya terukur dan
bukan cuma terasa; dan **kerjakan dari daun ke akar**, karena model dipanggil controller
— membereskan controller lebih dulu berarti memindahkan panggilan yang sama dua kali.

| # | Gelombang | Isi | Ukuran keberhasilan |
| - | --------- | --- | ------------------- |
| 0 | **Pasang pagar** | Dua berkas plafon, `QualityBudgetTest`, tier ketat 9 → **10** (3 penyebab) | Gerbang hijau; mulai detik ini tidak ada yang bisa memburuk |
| 1 | **Habisi `mixed`** | 48 → 0; DTO pertama masuk `app/Data` | `hard.mixed: 0` ditegakkan penuh |
| 2 | **Model** | 123 method logic dipilah lewat §2.4; pindah ke `Actions/` dan `Queries/` | 244 error level 10 turun; 7 berkas keluar daftar |
| 3 | **Controller** | 18 berkas `Http`; `SubmissionViewController` 1353 → cangkang | 959 error turun; 18 berkas keluar daftar |
| 4 | **Actions yang melanggar** | 12 berkas | 12 berkas keluar daftar |
| 5 | **Tier default 5 → 10** | Sisa yang tidak rontok sebagai efek samping gelombang 2–4 | `max.phpstan.default` → 0 |
| 6 | **Vue legacy** | 68 berkas; sebagian besar kemungkinan hilang karena **dihapus** saat layarnya diganti | `max.legacy.files` → 0 |

**Kenapa gelombang 0 tidak boleh ditawar.** Ambang kerasnya harus hidup sebelum UI baru
ditulis. Kalau tidak, 65 layar SIMPAS v2 lahir tanpa pagar dan percakapan ini terulang
enam bulan lagi dengan angka yang lebih besar.

**Kenapa `mixed` didahulukan meski terdengar sepele.** Ia memaksa DTO pertama lahir.
Setelah bentuk data punya nama, gelombang 2 dan 3 jadi pemindahan alih-alih perancangan
— dan itu jauh lebih aman.

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
