# Batch prosa komentar — 131 berkas keluar antrean

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Bagian atas menetapkan lingkup dan
> keputusan; bagian *Rencana Implementasi* memberi langkahnya. Baca
> *Cara plafon prosa bekerja* sampai habis sebelum menyentuh berkas pertama —
> semantiknya tidak seperti dugaan, dan salah paham di sana membuat seluruh
> batch ini terasa acak.

**Goal:** Mengeluarkan **131 berkas** dari `quality-legacy.txt` dengan
memendekkan prosa komentar, tanpa mengubah satu baris perilaku pun.

**Architecture:** Borong per kategori, bukan per berkas. Ke-131 berkas ini
pelanggarannya **tunggal dan sama**: `prosa komentar terlalu panjang`. Tidak ada
test baru, tidak ada perubahan perilaku, tidak ada sensus K2 — karena tidak ada
kode yang dipindahkan atau dihapus. Gerbang penuh dijalankan **sekali per Task**,
bukan per berkas.

**Tech Stack:** PHP 8.4 · Pest · PHPStan tier strict · `App\Support\Quality\CommentMetrics`

**Spec induk:** [`docs/design/refactor-plan.md`](refactor-plan.md) §4.1 dan §4.2

**Pendahulu:** [`refactor-plan-wave-2-review-cluster.md`](refactor-plan-wave-2-review-cluster.md)

**Status:** selesai 2026-09-21, dieksekusi di branch `feat/init-be-development`,
commit `91b3147`..`affff38` (kedua plafon turun di `ce802e3`).

| Ukuran                 | Sebelum | Sesudah |
| ---------------------- | ------: | ------: |
| Berkas di antrean      |     272 |     141 |
| `max.comment.overflow` |     164 |      32 |
| Test                   |     939 |     939 |

---

## Kenapa batch ini ada

Antrean legacy berisi **272 berkas**. Sensus pelanggarannya:

| Sebab | Berkas | Sifat |
| ----- | -----: | ----- |
| **Hanya** prosa komentar terlalu panjang | **131** | mekanis, nol risiko perilaku |
| **Hanya** blok duplikat | 31 | dedup sekali, banyak rontok |
| Butuh bedah sungguhan (4+ pelanggaran) | 14 | 12 di antaranya controller = gelombang 3 |
| Sisanya (method kepanjangan/kompleks, campuran) | 96 | extract-method, batchable |

Gelombang 2 mengerjakan kategori **paling mahal** — model yang sarat business
logic — dengan laju ~4 berkas/hari. Itu benar untuk kategori itu, dan menghasilkan
belasan bug nyata. Tapi itu bukan bentuk pekerjaan yang tersisa: **48% antrean
hanya soal panjang komentar.** Dikerjakan satu-satu dengan gerbang penuh per
berkas, 131 berkas berarti ~5 jam murni menunggu CI. Dikerjakan per direktori
dengan gerbang per Task, ia satu sesi.

---

## Lingkup

131 berkas, dikelompokkan jadi enam Task berdasarkan direktori:

| Task | Area | Berkas |
| ---- | ---- | -----: |
| 2 | `app/Actions/**` | 40 |
| 3 | `app/Models/` | 17 |
| 4 | `app/Support/**` | 35 |
| 5 | `app/Http/**` | 20 |
| 6 | `app/Notifications/` | 9 |
| 7 | Sisanya (`Console`, `Data`, `Enums`, `Jobs`, `Listeners`, `Mail`, `Policies`, `Providers`, `Services`) | 10 |

Daftar lengkapnya di *Lampiran A*, beserta panjang prosa terpanjang tiap berkas.

**Di luar lingkup:** 32 berkas `app/` yang melanggar prosa **tetapi juga**
melanggar hal lain. Memendekkan komentarnya tidak mengeluarkannya dari antrean,
dan komentarnya kemungkinan ditulis ulang saat berkasnya dibedah nanti.

---

## Cara plafon prosa bekerja

**Baca bagian ini dua kali.** Ia sumber seluruh kebingungan di batch ini.

Plafonnya `hard.comment.prose: 3` — maksimal **3 baris prosa berturut-turut**.
Penghitungnya `App\Support\Quality\CommentMetrics::longestProseRun()`:

```php
foreach (baris berkas) {
    if (bukan baris komentar) { catat run terpanjang; run = 0; lanjut; }   // ← HANYA ini yang mereset
    if (baris prosa) { run++; }
}
```

**Baris komentar** = setelah `trim()`, diawali `//`, `*`, atau `/*`.

**Baris prosa** = baris komentar yang, setelah awalannya dibuang, isinya
**≥ 4 karakter** dan **tidak diawali `@`**.

Tiga akibat yang tidak intuitif:

**1. Anotasi tidak mereset hitungan.** `@param`, `@return`, `@throws` bukan prosa
(tidak menambah), tapi ia baris komentar — jadi **tidak mengenolkan run**. Prosa
sebelum dan sesudah blok anotasi **bersambung jadi satu run**.

**2. Baris kosong di dalam docblock tidak mereset.** ` *` sendirian isinya nol
karakter → bukan prosa, tapi tetap baris komentar.

**3. Dua blok komentar yang menempel tanpa kode di antaranya dihitung satu run.**

Contoh yang **melanggar** (run = 5, meski tiap bloknya pendek) — **angka ini
keliru: yang benar run = 3, lihat *Koreksi* di bawah**:

```php
    // Alasan pertama, cukup panjang untuk dihitung.
    // Lanjutannya di baris kedua ini.

    /** Blok lain menempel, tidak ada kode pemisah. */
    // Dan satu baris lagi di sini.
    // Serta satu lagi.
    $hasil = hitung();
```

Contoh yang **lolos** (run = 3, lalu direset `$a = 1;`, lalu run = 2):

```php
    // Tiga baris prosa di sini.
    // Baris kedua.
    // Baris ketiga.
    $a = 1;

    // Dua baris menjelaskan langkah berikutnya.
    // Baris kedua.
    $b = 2;
```

### Koreksi (dicatat saat Task 7, setelah diukur langsung)

**Contoh "melanggar (run = 5)" di atas keliru — nilai sebenarnya run = 3.**
Baris kosong di antara kedua blok itu, setelah `trim()`, tidak diawali `//`,
`*`, maupun `/*`. Jadi ia **bukan baris komentar**, dan cabang pertama
penghitung **mereset** run. Diukur langsung dengan `CommentMetrics`:

| Susunan                                          | Klaim di atas | Terukur |
| ------------------------------------------------ | ------------: | ------: |
| Dua blok `//` dipisah **baris kosong sungguhan**  |             5 |   **3** |
| Dua blok `//` **menempel**, tanpa baris kosong    |             — |   **5** |
| Docblock dengan baris ` *` sendirian di tengahnya |             — |   **4** |

Butir 2 tetap benar: ` *` sendirian **tetap** baris komentar, jadi ia tidak
mereset. Butir 3 hanya benar kalau di antara kedua blok **tidak ada baris
kosong** — itulah yang membedakan baris dua dan tiga tabel di atas.

**Celahnya tidak boleh dipakai.** Menyisipkan baris kosong memang mereset
hitungan, tetapi tidak memadatkan apa pun — kegagalan yang sama dengan melipat
ulang baris. Pemadatan tetap lewat K2a atau K2b.

---

## Keputusan yang sudah diambil

Empat keputusan ini **sudah ditetapkan**. Kalau salah satunya terasa salah saat
dikerjakan, hentikan dan bawa ke pemilik repo.

### K1 — Memendekkan, bukan menghapus

Komentar di repo ini menjelaskan **kenapa**, bukan **apa**. Menghapusnya membuang
satu-satunya tempat alasan itu tercatat. Yang dituntut plafon ini bukan "komentar
lebih sedikit", melainkan "prosa yang padat".

**Dilarang:** membuang alasan, memindahkannya ke dokumen desain (locality-nya
hilang), atau menggantinya dengan komentar yang menyebut ulang nama method.

### K2 — Dua teknik yang disahkan

**(a) Padatkan.** Simpan kalimat yang menentukan, buang yang mengulang kode.
Sebelum (run = 6):

```php
    /**
     * BR-REV-09 — reviewer hanya bisa ditugaskan dalam rentang masa tugasnya.
     * Ini penting karena reviewer yang masa tugasnya habis tidak lagi berwenang
     * menilai. Kalau dibiarkan, ia akan tetap muncul di daftar penugasan dan
     * Operator bisa menugaskannya tanpa sadar. Pemeriksaannya di sini, bukan
     * di controller, supaya seluruh jalur penugasan tunduk pada aturan yang sama.
     * Nilai `null` berarti tidak dibatasi.
     */
```

Sesudah (run = 3):

```php
    /**
     * BR-REV-09 — reviewer hanya bisa ditugaskan dalam rentang masa tugasnya.
     * Di sini, bukan di controller, supaya seluruh jalur penugasan tunduk pada
     * aturan yang sama. `null` berarti tidak dibatasi.
     */
```

**(b) Dekatkan.** Pecah satu blok panjang jadi dua blok yang masing-masing
menempel pada baris yang ia jelaskan. Baris kode di antaranya mereset hitungan,
**dan** komentarnya jadi lebih berguna karena tidak lagi menjelaskan lima hal
sekaligus dari kejauhan.

Utamakan (b) kalau blok panjangnya memang menjelaskan beberapa langkah berbeda.
Utamakan (a) kalau ia menjelaskan satu hal dengan bertele-tele.

### K3 — Kode BR wajib bertahan

Referensi `BR-XX-NN` dan ID skenario `[ABC-DEF-01]` **tidak boleh hilang** dalam
pemadatan. Itu satu-satunya tautan antara kode dan dokumen kesepakatan. Kalau
sebuah blok menyebut tiga BR dan harus dipendekkan, ketiga kodenya tetap ditulis
walau kalimat penjelasnya dipangkas.

### K4 — Anotasi PHPStan tidak disentuh

`@param`, `@return`, `@var`, `@method`, `@property` tidak dihitung prosa dan
**tidak boleh dipangkas untuk mengejar angka**. Tier strict level 10 menuntutnya.
Membuangnya menukar satu pelanggaran dengan pelanggaran lain yang lebih berat.

---

## Global Constraints

- **Prosa komentar bahasa Indonesia, identifier bahasa Inggris.** (AGENTS.md)
- **Nol perubahan perilaku.** Kalau sebuah Task mengubah baris kode non-komentar,
  ia keluar lingkup — hentikan dan laporkan.
- **Jumlah test tidak boleh berubah.** Batch ini tidak menambah maupun mengurangi
  test. Kalau jumlahnya bergeser, ada yang salah.
- **`pending.txt` dan `uncovered.txt` tidak disentuh.**
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Berkas yang jadi bersih WAJIB dikeluarkan dari `quality-legacy.txt` dan
  `max.legacy.files` diturunkan.** `QualityBudgetTest` assertion 2 **gagal** kalau
  ada berkas bersih yang masih terdaftar. Ini jebakan paling sering di batch ini.
- **Container** (PHP host bukan 8.4):
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```
  Disingkat **`CI`** di seluruh rencana ini.

---

## Risiko

**Yang terbesar: memangkas alasan, bukan memangkas kata.** Plafon ini gampang
dipenuhi dengan menghapus komentar, dan hasilnya kodebase yang lulus gerbang tapi
kehilangan seluruh memori kenapa sesuatu dilakukan. K1 dan K3 ada untuk ini, dan
review per Task memeriksanya — bukan angka run-nya, melainkan apakah alasannya
masih terbaca.

**Yang paling mungkin terlewat: menurunkan plafon.** Assertion 2 menolak berkas
bersih yang masih terdaftar, jadi lupa mengedit `quality-legacy.txt` membuat
gerbang merah dengan pesan yang menyebut berkasnya satu per satu. Ikuti pesannya.

**Yang paling mungkin salah kaprah: mengira tiap blok dihitung sendiri-sendiri.**
Lihat *Cara plafon prosa bekerja* butir 3.

**Yang tidak berisiko:** deteksi duplikasi membuang komentar sebelum menghitung
(`DuplicationScanner::COMMENT_PREFIXES`), jadi memendekkan komentar **tidak**
menggeser angka `berbagi N blok` berkas mana pun.

---

## Definisi selesai

- [ ] `CI composer run quality` hijau
- [ ] `npm run quality` hijau
- [ ] 131 berkas di *Lampiran A* keluar dari `quality-legacy.txt`
- [ ] `max.legacy.files` **272 → 141**
- [ ] `max.comment.overflow` **164 → 32**
- [ ] Jumlah test **tidak berubah** (939 sebelum dan sesudah)
- [ ] `git diff` batch ini tidak memuat satu pun baris non-komentar
- [ ] Tiap BR dan ID skenario yang ada sebelum batch masih ada sesudahnya

---

## Yang tidak dikerjakan dokumen ini

- **31 berkas blok-duplikat.** Batch tersendiri; kluster terbesarnya berbagi
  317/167/158/147 blok dan kemungkinan halaman Vue nyaris kembar.
- **96 berkas method kepanjangan/kompleks.** Batch extract-method per direktori.
- **14 controller berat.** Itu gelombang 3, dan di sana disiplin TDD penuh berlaku.
- **32 berkas prosa-plus-lainnya.** Komentarnya ikut dirapikan saat berkasnya
  dibedah.

---
---

# Rencana Implementasi

**Goal:** 131 berkas keluar antrean lewat pemendekan prosa komentar.

**Architecture:** Satu Task = satu area direktori = satu commit = satu gerbang.

**Spec:** bagian atas dokumen ini.

## Global Constraints

Seluruh *Global Constraints* dan K1–K4 di atas berlaku untuk **setiap** Task.
Yang paling sering dilanggar: berkas bersih wajib dikeluarkan dari
`quality-legacy.txt` di Task yang sama, bukan ditunda ke akhir.

---

### Task 1: Alat ukur

Tanpa alat ini setiap Task berikutnya menebak baris mana yang bermasalah.

**Files:**
- Create: `tools/prosa.php`

**Interfaces:**
- Consumes: —
- Produces: `php tools/prosa.php <path...>` → baris `path:baris jumlah` untuk tiap
  run prosa > 3; tanpa argumen ia memindai seluruh `app/**/*.php`.

`tools/` di luar korpus terukur (`SourceTree::measured` hanya `app/**/*.php` dan
`resources/js/**/*.vue`), jadi berkas ini tidak ikut diukur pagar.

- [ ] **Step 1: Tulis alatnya**

```php
<?php

// tools/prosa.php — laporan run prosa komentar. Di luar korpus terukur.

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\Quality\SourceFile;
use App\Support\Quality\SourceTree;

/** @return list<array{int, int}> pasangan [baris awal, panjang run] untuk run > 3 */
function runProsa(string $path): array
{
    $hasil = [];
    $run = 0;
    $awal = null;
    $nomor = 0;

    foreach (SourceFile::lines(SourceFile::read($path)) as $line) {
        $nomor++;
        $t = trim($line);
        $komentar = str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*');

        if (!$komentar) {
            if ($run > 3) {
                $hasil[] = [$awal, $run];
            }
            $run = 0;
            $awal = null;

            continue;
        }

        $body = trim(preg_replace('#^(//+|/\*+|\*+/?)#', '', $t) ?? '');

        if (strlen($body) >= 4 && !str_starts_with($body, '@')) {
            $run++;
            $awal ??= $nomor;
        }
    }

    if ($run > 3) {
        $hasil[] = [$awal, $run];
    }

    return $hasil;
}

$paths = array_slice($argv, 1);

if ($paths === []) {
    $paths = SourceTree::paths(base_path(), 'app', 'php');
}

$total = 0;

foreach ($paths as $path) {
    $abs = str_starts_with($path, '/') ? $path : base_path($path);

    foreach (runProsa($abs) as [$baris, $run]) {
        printf("%s:%d  run=%d\n", $path, $baris, $run);
        $total++;
    }
}

printf("\n%d run melewati plafon 3.\n", $total);
```

- [ ] **Step 2: Jalankan, pastikan angkanya cocok dengan pagar**

Run: `CI php tools/prosa.php | tail -1`
Expected: melaporkan run di 163 berkas `app/` (jumlah run bisa lebih besar dari
jumlah berkas — satu berkas bisa punya beberapa run).

Bandingkan jumlah **berkas**-nya:

```bash
CI php tools/prosa.php | grep ':' | grep -oP '^[^:]+' | sort -u | wc -l
```
Expected: `163`

`grep ':'` membuang baris ringkasan di akhir keluaran; tanpa itu hasilnya 164
dan kamu akan mengira alatnya meleset satu.

Kalau bukan 163, alatnya tidak sepadan dengan `CommentMetrics` — perbaiki alatnya,
jangan lanjut.

- [ ] **Step 3: Commit**

```bash
git add tools/prosa.php
git commit -m "chore(quality): alat laporan run prosa komentar"
```

---

### Task 2: `app/Actions/**` — 40 berkas

**Files:**
- Modify: 40 berkas `app/Actions/**` di *Lampiran A* bagian Actions
- Modify: `quality-legacy.txt`
- Modify: `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/prosa.php` dari Task 1
- Produces: 40 berkas dengan `violations()` === `[]`

- [ ] **Step 1: Lihat sasarannya**

```bash
CI php tools/prosa.php $(grep '^app/Actions/' quality-legacy.txt | tr '\n' ' ')
```

Keluarannya `path:baris run=N`. Kerjakan berkas per berkas dari daftar itu.

- [ ] **Step 2: Pendekkan tiap run, pakai teknik K2**

Untuk tiap baris yang dilaporkan: buka berkasnya di baris itu, lalu **padatkan**
(K2a) atau **dekatkan** (K2b). Pertahankan kode BR (K3). Jangan sentuh anotasi
(K4). Jangan ubah satu pun baris kode.

- [ ] **Step 3: Pastikan tidak ada baris kode yang tersenggol**

```bash
git diff -U0 -- app/Actions/ | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
  | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong.** Setiap baris yang muncul di sini adalah baris non-komentar
yang berubah — kembalikan.

- [ ] **Step 4: Pastikan berkasnya sudah bersih**

```bash
CI php tools/prosa.php $(grep '^app/Actions/' quality-legacy.txt | tr '\n' ' ') | tail -1
```
Expected: `0 run melewati plafon 3.`

- [ ] **Step 5: Keluarkan dari antrean dan turunkan plafon**

Daftarnya diambil dari berkas yang benar-benar kamu ubah, bukan dari pola
direktori — `app/Actions/` juga memuat berkas antrean yang **bukan** bagian batch
ini:

```bash
grep -c '^app/\|^resources/' quality-legacy.txt          # catat angka sebelum
for f in $(git diff --name-only -- app/Actions/); do sed -i "\|^${f}$|d" quality-legacy.txt; done
grep -c '^app/\|^resources/' quality-legacy.txt          # harus turun 40
```

Lalu setel `max.legacy.files: 232` di `quality-budget.txt`.

- [ ] **Step 6: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, **939 test** — jumlahnya tidak berubah.

Kalau assertion 2 merah, ia menyebut berkas bersih yang masih terdaftar: buang
barisnya dan turunkan plafonnya lagi. Kalau assertion 4 merah,
`max.legacy.files` diturunkan terlalu sedikit.

- [ ] **Step 7: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Actions quality-legacy.txt quality-budget.txt
git commit -m "refactor(quality): padatkan prosa komentar di app/Actions"
```

---

### Task 3: `app/Models/` — 17 berkas

**Files:**
- Modify: 17 berkas `app/Models/` di *Lampiran A*
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/prosa.php`
- Produces: 17 berkas dengan `violations()` === `[]`

- [ ] **Step 1: Lihat sasarannya**

```bash
CI php tools/prosa.php app/Models/AuditEntry.php app/Models/ExportJob.php \
  app/Models/Form.php app/Models/FormField.php app/Models/FormPhase.php \
  app/Models/FormSubmissionOverride.php app/Models/InvitationToken.php \
  app/Models/OutputTypeDefinition.php app/Models/Partner.php \
  app/Models/ResearchOutput.php app/Models/Reviewer.php app/Models/Scheme.php \
  app/Models/StudentMember.php app/Models/SubmissionDate.php \
  app/Models/SubmissionTargetOutput.php app/Models/User.php \
  app/Models/UserProfile.php
```

- [ ] **Step 2: Pendekkan tiap run, pakai teknik K2**

Model memuat banyak `@property` — **jangan disentuh** (K4). Yang dipangkas hanya
kalimat penjelas di antaranya. Ingat butir 1 *Cara plafon prosa bekerja*: prosa
sebelum dan sesudah blok `@property` **bersambung**, jadi run bisa panjang padahal
tiap potongannya pendek.

- [ ] **Step 3: Pastikan tidak ada baris kode yang tersenggol**

```bash
git diff -U0 -- app/Models/ | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
  | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong.**

- [ ] **Step 4: Pastikan bersih, keluarkan dari antrean, turunkan plafon**

```bash
CI php tools/prosa.php $(git diff --name-only -- app/Models/ | tr '\n' ' ') | tail -1
for f in $(git diff --name-only -- app/Models/); do sed -i "\|^${f}$|d" quality-legacy.txt; done
grep -c '^app/\|^resources/' quality-legacy.txt   # harus turun 17
```

Turunkan `max.legacy.files` 232 → 215.

- [ ] **Step 5: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 939 test.

- [ ] **Step 6: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Models quality-legacy.txt quality-budget.txt
git commit -m "refactor(quality): padatkan prosa komentar di app/Models"
```

---

### Task 4: `app/Support/**` — 35 berkas

**Files:**
- Modify: 35 berkas `app/Support/**` di *Lampiran A*
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/prosa.php`
- Produces: 35 berkas dengan `violations()` === `[]`

Area ini yang **paling padat alasan**: Guard dan Gate hampir seluruhnya berisi
penjelasan kenapa sebuah penolakan ada. `AccountStateGuard` (13),
`PhaseAccessGate` (13), `Deliver` (13) adalah run terpanjang di seluruh batch.
Di sinilah K1 paling mudah dilanggar — kalau ragu, pakai K2b (dekatkan) alih-alih
memangkas.

- [ ] **Step 1: Lihat sasarannya**

```bash
CI php tools/prosa.php $(grep '^app/Support/' quality-legacy.txt | tr '\n' ' ')
```

- [ ] **Step 2: Pendekkan tiap run, pakai teknik K2**

- [ ] **Step 3: Pastikan tidak ada baris kode yang tersenggol**

```bash
git diff -U0 -- app/Support/ | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
  | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong.**

- [ ] **Step 4: Pastikan bersih, keluarkan dari antrean, turunkan plafon**

```bash
CI php tools/prosa.php $(git diff --name-only -- app/Support/ | tr '\n' ' ') | tail -1
for f in $(git diff --name-only -- app/Support/); do sed -i "\|^${f}$|d" quality-legacy.txt; done
grep -c '^app/\|^resources/' quality-legacy.txt   # harus turun 35
```

Turunkan `max.legacy.files` 215 → 180.

- [ ] **Step 5: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 939 test. `SupportPurityTest` juga harus tetap hijau — ia
memindai isi berkas `app/Support/`, jadi salah potong yang menghapus baris kode
akan terlihat di sini juga.

- [ ] **Step 6: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Support quality-legacy.txt quality-budget.txt
git commit -m "refactor(quality): padatkan prosa komentar di app/Support"
```

---

### Task 5: `app/Http/**` — 20 berkas

**Files:**
- Modify: 20 berkas `app/Http/**` di *Lampiran A*
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/prosa.php`
- Produces: 20 berkas dengan `violations()` === `[]`

**Perhatian:** `app/Http/` memuat 14 controller berat yang **bukan** bagian batch
ini (`SubmissionViewController`, `ReviewController`, dan kawan-kawan). Yang
dikerjakan hanya 20 berkas di *Lampiran A* — sisanya tetap di antrean karena
punya pelanggaran lain, dan komentarnya ditulis ulang saat gelombang 3.

- [ ] **Step 1: Lihat sasarannya**

```bash
CI php tools/prosa.php \
  app/Http/Controllers/OrganizationController.php \
  app/Http/Controllers/SchemeController.php \
  app/Http/Controllers/SubmissionWithdrawalController.php \
  app/Http/Controllers/Audit/AuditLogController.php \
  app/Http/Controllers/Auth/AuthenticatedSessionController.php \
  app/Http/Controllers/Auth/RegisteredUserController.php \
  app/Http/Controllers/Config/ReferenceEntityController.php \
  app/Http/Controllers/Export/DocumentPrintController.php \
  app/Http/Controllers/Identity/NidnVerificationController.php \
  app/Http/Controllers/Identity/UserAccessController.php \
  app/Http/Controllers/Monev/MonevCycleController.php \
  app/Http/Controllers/Notification/NotificationChannelController.php \
  app/Http/Controllers/Notification/NotificationController.php \
  app/Http/Controllers/Review/ReviewSummaryResolutionController.php \
  app/Http/Controllers/Submission/BudgetLineItemController.php \
  app/Http/Controllers/Submission/PhaseOverrideController.php \
  app/Http/Controllers/Submission/ResearchOutputController.php \
  app/Http/Controllers/Submission/SubmissionDecisionController.php \
  app/Http/Middleware/HandleInertiaRequests.php \
  app/Http/Requests/Auth/LoginRequest.php
```

- [ ] **Step 2: Pendekkan tiap run, pakai teknik K2**

- [ ] **Step 3: Pastikan tidak ada baris kode yang tersenggol**

```bash
git diff -U0 -- app/Http/ | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
  | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong.**

- [ ] **Step 4: Pastikan bersih, keluarkan dari antrean, turunkan plafon**

```bash
CI php tools/prosa.php $(git diff --name-only -- app/Http/ | tr '\n' ' ') | tail -1
for f in $(git diff --name-only -- app/Http/); do sed -i "\|^${f}$|d" quality-legacy.txt; done
grep -c '^app/\|^resources/' quality-legacy.txt   # harus turun 20
```

Turunkan `max.legacy.files` 180 → 160.

- [ ] **Step 5: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 939 test.

- [ ] **Step 6: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Http quality-legacy.txt quality-budget.txt
git commit -m "refactor(quality): padatkan prosa komentar di app/Http"
```

---

### Task 6: `app/Notifications/` — 9 berkas

**Files:**
- Modify: 9 berkas `app/Notifications/` di *Lampiran A*
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/prosa.php`
- Produces: 9 berkas dengan `violations()` === `[]`

- [ ] **Step 1: Lihat sasarannya**

```bash
CI php tools/prosa.php $(grep '^app/Notifications/' quality-legacy.txt | tr '\n' ' ')
```

- [ ] **Step 2: Pendekkan tiap run, pakai teknik K2**

Sembilan berkas ini run-nya pendek (4–6), jadi sebagian besar cukup K2a.

- [ ] **Step 3: Pastikan tidak ada baris kode yang tersenggol**

```bash
git diff -U0 -- app/Notifications/ | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
  | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong.**

- [ ] **Step 4: Pastikan bersih, keluarkan dari antrean, turunkan plafon**

```bash
CI php tools/prosa.php $(git diff --name-only -- app/Notifications/ | tr '\n' ' ') | tail -1
for f in $(git diff --name-only -- app/Notifications/); do sed -i "\|^${f}$|d" quality-legacy.txt; done
```

Turunkan `max.legacy.files` 160 → 151.

- [ ] **Step 5: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 939 test. `tests/Unit/Notification/` menguji payload notifikasi —
kalau ia merah, ada baris kode yang ikut terpangkas.

- [ ] **Step 6: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Notifications quality-legacy.txt quality-budget.txt
git commit -m "refactor(quality): padatkan prosa komentar di app/Notifications"
```

---

### Task 7: Sisanya — 10 berkas, lalu turunkan `max.comment.overflow`

**Files:**
- Modify: `app/Console/Commands/PruneExpiredExports.php`,
  `app/Data/Submission/SubmissionAbilitiesData.php`,
  `app/Enums/VerificationStatus.php`, `app/Jobs/GenerateExport.php`,
  `app/Listeners/SendProposalSubmittedNotification.php`,
  `app/Mail/DynamicEmailNotification.php`,
  `app/Policies/BudgetLineItemPolicy.php`,
  `app/Policies/FormSubmissionPolicy.php`,
  `app/Providers/AppServiceProvider.php`,
  `app/Services/Storage/S3StorageService.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/prosa.php`
- Produces: 10 berkas bersih; `max.legacy.files: 141`; `max.comment.overflow: 32`

- [ ] **Step 1: Lihat sasarannya**

```bash
CI php tools/prosa.php \
  app/Console/Commands/PruneExpiredExports.php \
  app/Data/Submission/SubmissionAbilitiesData.php \
  app/Enums/VerificationStatus.php \
  app/Jobs/GenerateExport.php \
  app/Listeners/SendProposalSubmittedNotification.php \
  app/Mail/DynamicEmailNotification.php \
  app/Policies/BudgetLineItemPolicy.php \
  app/Policies/FormSubmissionPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Services/Storage/S3StorageService.php
```

- [ ] **Step 2: Pendekkan tiap run, pakai teknik K2**

`AppServiceProvider` (13) dan `FormSubmissionPolicy` (10) yang terpanjang.
Policy memuat alasan wewenang — K1 berlaku keras di sana.

- [ ] **Step 3: Pastikan tidak ada baris kode yang tersenggol**

```bash
git diff -U0 -- app/ | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
  | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong.**

- [ ] **Step 4: Keluarkan dari antrean, turunkan kedua plafon**

```bash
for f in $(git diff --name-only -- app/); do sed -i "\|^${f}$|d" quality-legacy.txt; done
grep -c '^app/\|^resources/' quality-legacy.txt      # harus 141
```

Di `quality-budget.txt`: `max.legacy.files: 141`.

Lalu ukur overflow yang tersisa dan setel plafonnya ke angka itu:

```bash
CI php tools/prosa.php | grep ':' | grep -oP '^[^:]+' | sort -u | wc -l
```
Expected: `32`. Setel `max.comment.overflow: 32`.

- [ ] **Step 5: Gerbang penuh, dua-duanya**

Run: `CI composer run quality`
Expected: hijau, **939 test**.

Run: `npm run quality`
Expected: hijau.

- [ ] **Step 6: Buktikan nol perilaku berubah**

```bash
git diff -U0 <commit-sebelum-Task-2>..HEAD -- app/ | grep -E '^[+-]' \
  | grep -vE '^(\+\+\+|---)' | grep -vE '^[+-]\s*(//|\*|/\*)' | grep -vE '^[+-]\s*$'
```
Expected: **kosong** untuk seluruh batch, bukan hanya Task terakhir.

- [ ] **Step 7: Commit**

```bash
CI ./vendor/bin/pint -q
git add app quality-legacy.txt quality-budget.txt
git commit -m "refactor(quality): padatkan prosa komentar di sisa app"
```

- [ ] **Step 8: Catat hasilnya di dokumen ini**

Ubah **Status** di bagian atas jadi `selesai <tanggal>, mendarat di <hash>`, dan
tulis tabel Sebelum/Sesudah: berkas antrean 272 → 141, `max.comment.overflow`
164 → 32, test 939 → 939.

---

## Lampiran A — 131 berkas, dengan panjang run terpanjangnya

Angka dalam kurung = run prosa terpanjang saat batch ini ditulis.

### Task 2 — `app/Actions/**` (40)

```
app/Actions/Budget/DeleteBudgetLineItem.php (7)
app/Actions/Concerns/ChangesNoState.php (9)
app/Actions/Config/CountReferenceEntityUsage.php (6)
app/Actions/Config/DeleteReferenceEntity.php (4)
app/Actions/Config/ToggleReferenceEntity.php (6)
app/Actions/Export/RequestExport.php (8)
app/Actions/Form/DeleteFormPhase.php (5)
app/Actions/Form/NotifyFormChange.php (11)
app/Actions/FormSubmission/AddPartner.php (5)
app/Actions/FormSubmission/AddResearchMember.php (5)
app/Actions/FormSubmission/AddStudentMember.php (5)
app/Actions/FormSubmission/DeactivateUser.php (9)
app/Actions/FormSubmission/DeleteTargetOutput.php (6)
app/Actions/FormSubmission/RemovePartner.php (4)
app/Actions/FormSubmission/RemoveResearchMember.php (6)
app/Actions/FormSubmission/RemoveStudentMember.php (4)
app/Actions/FormSubmission/ResolveWizardSubmission.php (8)
app/Actions/FormSubmission/SaveTargetOutput.php (7)
app/Actions/FormSubmission/StartSubmission.php (4)
app/Actions/FormSubmission/TransferSubmissionOwnership.php (7)
app/Actions/FormSubmission/WithdrawSubmission.php (14)
app/Actions/Identity/DeactivateOrganization.php (11)
app/Actions/Identity/DeleteOrganization.php (10)
app/Actions/Identity/MoveUserToOrganization.php (7)
app/Actions/Identity/RejectNidnVerification.php (6)
app/Actions/Identity/RevokeInvitation.php (9)
app/Actions/Identity/VerifyNidn.php (8)
app/Actions/Monev/NotifyMonevEvaluationSubmitted.php (6)
app/Actions/Monev/OpenMonevCycle.php (8)
app/Actions/Monev/SuggestCycleReviewers.php (8)
app/Actions/Override/RevokePhaseOverride.php (4)
app/Actions/Print/PrintDocument.php (8)
app/Actions/ResearchOutput/SaveResearchOutput.php (8)
app/Actions/Review/BuildReviewerDashboard.php (11)
app/Actions/Review/EvaluateSubmissionOutcome.php (10)
app/Actions/Review/RejectSubmission.php (5)
app/Actions/Review/ReplaceReviewer.php (10)
app/Actions/Review/ResolveReviewSummary.php (10)
app/Actions/Scheme/DeactivateScheme.php (10)
app/Actions/Scheme/SaveScheme.php (7)
```

### Task 3 — `app/Models/` (17)

```
app/Models/AuditEntry.php (7)
app/Models/ExportJob.php (4)
app/Models/Form.php (6)
app/Models/FormField.php (7)
app/Models/FormPhase.php (4)
app/Models/FormSubmissionOverride.php (4)
app/Models/InvitationToken.php (6)
app/Models/OutputTypeDefinition.php (4)
app/Models/Partner.php (5)
app/Models/ResearchOutput.php (6)
app/Models/Reviewer.php (5)
app/Models/Scheme.php (4)
app/Models/StudentMember.php (5)
app/Models/SubmissionDate.php (5)
app/Models/SubmissionTargetOutput.php (5)
app/Models/User.php (5)
app/Models/UserProfile.php (5)
```

### Task 4 — `app/Support/**` (35)

```
app/Support/AccountStateGuard.php (13)
app/Support/AffectedDraftOwners.php (11)
app/Support/BudgetLimitGuard.php (6)
app/Support/ConflictOfInterestGuard.php (11)
app/Support/EmailDomainGuard.php (12)
app/Support/MonevAccessGate.php (11)
app/Support/OptimisticLock.php (8)
app/Support/PhaseAccessGate.php (13)
app/Support/RequiredFieldGuard.php (5)
app/Support/ResearchOutputGuard.php (12)
app/Support/ReviewSummaryGuard.php (8)
app/Support/ReviewerWorkload.php (7)
app/Support/SchemeFieldGuard.php (6)
app/Support/ScoreVisibility.php (11)
app/Support/SubmissionPeriodGate.php (10)
app/Support/WizardStepGate.php (11)
app/Support/Audit/AppendOnlyViolation.php (4)
app/Support/Audit/AuditDisabledException.php (4)
app/Support/Audit/AuditScope.php (9)
app/Support/Bdd/BusinessRuleCatalog.php (4)
app/Support/Config/ReferenceEntity.php (6)
app/Support/Inertia/RenderedPageCatalog.php (5)
app/Support/Lifecycle/DeletionNotPermitted.php (10)
app/Support/Lifecycle/ReferenceGuard.php (9)
app/Support/Notification/ChannelNotDeclared.php (6)
app/Support/Notification/Deliver.php (13)
app/Support/Notification/NotificationChannels.php (6)
app/Support/Notification/NotificationEventCatalog.php (6)
app/Support/Reporting/DocumentWriter.php (4)
app/Support/Reporting/ExportRetention.php (8)
app/Support/Reporting/ExportThreshold.php (6)
app/Support/Reporting/ExportType.php (4)
app/Support/Reporting/ExportWriter.php (4)
app/Support/Reporting/PrintableDocument.php (7)
app/Support/Reporting/SubmissionVisibility.php (9)
```

### Task 5 — `app/Http/**` (20)

```
app/Http/Controllers/OrganizationController.php (4)
app/Http/Controllers/SchemeController.php (4)
app/Http/Controllers/SubmissionWithdrawalController.php (5)
app/Http/Controllers/Audit/AuditLogController.php (8)
app/Http/Controllers/Auth/AuthenticatedSessionController.php (5)
app/Http/Controllers/Auth/RegisteredUserController.php (8)
app/Http/Controllers/Config/ReferenceEntityController.php (5)
app/Http/Controllers/Export/DocumentPrintController.php (4)
app/Http/Controllers/Identity/NidnVerificationController.php (5)
app/Http/Controllers/Identity/UserAccessController.php (5)
app/Http/Controllers/Monev/MonevCycleController.php (4)
app/Http/Controllers/Notification/NotificationChannelController.php (5)
app/Http/Controllers/Notification/NotificationController.php (9)
app/Http/Controllers/Review/ReviewSummaryResolutionController.php (4)
app/Http/Controllers/Submission/BudgetLineItemController.php (5)
app/Http/Controllers/Submission/PhaseOverrideController.php (5)
app/Http/Controllers/Submission/ResearchOutputController.php (5)
app/Http/Controllers/Submission/SubmissionDecisionController.php (5)
app/Http/Middleware/HandleInertiaRequests.php (4)
app/Http/Requests/Auth/LoginRequest.php (6)
```

### Task 6 — `app/Notifications/` (9)

```
app/Notifications/ExportCompletedNotification.php (4)
app/Notifications/ExportFailedNotification.php (4)
app/Notifications/FormChangedNotification.php (4)
app/Notifications/MonevEvaluationSubmittedNotification.php (5)
app/Notifications/MonevStageOpenedNotification.php (4)
app/Notifications/NewRegistrationAwaitingVerification.php (4)
app/Notifications/NidnVerificationRejectedNotification.php (4)
app/Notifications/PhaseOverrideGrantedNotification.php (6)
app/Notifications/SubmissionDeadlineExtendedNotification.php (4)
```

### Task 7 — sisanya (10)

```
app/Console/Commands/PruneExpiredExports.php (4)
app/Data/Submission/SubmissionAbilitiesData.php (5)
app/Enums/VerificationStatus.php (9)
app/Jobs/GenerateExport.php (8)
app/Listeners/SendProposalSubmittedNotification.php (8)
app/Mail/DynamicEmailNotification.php (5)
app/Policies/BudgetLineItemPolicy.php (4)
app/Policies/FormSubmissionPolicy.php (10)
app/Providers/AppServiceProvider.php (13)
app/Services/Storage/S3StorageService.php (5)
```
