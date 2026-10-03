# Batch PHP non-controller — 20 berkas keluar antrean

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans`. Langkahnya memakai checkbox (`- [ ]`).
>
> **Dokumen ini spec sekaligus rencana.** Baca *Keputusan yang sudah diambil* —
> terutama **K1** — sebelum menyentuh berkas pertama. K1 menutup satu jalan pintas
> yang membuat seluruh batch ini lulus gerbang tanpa memperbaiki apa pun.

**Goal:** Mengeluarkan **20 berkas PHP** dari `quality-legacy.txt` lewat Extract
Method, Introduce Parameter Object, dan penyatuan satu kueri kembar — tanpa
mengubah perilaku.

**Architecture:** Satu Task = satu area = satu commit = satu gerbang. Hampir setiap
berkas di sini punya pola yang sama: prosa komentar sisa (yang tidak ikut batch
prosa karena berkasnya punya pelanggaran kedua) **plus satu method yang sedikit
kelewat** — 41–74 baris dari ambang 40. Obatnya Extract Method pada seam yang
sudah dipetakan di tiap Task.

**Tech Stack:** PHP 8.4 · Pest · PHPStan tier strict level 10 · `App\Support\Quality\HardLimits`

**Spec induk:** [`docs/design/refactor-plan.md`](refactor-plan.md) §2.3, §2.5, §5 gelombang 2 dan 4

**Pendahulu:** [`refactor-plan-batch-prosa-komentar.md`](refactor-plan-batch-prosa-komentar.md) —
aturan K1–K4 di sana berlaku untuk setiap pemadatan prosa di batch ini.

**Status:** belum dikerjakan.

---

## Kenapa batch ini, dan kenapa bukan dedup Vue

Antrean berisi **141 berkas**: 42 PHP, 99 Vue. Batch berikutnya yang semula
diusulkan — 31 berkas hanya-duplikat — **seluruhnya Vue legacy**, dan pemilik repo
memastikan (3 Oktober 2026) layar SIMPAS v2 sedang menggantikannya. Berkas itu
keluar antrean lewat **penghapusan**, bukan refactor. Mendedupnya berarti merapikan
halaman yang akan dibuang, dengan jaring pengaman tiga test Vitest.

PHP sebaliknya: kodenya permanen, dan dua putaran terakhir membuktikan di sanalah bug
aturan bisnis bersembunyi.

| Kelompok 42 berkas PHP | Berkas | Nasib |
| ---------------------- | -----: | ----- |
| Action, Support, Query, command — 2–3 pelanggaran | **20** | **batch ini** |
| Controller | 17 | gelombang 3, rencana tersendiri |
| Ditunda dengan alasan (lihat *Yang tidak dikerjakan*) | 5 | — |

---

## Lingkup

| Task | Area | Berkas | Teknik utama |
| ---- | ---- | -----: | ------------ |
| 1 | Alat ukur | — | `tools/limits.php` |
| 2 | `AuditTrail` | 1 | Introduce Parameter Object (`AuditChange`) — menyentuh 23 pemanggil |
| 3 | `Actions/Identity` | 5 | Extract Method |
| 4 | `Actions/FormSubmission` | 3 | Extract Method |
| 5 | `Actions/Budget` + `GrantPhaseOverride` | 3 | Extract Method · Introduce Parameter Object |
| 6 | Periode | 3 | Satukan kueri kembar ke `Queries/` · Extract Method |
| 7 | Config, Scheme, Review | 4 | Extract Method · Decompose Data Table |
| 8 | `NotifyActiveSubmissionPeriods` | 1 | Characterization test · Extract Query |
| 9 | Penutup | — | plafon akhir, catat hasil |

Antrean **141 → 121**.

---

## Keputusan yang sudah diambil

Kalau salah satunya terasa salah saat dikerjakan, hentikan dan bawa ke pemilik repo.

### K1 — Ekstrak dulu, padatkan prosa kemudian. Method lolos hanya dengan komentarnya utuh.

`MethodMetric::loc` dihitung dari kurung buka sampai kurung tutup — **komentar dan
baris kosong di dalam method ikut terhitung**. Akibatnya beberapa `handle()` di
batch ini (41, 42, 47 baris) bisa "lolos" hanya dengan menggerus komentarnya.
Itu jalan pintas yang **dilarang**: hasilnya method yang tetap terlalu panjang,
tapi kini tanpa alasan yang menjelaskan isinya.

Karena itu urutannya mengikat:

1. Extract Method sampai method itu **≤ 40 baris dengan komentarnya belum disentuh**.
2. **Ukur** — lihat Step "Buktikan method lolos dengan komentar utuh" di tiap Task.
3. Baru kemudian padatkan prosa, untuk pelanggaran prosa, dengan aturan K2.

Komentar yang menempel pada blok yang diekstrak **ikut pindah** ke method barunya.
Ia menjelaskan kode itu, jadi ia tinggal bersama kode itu.

**Karena itu Task 3–8 commit dua kali.** Commit pertama hanya ekstraksi — komentar
belum disentuh, berkasnya masih di antrean karena pelanggaran prosanya. Commit kedua
memadatkan prosa dan mengeluarkan berkasnya. Pemisahan ini yang membuat K1 bisa
**dibuktikan dari riwayat git** di Task 9, bukan sekadar dijanjikan. Keadaan di antara
keduanya hijau: berkas yang masih terdaftar dikecualikan assertion 1, dan assertion 2
hanya menolak berkas terdaftar yang sudah **bersih**.

### K2 — Pemadatan prosa ikut aturan batch prosa, plus dua jebakan yang sudah terbukti

K1–K4 di [`refactor-plan-batch-prosa-komentar.md`](refactor-plan-batch-prosa-komentar.md)
berlaku penuh: padatkan atau dekatkan, jangan hapus; kode BR wajib bertahan; anotasi
PHPStan tidak disentuh. Batch prosa lalu menemukan dua kegagalan yang **lolos semua
pemeriksaan mesin**:

**(a) Pemadatan menyimpan alasan, lalu membuang fakta yang membuat alasan itu sah.**
Empat kasus lolos run-length, kepadatan, dan kode BR, dan baru tertangkap manusia
yang membandingkan teks terhapus dengan yang tersisa. Sebelum memotong kalimat yang
menyatakan **fakta tentang dunia** — bukan tentang kode — periksa apakah fakta itu
tercatat di tempat lain. Dokumen desain tidak dihitung.

Berkas di batch ini padat fakta semacam itu. Contoh yang **tidak boleh hilang**:
`SubmitFormSubmission` — kenapa SAVEPOINT diperlukan (23505 membatalkan seluruh
transaksi PostgreSQL, lalu kueri pemulihan gagal 25P02); `RegisterByInvitation` —
bahwa permission additive adalah **default library Spatie**, bukan logika aplikasi;
`AppointReviewer` — bahwa default satu tahun adalah masa jabatan lazim LPPM.

**(b) Ukur kepadatan dalam karakter, bukan byte.** Prosa ini penuh em-dash
(3 byte). `wc -c` melaporkan pembungkusan ulang yang menukar em-dash dengan koma
sebagai "pemadatan". Pakai `wc -m`.

### K3 — `AuditTrail::record()` menerima `AuditChange`, bukan `$old` dan `$new` terpisah

```php
AuditTrail::record($description, $subject, $causer, new AuditChange(old: [...], new: [...]));
```

Nilai lama dan baru selalu bepergian bersama ke layar audit; memisahkannya jadi dua
parameter adalah Data Clumps. Konstruktor dengan argumen bernama sudah cukup terbaca —
**jangan** menambahkan `AuditChange::created()` dan sejenisnya (YAGNI).

Dua `mixed` di docblock `record()` pindah ke docblock konstruktor `AuditChange`.
Jumlahnya tidak berubah; komentar di `quality-budget.txt` yang menunjuk lokasinya
yang diperbarui.

### K4 — Kueri rujukan periode disatukan; guard di Action **tidak** dibuang

`DeleteSubmissionPeriod::handle()` dan hook `deleting` di `SubmissionPeriod::booted()`
menjalankan `ReferenceGuard` dengan kueri yang sama persis. Membuang guard di Action
terlihat seperti dedup yang benar, dan **salah**: Action mencatat audit **sebelum**
`delete()` memicu hook. Tanpa guard-nya sendiri, penghapusan yang ditolak hook tetap
meninggalkan entri "Periode pengajuan dihapus" untuk kejadian yang tidak pernah ada.

Yang disatukan kuerinya, ke `Queries/Period/SubmissionPeriodReferences`. Kedua
pemanggil tetap ada.

### K5 — Bentuk method hasil ekstraksi

- `private`, bertipe penuh, nama berupa niat: `guardIssuable()`, `expiresAt()`,
  `createAccount()` — bukan `step1()` atau `helper()`.
- Kalau method asal punya docblock array-shape (`@param array{...} $attributes`),
  **salin persis** ke method baru yang menerimanya. Tier strict level 10 menolak
  `array` telanjang.
- Satu method satu kerja. Nama yang butuh "dan" berarti dua method.
- Dilarang `@phpstan-ignore`, baseline, `assert()`, inline `@var`, atau cast untuk
  meredam galat (AGENTS.md aturan 5).

---

## Global Constraints

- **Nol perubahan perilaku.** Jumlah test hanya bertambah oleh test baru yang
  disebut di Task 2 (+1) dan Task 8 (+6). Test lama tidak ada yang diubah kecuali
  pemanggilnya ikut berganti signature (Task 2, Task 5).
- **Identifier bahasa Inggris, prosa komentar bahasa Indonesia.**
- **`pending.txt` dan `uncovered.txt` tidak disentuh.**
- **Berkas yang jadi bersih WAJIB dikeluarkan dari `quality-legacy.txt` dan
  `max.legacy.files` diturunkan di Task yang sama.** Assertion 2 `QualityBudgetTest`
  gagal kalau tidak.
- **Pesan commit: satu baris, tanpa badan, tanpa trailer.**
- **Database test:** container `simpas_dev_pg` (PostgreSQL di port 55432). Kalau
  Pest gagal dengan `Connection refused`, nyalakan dulu:
  `docker start simpas_dev_pg` — itu kegagalan lingkungan, bukan kode.
- **Container PHP** (host bukan 8.4), disingkat **`CI`**:
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```

---

## Risiko

**Yang terbesar: Task 2 menyentuh 23 berkas, banyak di antaranya sudah keluar
antrean.** Membungkus `$old, $new` jadi `new AuditChange(...)` bisa memperpanjang
method yang tadinya pas 40 baris. Assertion 1 menangkapnya. PHPStan menangkap setiap
pemanggil yang terlewat — `array` tidak bisa masuk ke parameter `?AuditChange`.

**Yang paling mungkin terlewat: K1.** Mengecilkan komentar jauh lebih cepat daripada
mengekstrak method, dan gerbang tidak bisa membedakan keduanya. Step pembuktian di
tiap Task ada untuk ini.

**Yang paling mungkin salah kaprah: K4.** Membuang guard ganda terasa benar.

**Yang tidak berisiko seperti dugaan:** Extract Method memindahkan baris apa adanya.
Setiap Action di Task 3–7 sudah punya minimal satu berkas test; satu-satunya yang
tidak — command di Task 8 — dapat test karakterisasi lebih dulu.

---

## Definisi selesai

- [ ] `CI composer run quality` hijau
- [ ] `npm run quality` hijau
- [ ] 20 berkas di *Lingkup* keluar dari `quality-legacy.txt`; `max.legacy.files: 121`
- [ ] Jumlah test **946** (939 + 7)
- [ ] Tidak ada method di 20 berkas itu yang lolos ambang hanya karena komentarnya dipangkas (K1)
- [ ] Tiap BR, ID skenario, dan fakta latar yang disebut K2(a) masih ada
- [ ] `max.mixed` tetap **20**

---

## Yang tidak dikerjakan dokumen ini

| Berkas | Kenapa ditunda |
| ------ | -------------- |
| `app/Models/FormSubmission.php` | Pengecualian terdokumentasi — lantainya 215 baris kode (refactor-plan.md §5) |
| `app/Models/FormPhaseDetail.php` | Lewat ukuran berkas (207). Butuh sensus K2 model, bukan Extract Method — rencananya sendiri |
| `app/Models/Organization.php` | Sama (221). Sudah ditandai "menunggu rencananya sendiri" sejak gelombang 2 |
| `app/Services/EmailNotificationService.php` | 396 baris, **nol test**. AGENTS.md menuntut notifikasi lewat Laravel Notification, bukan Mail custom — nasibnya migrasi, bukan dirapikan. Dua method-nya sudah nol pemanggil: `notifyAdminFormSubmission()`, dan `notifySubmissionPeriodActive()` yang sudah digantikan command di Task 8 |
| `app/Http/Middleware/CheckBiodata.php` | Lewat ukuran (215), **nol test**, dan alur biodatanya terkait layar yang sedang diganti v2 |
| 17 controller | Gelombang 3 |
| 99 berkas Vue | Diganti layar SIMPAS v2 |

---
---

# Rencana Implementasi

**Goal:** 20 berkas PHP keluar antrean; antrean 141 → 121.

**Architecture:** Satu Task = satu area = satu commit = satu gerbang. Task 2 lebih dulu
karena mengubah signature yang dipakai banyak berkas di Task 3–7.

**Spec:** bagian atas dokumen ini.

## Global Constraints

Seluruh *Global Constraints* dan K1–K5 di atas berlaku untuk **setiap** Task.

---

### Task 1: Alat ukur per berkas

`tools/prosa.php` dari batch lalu hanya mengukur prosa. Batch ini butuh pelanggaran
lengkap dan angka method-nya, untuk berkas yang disebut saja.

**Files:**
- Create: `tools/limits.php`

**Interfaces:**
- Produces: `php tools/limits.php <path...>` → per berkas: daftar pelanggaran
  `HardLimits` dan baris `nama() loc=.. cc=.. params=.. returns=..` untuk method yang
  melewati ambang. Diakhiri `BERSIH` atau `n berkas masih melanggar`.

`tools/` di luar korpus terukur, jadi berkas ini tidak ikut diukur pagar.

- [ ] **Step 1: Tulis alatnya**

```php
<?php

// tools/limits.php — pelanggaran HardLimits untuk berkas yang disebut. Di luar korpus terukur.

require __DIR__.'/../vendor/autoload.php';

use App\Support\Quality\BudgetFile;
use App\Support\Quality\CodeMetrics;
use App\Support\Quality\HardLimits;
use App\Support\Quality\SourceTree;

$root = dirname(__DIR__);
$budget = new BudgetFile("{$root}/quality-budget.txt");
$limits = new HardLimits(
    $budget,
    array_map(static fn (string $p): string => "{$root}/{$p}", SourceTree::measured($root)),
);

$masihMelanggar = 0;

foreach (array_slice($argv, 1) as $path) {
    $violations = $limits->violations("{$root}/{$path}");
    echo $path.($violations === [] ? '  ✓' : '')."\n";

    foreach ($violations as $v) {
        echo "   ✗ {$v}\n";
    }

    foreach (CodeMetrics::forFile("{$root}/{$path}")['methods'] as $m) {
        $lewat = $m->loc > $budget->value('hard.method.loc')
            || $m->complexity > $budget->value('hard.method.cc')
            || $m->returns > $budget->value('hard.method.returns')
            || ($m->parameters > $budget->value('hard.method.params') && !$m->onlyPromotesProperties());

        if ($lewat) {
            printf("      %s() loc=%d cc=%d params=%d returns=%d\n", $m->name, $m->loc, $m->complexity, $m->parameters, $m->returns);
        }
    }

    $masihMelanggar += $violations === [] ? 0 : 1;
}

echo $masihMelanggar === 0 ? "\nBERSIH\n" : "\n{$masihMelanggar} berkas masih melanggar\n";
```

- [ ] **Step 2: Jalankan, pastikan angkanya cocok dengan pengukuran batch ini**

Run: `CI php tools/limits.php app/Actions/Identity/IssueInvitation.php app/Support/Audit/AuditTrail.php`

Expected:
```
app/Actions/Identity/IssueInvitation.php
   ✗ prosa komentar terlalu panjang
   ✗ handle() terlalu panjang
   ✗ handle() terlalu kompleks
      handle() loc=61 cc=10 params=3 returns=1
app/Support/Audit/AuditTrail.php
   ✗ prosa komentar terlalu panjang
   ✗ record() terlalu banyak parameter
      record() loc=27 cc=5 params=5 returns=1

2 berkas masih melanggar
```

Kalau angkanya berbeda, kode berkas itu sudah berubah sejak dokumen ini ditulis —
baca ulang seam di Task-nya sebelum mengikutinya.

- [ ] **Step 3: Commit**

```bash
git add tools/limits.php
git commit -m "chore(quality): alat laporan pelanggaran per berkas"
```

---

### Task 2: `AuditChange` — Introduce Parameter Object untuk `AuditTrail::record()`

**Files:**
- Create: `app/Support/Audit/AuditChange.php`
- Modify: `app/Support/Audit/AuditTrail.php`
- Modify: 23 pemanggil (daftar di Step 4)
- Modify: `tests/Feature/Audit/AuditTrailTest.php`
- Modify: `quality-budget.txt`, `quality-legacy.txt`

**Interfaces:**
- Produces: `App\Support\Audit\AuditChange` dengan konstruktor
  `(?array $old = null, ?array $new = null)`;
  `AuditTrail::record(string $description, Model $subject, ?Model $causer = null, ?AuditChange $change = null): Activity`.
  Task 3–7 memakai bentuk ini.

**Sebelum Step 1 — catat titik awal batch.** Task 9 membuktikan K1 dari riwayat
git mulai titik ini, jadi hash-nya dicatat sekarang, bukan ditebak nanti:

```bash
git rev-parse --short HEAD    # catat sebagai BASE
```

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Audit/AuditTrailTest.php`, beserta
`use App\Support\Audit\AuditChange;` di blok impor:

```php
it('mencatat nilai lama dan baru lewat AuditChange', function () {
    $budi = User::factory()->create();

    $entry = AuditTrail::record('Skema dipilih', auditedSubmission($budi), $budi, new AuditChange(
        old: ['scheme_id' => 1],
        new: ['scheme_id' => 2],
    ));

    expect($entry->properties['old'])->toBe(['scheme_id' => 1])
        ->and($entry->properties['new'])->toBe(['scheme_id' => 2]);
});
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `CI ./vendor/bin/pest tests/Feature/Audit/AuditTrailTest.php --filter=AuditChange`
Expected: FAIL — `Class "App\Support\Audit\AuditChange" not found`.

- [ ] **Step 3: Tulis `AuditChange`**

```php
<?php

namespace App\Support\Audit;

/**
 * BR-RPT-09 — nilai lama dan baru satu perubahan. Keduanya selalu bepergian
 * bersama ke layar audit, jadi mereka satu parameter, bukan dua.
 */
final class AuditChange
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __construct(
        public readonly ?array $old = null,
        public readonly ?array $new = null,
    ) {}
}
```

- [ ] **Step 4: Ubah `record()`, lalu alihkan seluruh pemanggilnya**

Di `AuditTrail.php`, ganti dua parameter terakhir dan pembacaannya:

```php
    public static function record(
        string $description,
        Model $subject,
        ?Model $causer = null,
        ?AuditChange $change = null,
    ): Activity {
        $properties = [];

        if ($change?->old !== null) {
            $properties['old'] = $change->old;
        }

        if ($change?->new !== null) {
            $properties['new'] = $change->new;
        }
```

Hapus docblock `@param ... $old` / `$new` di atas `record()` — tipenya kini milik
`AuditChange`. Penyempitan `$change?->old !== null` sudah diuji lolos tier strict.

Lalu ubah tiap pemanggil yang membawa argumen ke-4 atau ke-5. Polanya:

| Sebelum | Sesudah |
| ------- | ------- |
| `record($d, $s, $c, null, [...])` | `record($d, $s, $c, new AuditChange(new: [...]))` |
| `record($d, $s, $c, [...], null)` | `record($d, $s, $c, new AuditChange(old: [...]))` |
| `record($d, $s, $c, $before, [...])` | `record($d, $s, $c, new AuditChange($before, [...]))` |

Daftarnya — 23 berkas, didapat dengan pencarian multi-baris karena banyak pemanggil
memecah argumennya ke beberapa baris:

```
app/Actions/Budget/DeleteBudgetLineItem.php
app/Actions/Budget/SaveBudgetLineItem.php
app/Actions/Budget/UpdateBudgetLineItem.php
app/Actions/Config/DeleteReferenceEntity.php
app/Actions/Config/SaveReferenceEntity.php
app/Actions/Config/ToggleReferenceEntity.php
app/Actions/Export/RequestExport.php
app/Actions/Form/DeleteFormPhase.php
app/Actions/FormSubmission/AddPartner.php
app/Actions/FormSubmission/AddStudentMember.php
app/Actions/FormSubmission/DeleteTargetOutput.php
app/Actions/FormSubmission/RemovePartner.php
app/Actions/FormSubmission/RemoveResearchMember.php
app/Actions/FormSubmission/RemoveStudentMember.php
app/Actions/Identity/IssueInvitation.php
app/Actions/Identity/MoveUserToOrganization.php
app/Actions/Identity/RevokeInvitation.php
app/Actions/Identity/SaveOrganization.php
app/Actions/Period/DeleteSubmissionPeriod.php
app/Actions/Print/PrintDocument.php
app/Actions/Scheme/SyncSchemeSelection.php
app/Console/Commands/PruneExpiredExports.php
app/Http/Controllers/Notification/NotificationChannelController.php
```

Tambahkan `use App\Support\Audit\AuditChange;` di tiap berkas yang memakainya.

- [ ] **Step 5: Biarkan PHPStan menemukan yang terlewat**

Run: `CI composer run analyse:strict` lalu `CI composer run analyse`
Expected: `[OK] No errors` di keduanya. Galat berbunyi *expects AuditChange|null,
array given* menunjuk pemanggil yang belum diubah — ubah, ulangi.

- [ ] **Step 6: Jalankan test-nya, pastikan LULUS**

Run: `CI ./vendor/bin/pest tests/Feature/Audit tests/Unit/Audit`
Expected: hijau, termasuk test Step 1.

- [ ] **Step 7: Padatkan prosa `AuditTrail.php`**

Docblock kelasnya run 8. Pertahankan dua fakta: bentuk `old`/`new` seragam karena
layar audit membacanya, dan aksi scheduler memang tidak punya aktor — yang tidak
boleh ditambal aktor palsu. Ikuti K2.

- [ ] **Step 8: Perbarui komentar `max.mixed` di `quality-budget.txt`**

Baris:
```
#    2  parameter AuditTrail::record ($old, $new)
```
jadi:
```
#    2  konstruktor AuditChange ($old, $new)
```
`max.mixed` tetap **20**.

- [ ] **Step 9: Keluarkan dari antrean**

```bash
CI php tools/limits.php app/Support/Audit/AuditTrail.php      # harus BERSIH
sed -i '\|^app/Support/Audit/AuditTrail\.php$|d' quality-legacy.txt
```
Setel `max.legacy.files: 140`.

- [ ] **Step 10: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, **940 test**. Kalau assertion 1 merah untuk berkas yang tadinya
bersih, membungkus argumen membuat method-nya kelewat 40 — ekstrak, jangan padatkan
komentarnya (K1).

- [ ] **Step 11: Commit**

```bash
CI ./vendor/bin/pint -q
git add app tests quality-budget.txt quality-legacy.txt
git commit -m "refactor(audit): satukan nilai lama dan baru jadi AuditChange"
```

---

### Task 3: `Actions/Identity` — 5 berkas

**Files:**
- Modify: `app/Actions/Identity/IssueInvitation.php`, `SaveOrganization.php`,
  `AppointReviewer.php`, `RegisterByInvitation.php`, `RegisterResearcher.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `AuditChange` (Task 2), `tools/limits.php` (Task 1)
- Produces: tidak ada method publik baru — seluruh ekstraksi `private`.

**Seam.** Nomor baris dihitung **sebelum Task 2**; Task 2 menggeser sedikit baris di
`IssueInvitation` dan `SaveOrganization`. Cari blok berdasarkan isinya, bukan angkanya.

| Berkas · method | Sekarang | Ekstrak jadi | Sisa ± |
| --------------- | -------- | ------------ | -----: |
| `IssueInvitation::handle` | 61 · cc 10 | `guardIssuable(User $actor, Organization $organization): void` — cek `users.manage` dan `is_active` (bar. 36–47) · `maxUses(array $attributes): ?int` (bar. 49–55) · `expiresAt(array $attributes): ?Carbon` (bar. 57–68) | 30 |
| `SaveOrganization::handle` | 42 · cc 10 | `create(User $actor, array $attributes, ?int $parentId): Organization` — cabang `$organization === null` (bar. 49–63) · `update(User $actor, Organization $organization, array $attributes, ?int $parentId): Organization` — sisanya (bar. 65–79) | 15 |
| `AppointReviewer::handle` | 61 · cc 8 | `guardAppointable(User $user, string $type): void` (bar. 53–65) · `persist(User $user, string $type, ?string $startDate, ?string $endDate): Reviewer` — **isi** closure transaksi (bar. 68–100), dipanggil dari dalam `DB::transaction` | 22 |
| `RegisterByInvitation::handle` | 42 | `createAccount(InvitationToken $token, array $data): User` — isi closure transaksi (bar. 51–76) | 20 |
| `RegisterResearcher::handle` | 59 | `guardRegistrable(array $data): Organization` — tiga guard, mengembalikan organisasi aktif yang sudah dicari (bar. 46–68) · `createAccount(array $data, Organization $organization): User` — isi closure transaksi (bar. 71–86) | 22 |

`array $attributes` / `array $data` di method baru wajib membawa docblock array-shape
yang sama dengan `handle()` (K5).

**Contoh kerja — `IssueInvitation`.** Bentuk setelah Task 3 (badan `create` dan
pesan galat disingkat `…` di sini; di berkas tetap utuh):

```php
    public function handle(User $actor, Organization $organization, array $attributes = []): InvitationToken
    {
        $this->guardIssuable($actor, $organization);

        $maxUses = $this->maxUses($attributes);
        $expiresAt = $this->expiresAt($attributes);

        $tercatat = InvitationToken::create([…]);

        // BR-RPT-09 — token-nya sendiri tidak ikut dicatat. Jejak audit dibaca
        // lebih banyak orang daripada yang berhak memakai undangannya.
        AuditTrail::record('Undangan registrasi diterbitkan', $tercatat, $actor, new AuditChange(new: […]));

        return $tercatat;
    }

    private function guardIssuable(User $actor, Organization $organization): void
    {
        if (!$actor->can('users.manage')) {
            throw new AuthorizationException('Hanya Operator yang dapat menerbitkan undangan registrasi.');
        }

        // BR-IAM-06 dan BR-IAM-14 — menerbitkan undangan ke unit yang sudah
        // dipensiunkan melahirkan token yang tidak pernah bisa dipakai. Ditolak
        // di sini supaya kegagalannya terlihat penerbitnya, bukan pendaftarnya.
        if (!$organization->is_active) {
            throw ValidationException::withMessages(['organization_id' => '…']);
        }
    }

    /** @param  array{permissions?: list<string>, max_uses?: int|null, expires_at?: string|null}  $attributes */
    private function maxUses(array $attributes): ?int
    {
        $maxUses = $attributes['max_uses'] ?? null;

        if ($maxUses !== null && $maxUses < 1) {
            throw ValidationException::withMessages(['max_uses' => 'Kuota pemakaian minimal satu.']);
        }

        return $maxUses;
    }
```

Perhatikan komentar BR-IAM-06/14 **ikut pindah** bersama `if` yang dijelaskannya (K1).
`expiresAt()` mengikuti bentuk `maxUses()`, membawa komentar `isset()` bersamanya.

- [ ] **Step 1: Catat titik awal**

```bash
CI php tools/limits.php app/Actions/Identity/IssueInvitation.php app/Actions/Identity/SaveOrganization.php \
  app/Actions/Identity/AppointReviewer.php app/Actions/Identity/RegisterByInvitation.php \
  app/Actions/Identity/RegisterResearcher.php
CI ./vendor/bin/pest tests/Feature/Identity
```
Expected: 5 berkas melanggar; test hijau. Catat jumlah test-nya.

- [ ] **Step 2: Extract Method sesuai tabel seam**

Pindahkan baris apa adanya. Jangan menyunting komentar di langkah ini.

- [ ] **Step 3: Buktikan method lolos dengan komentar utuh (K1)**

```bash
git diff -U0 -- app/Actions/Identity/ | grep -E '^-\s*(//|\*|/\*)' | head
```
Expected: **kosong** — belum ada baris komentar yang dihapus. Lalu:

```bash
CI php tools/limits.php app/Actions/Identity/*.php | grep -E 'terlalu (panjang|kompleks)'
```
Expected: **kosong.** Yang boleh tersisa hanya `prosa komentar terlalu panjang`.

- [ ] **Step 4: Jalankan test-nya**

Run: `CI ./vendor/bin/pest tests/Feature/Identity`
Expected: hijau, jumlah sama dengan Step 1.

- [ ] **Step 5: Commit ekstraksi — komentar belum disentuh**

```bash
CI composer run analyse:strict
CI ./vendor/bin/pint -q
git add app/Actions/Identity
git commit -m "refactor(identity): ekstrak guard dan pembuatan akun dari lima handle"
```

Berkasnya sengaja masih di antrean: pelanggaran prosanya belum dibereskan.
Commit ini yang dibaca Task 9 untuk membuktikan K1.

- [ ] **Step 6: Padatkan prosa (K2)**

Jangan hilangkan: di `RegisterByInvitation`, bahwa permission diberikan langsung
**karena** sifat additive-nya adalah default Spatie (BR-IAM-09); di `AppointReviewer`,
bahwa satu tahun adalah masa jabatan lazim di LPPM dan Operator tetap bisa
menyuntingnya; di `RegisterResearcher`, bahwa `permission()` Spatie melempar bila
permission-nya belum ada (alasan BR-NOT-03 fire-and-forget).

- [ ] **Step 7: Bersih, keluarkan dari antrean**

```bash
CI php tools/limits.php app/Actions/Identity/IssueInvitation.php app/Actions/Identity/SaveOrganization.php \
  app/Actions/Identity/AppointReviewer.php app/Actions/Identity/RegisterByInvitation.php \
  app/Actions/Identity/RegisterResearcher.php | tail -1          # BERSIH
for f in IssueInvitation SaveOrganization AppointReviewer RegisterByInvitation RegisterResearcher; do
  sed -i "\|^app/Actions/Identity/${f}\.php$|d" quality-legacy.txt
done
```
Setel `max.legacy.files: 135`.

- [ ] **Step 8: Gerbang penuh**

Run: `CI composer run quality`
Expected: hijau, 940 test.

- [ ] **Step 9: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Actions/Identity quality-legacy.txt quality-budget.txt
git commit -m "refactor(identity): padatkan prosa lima Action dan keluarkan dari antrean"
```

---

### Task 4: `Actions/FormSubmission` — 3 berkas

**Files:**
- Modify: `app/Actions/FormSubmission/ResubmitFormSubmission.php`,
  `StartChildSubmission.php`, `SubmitFormSubmission.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Consumes: `tools/limits.php`
- Produces: ekstraksi `private` saja.

| Berkas · method | Sekarang | Ekstrak jadi | Sisa ± |
| --------------- | -------- | ------------ | -----: |
| `ResubmitFormSubmission::handle` | 47 | `guardResubmittable(FormSubmission $submission, User $user): SubmissionStatus` — cek `can('resubmit')` dan status non-null, mengembalikan status (bar. 25–39) · `returnToReviewQueue(FormSubmission $submission): void` — blok "reviewer masih terpasang" beserta komentar BR-SM-03 dan ADR 0006-nya (bar. 50–63) | 22 |
| `StartChildSubmission::handle` | 41 | `accessControlOf(FormPhaseDetail $detail): FormAccessControl` — ambil `formAccessControl`, lempar bila null (bar. 52–58) | 35 |
| `SubmitFormSubmission::handle` | 74 | `guardSubmittable(FormSubmission $submission, User $user): SubmissionStatus` — cek `can('submit')`, status non-null (bar. 31–48) · `markSubmitted(FormSubmission $submission): void` — **seluruh** blok `try`/`catch` beserta komentar BR-SM-16, SAVEPOINT, dan `errorInfo[0]` (bar. 61–88) | 32 |

`SubmitFormSubmission` sudah punya `guardActiveSubmissionUniqueness()`,
`activeSubmissionClash()`, dan `activeSubmissionRejection()` — `markSubmitted()`
memanggil dua yang terakhir persis seperti `handle()` sekarang.

- [ ] **Step 1: Catat titik awal**

```bash
CI php tools/limits.php app/Actions/FormSubmission/ResubmitFormSubmission.php \
  app/Actions/FormSubmission/StartChildSubmission.php app/Actions/FormSubmission/SubmitFormSubmission.php
CI ./vendor/bin/pest --filter='Submit|Resubmit|Monev|ChildSubmission'
```

- [ ] **Step 2: Extract Method sesuai tabel seam**

- [ ] **Step 3: Buktikan method lolos dengan komentar utuh (K1)**

```bash
git diff -U0 -- app/Actions/FormSubmission/ | grep -E '^-\s*(//|\*|/\*)' | head    # kosong
CI php tools/limits.php app/Actions/FormSubmission/ResubmitFormSubmission.php \
  app/Actions/FormSubmission/StartChildSubmission.php app/Actions/FormSubmission/SubmitFormSubmission.php \
  | grep -E 'terlalu (panjang|kompleks)'                                             # kosong
```

- [ ] **Step 4: Jalankan test-nya**

Run: perintah Pest di Step 1. Expected: hijau, jumlah sama.
`SubmitFormSubmission` dipakai 10 berkas test — termasuk jalur lomba-balap BR-SM-16.

- [ ] **Step 5: Commit ekstraksi — komentar belum disentuh**

```bash
CI composer run analyse:strict
CI ./vendor/bin/pint -q
git add app/Actions/FormSubmission
git commit -m "refactor(submission): ekstrak guard dan penandaan submit dari tiga handle"
```

Berkasnya sengaja masih di antrean: pelanggaran prosanya belum dibereskan.
Commit ini yang dibaca Task 9 untuk membuktikan K1.

- [ ] **Step 6: Padatkan prosa (K2)**

**Jangan hilangkan** di `SubmitFormSubmission`: kenapa SAVEPOINT perlu (23505 membuat
PostgreSQL membatalkan seluruh transaksi, lalu kueri pemulihan gagal 25P02 dan peneliti
tetap mendapat 500), dan kenapa `errorInfo[0]` dipakai alih-alih `getCode()`.
Di `ResubmitFormSubmission`: bahwa efek ini dulu milik
`FormSubmission::updateStatusBasedOnReviews()` yang dihapus karena jalur auto-reject
terlarang (ADR 0006).

- [ ] **Step 7: Bersih, keluarkan dari antrean**

```bash
for f in ResubmitFormSubmission StartChildSubmission SubmitFormSubmission; do
  sed -i "\|^app/Actions/FormSubmission/${f}\.php$|d" quality-legacy.txt
done
```
Setel `max.legacy.files: 132`.

- [ ] **Step 8: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, 940 test.

- [ ] **Step 9: Commit**

```bash
CI ./vendor/bin/pint -q
git add app/Actions/FormSubmission quality-legacy.txt quality-budget.txt
git commit -m "refactor(submission): padatkan prosa tiga Action dan keluarkan dari antrean"
```

---

### Task 5: `Actions/Budget` dan `GrantPhaseOverride` — 3 berkas

**Files:**
- Create: `app/Data/Request/PhaseOverrideInput.php`
- Modify: `app/Actions/Budget/UpdateBudgetLineItem.php`, `app/Actions/Budget/SaveBudgetLineItem.php`,
  `app/Actions/Override/GrantPhaseOverride.php`
- Modify: `app/Http/Controllers/Submission/PhaseOverrideController.php`
- Modify: `tests/Feature/FormEngine/PhaseOverrideTest.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Produces: `App\Data\Request\PhaseOverrideInput(string $reason, ?string $expiresAt = null)`;
  `GrantPhaseOverride::run(FormSubmission $submission, FormPhaseDetail $detail, User $grantedBy, PhaseOverrideInput $input): FormSubmissionOverride`

| Berkas · method | Sekarang | Ekstrak jadi | Sisa ± |
| --------------- | -------- | ------------ | -----: |
| `UpdateBudgetLineItem::handle` | 74 · cc 12 | `guardOwnership(FormSubmission $submission, BudgetLineItem $item, User $user): void` — `can('update-budget')` dan BR-SM-07 (bar. 41–51) · `guardComponentSelectable(int $componentId, BudgetLineItem $item): void` — BR-BUD-05 (bar. 63–76) · `snapshot(BudgetLineItem $item): array` — empat kunci `item_name`/`volume`/`unit_price`/`total`, dipakai **dua kali**: untuk `old` sebelum `update()` dan untuk `new` sesudahnya | 35 · cc ≤ 8 |
| `SaveBudgetLineItem::handle` | 56 | `guardNewItem(FormSubmission $submission, User $user, array $attributes): BudgetComponent` — `can`, BR-BUD-04, BR-BUD-05; mengembalikan komponen aktif (bar. 29–48) | 35 |
| `GrantPhaseOverride::handle` | 46 · **5 param** | Introduce Parameter Object: `$reason` + `$expiresAt` → `PhaseOverrideInput` · `expiry(PhaseOverrideInput $input): ?Carbon` — BR-FE-09 alasan wajib dan tenggat di masa depan (bar. 40–52) | 30 · 4 param |

`snapshot()` membawa docblock
`@return array{item_name: string, volume: int, unit_price: int, total: int}` — persis
`@property` di `BudgetLineItem`.

- [ ] **Step 1: Catat titik awal**

```bash
CI php tools/limits.php app/Actions/Budget/UpdateBudgetLineItem.php \
  app/Actions/Budget/SaveBudgetLineItem.php app/Actions/Override/GrantPhaseOverride.php
CI ./vendor/bin/pest --filter='Budget|PhaseOverride'
```

- [ ] **Step 2: Tulis `PhaseOverrideInput`**

```php
<?php

namespace App\Data\Request;

/** BR-FE-09 — masukan perpanjangan akses fase: alasan wajib, tenggat boleh kosong. */
final class PhaseOverrideInput
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $expiresAt = null,
    ) {}
}
```

Sudah diuji lolos tier strict. Tanpa `fromValidated()`: controller-nya memakai
`$request->validate()` yang mengembalikan array, dan satu-satunya pemanggil tidak
butuh pabrik (YAGNI).

- [ ] **Step 3: Ubah signature `GrantPhaseOverride::handle` dan `@method`-nya**

```php
 * @method static FormSubmissionOverride run(FormSubmission $submission, FormPhaseDetail $detail, User $grantedBy, PhaseOverrideInput $input)
```
```php
    public function handle(
        FormSubmission $submission,
        FormPhaseDetail $detail,
        User $grantedBy,
        PhaseOverrideInput $input,
    ): FormSubmissionOverride {
        $expiry = $this->expiry($input);
```
`'reason' => trim($reason)` jadi `'reason' => trim($input->reason)`.

- [ ] **Step 4: Alihkan pemanggilnya**

`PhaseOverrideController`:
```php
        GrantPhaseOverride::run(
            $submission,
            FormPhaseDetail::findOrFail($validated['form_phase_detail_id']),
            $operator,
            new PhaseOverrideInput($validated['reason'], $validated['expires_at'] ?? null),
        );
```

`tests/Feature/FormEngine/PhaseOverrideTest.php` — empat pemanggil di baris 99, 108,
119, 131. Bungkus argumen ke-4 dan ke-5:
```php
GrantPhaseOverride::run($fx['submission'], $fx['detail'], $fx['operator'],
    new PhaseOverrideInput('Kendala jaringan kampus', '2026-03-10 23:59:00'));
```
Yang di baris 131 hanya membawa alasan: `new PhaseOverrideInput('   ')`.

- [ ] **Step 5: Extract Method sesuai tabel seam**

- [ ] **Step 6: Buktikan method lolos dengan komentar utuh (K1)**

```bash
git diff -U0 -- app/Actions/Budget/ app/Actions/Override/ | grep -E '^-\s*(//|\*|/\*)' | head   # kosong
CI php tools/limits.php app/Actions/Budget/UpdateBudgetLineItem.php \
  app/Actions/Budget/SaveBudgetLineItem.php app/Actions/Override/GrantPhaseOverride.php \
  | grep -E 'terlalu (panjang|kompleks|banyak)'                                                   # kosong
```

- [ ] **Step 7: Jalankan test-nya**

Run: perintah Pest di Step 1. Expected: hijau, jumlah sama.

- [ ] **Step 8: Commit ekstraksi — komentar belum disentuh**

```bash
CI composer run analyse:strict
CI ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(budget): ekstrak guard anggaran dan bungkus masukan perpanjangan fase"
```

Berkasnya sengaja masih di antrean: pelanggaran prosanya belum dibereskan.
Commit ini yang dibaca Task 9 untuk membuktikan K1.

- [ ] **Step 9: Padatkan prosa (K2)**

Jangan hilangkan BR-BUD-08 (nilai sebelum-sesudah karena angkanya bahan dispute),
dan di `GrantPhaseOverride` bahwa alasan sengaja **tidak** ikut ke notifikasi
(BR-FE-16: hanya untuk Operator dan Admin).

- [ ] **Step 10: Bersih, keluarkan dari antrean**

```bash
for f in app/Actions/Budget/UpdateBudgetLineItem.php app/Actions/Budget/SaveBudgetLineItem.php \
         app/Actions/Override/GrantPhaseOverride.php; do
  sed -i "\|^${f}$|d" quality-legacy.txt
done
```
Setel `max.legacy.files: 129`.

- [ ] **Step 11: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, 940 test.

- [ ] **Step 12: Commit**

```bash
CI ./vendor/bin/pint -q
git add app tests quality-legacy.txt quality-budget.txt
git commit -m "refactor(budget): padatkan prosa anggaran dan perpanjangan fase, keluarkan dari antrean"
```

---

### Task 6: Periode — 3 berkas

**Files:**
- Create: `app/Queries/Period/SubmissionPeriodReferences.php`
- Modify: `app/Actions/Period/DeleteSubmissionPeriod.php`, `app/Models/SubmissionPeriod.php`,
  `app/Actions/Period/ExtendSubmissionDates.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Produces: `SubmissionPeriodReferences::run(SubmissionPeriod $period): array<string, int>`

- [ ] **Step 1: Catat titik awal**

```bash
CI php tools/limits.php app/Actions/Period/DeleteSubmissionPeriod.php \
  app/Models/SubmissionPeriod.php app/Actions/Period/ExtendSubmissionDates.php
CI ./vendor/bin/pest --filter='Period|SubmissionDate|Deadline'
```
Expected: `DeleteSubmissionPeriod` dan `SubmissionPeriod` saling **berbagi 2 blok**.

- [ ] **Step 2: Tulis `SubmissionPeriodReferences`**

```php
<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-21 — rujukan yang menghalangi penghapusan satu periode. Dipakai hook
 * `deleting` dan Action-nya supaya keduanya menghitung hal yang sama.
 *
 * @method static array<string, int> run(SubmissionPeriod $period)
 */
class SubmissionPeriodReferences implements ChangesNoState
{
    use AsAction;

    /** @return array<string, int> */
    public function handle(SubmissionPeriod $period): array
    {
        return [
            'pengajuan' => FormSubmission::query()
                ->where('submission_period_id', $period->id)
                ->count(),
            'detail tahap yang memakai tanggalnya' => FormPhaseDetail::query()
                ->whereIn('submission_date_id', SubmissionDate::query()
                    ->where('submission_period_id', $period->id)
                    ->select('id'))
                ->count(),
        ];
    }
}
```

Sudah diuji lolos tier strict. Label kuncinya **harus** sama persis dengan yang
sekarang — `ReferenceGuard` memakainya dalam pesan galat yang dibaca pengguna.

- [ ] **Step 3: Pakai di kedua tempat (K4)**

`DeleteSubmissionPeriod::handle()`:
```php
        ReferenceGuard::assertUnreferenced('Periode pengajuan', SubmissionPeriodReferences::run($period));
```

Di hook `deleting` `SubmissionPeriod::booted()`, ganti array literalnya dengan panggilan
yang sama. **Guard di Action tetap ada** — lihat K4.

- [ ] **Step 4: Extract Method `ExtendSubmissionDates::handle` (74 baris)**

| Ekstrak jadi | Isi |
| ------------ | --- |
| `syncDates(SubmissionPeriod $period, array $dates): bool` | seluruh isi closure transaksi (bar. 45–88), **mengembalikan** `$adaPerpanjangan` alih-alih menulisnya lewat referensi `&$adaPerpanjangan`. `handle()` jadi `$adaPerpanjangan = DB::transaction(fn (): bool => $this->syncDates($period, $dates));` |
| `draftOwners(SubmissionPeriod $period): Collection` | kueri pemilik draft (bar. 95–100), `@return Collection<int, User>` |

Komentar "diperbarui di tempat, bukan dihapus lalu dibuat ulang" ikut ke `syncDates()`
utuh — ia satu-satunya catatan kenapa foreign key `form_phase_details.submission_date_id`
membuat hapus-buat-ulang selalu gagal.

- [ ] **Step 5: Buktikan bersih dari duplikasi dan method lolos dengan komentar utuh**

```bash
git diff -U0 -- app/Actions/Period/ app/Models/SubmissionPeriod.php | grep -E '^-\s*(//|\*|/\*)' | head  # kosong
CI php tools/limits.php app/Actions/Period/DeleteSubmissionPeriod.php \
  app/Models/SubmissionPeriod.php app/Actions/Period/ExtendSubmissionDates.php \
  | grep -E 'terlalu (panjang|kompleks)|berbagi'                                                      # kosong
```

- [ ] **Step 6: Jalankan test-nya**

Run: perintah Pest di Step 1. Expected: hijau, jumlah sama. Pastikan test yang
menolak penghapusan periode bertanggal-terpakai tetap hijau — itulah jaring K4.

- [ ] **Step 7: Commit ekstraksi — komentar belum disentuh**

```bash
CI composer run analyse:strict
CI ./vendor/bin/pint -q
git add app
git commit -m "refactor(period): satukan kueri rujukan periode dan ekstrak sinkronisasi tanggal"
```

Berkasnya sengaja masih di antrean: pelanggaran prosanya belum dibereskan.
Commit ini yang dibaca Task 9 untuk membuktikan K1.

- [ ] **Step 8: Padatkan prosa (K2)**

`DeleteSubmissionPeriod` docblock kelasnya (run 6) menjelaskan **dua** penghalang dan
kenapa yang kedua halus (BR-FE-07). Keduanya bertahan.

- [ ] **Step 9: Bersih, keluarkan dari antrean**

```bash
for f in app/Actions/Period/DeleteSubmissionPeriod.php app/Models/SubmissionPeriod.php \
         app/Actions/Period/ExtendSubmissionDates.php; do
  sed -i "\|^${f}$|d" quality-legacy.txt
done
```
Setel `max.legacy.files: 126`.

- [ ] **Step 10: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, 940 test.

- [ ] **Step 11: Commit**

```bash
CI ./vendor/bin/pint -q
git add app quality-legacy.txt quality-budget.txt
git commit -m "refactor(period): padatkan prosa periode dan keluarkan dari antrean"
```

---

### Task 7: Config, Scheme, Review — 4 berkas

**Files:**
- Modify: `app/Actions/Config/SaveReferenceEntity.php`, `app/Support/Config/ReferenceEntityRegistry.php`,
  `app/Actions/Scheme/SyncSchemeSelection.php`, `app/Actions/Review/AssignReviewer.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Produces: ekstraksi `private` saja. `ReferenceEntityRegistry::all()` tetap publik
  dengan keluaran **berurutan sama**.

| Berkas · method | Sekarang | Ekstrak jadi | Sisa ± |
| --------------- | -------- | ------------ | -----: |
| `SaveReferenceEntity::handle` | 46 | `create(ReferenceEntity $entity, ReferenceEntityInput $input, array $attributes): Model` — cabang `$record === null` (bar. 50–73), dan di dalamnya `withCode(ReferenceEntity $entity, ReferenceEntityInput $input, array $attributes): array` untuk penurunan kode BR-SC-03 (bar. 56–66) | 20 |
| `ReferenceEntityRegistry::all` | 51 · cc 1 | Decompose: `masterData(): list<ReferenceEntity>` — `scheme-types`, `submission-types`, `budget-components` · `taxonomies(): list<ReferenceEntity>` — `output-types`, `trls`. `all()` menjadi `collect([...self::masterData(), ...self::taxonomies()])->keyBy(...)->all()` | 5 |
| `SyncSchemeSelection::handle` | 55 · cc 9 | `selectedSchemeId(FormSubmission $submission): ?int` — cari field `scheme_selector` dan jawabannya, `null` bila tidak ada (bar. 34–51) · `resolveScheme(int $schemeId, FormSubmission $submission): Scheme` — `find`, lempar bila tidak ada, BR-SCH-10 (bar. 53–68) | 22 · cc ≤ 8 |
| `AssignReviewer::handle` | 41 | `persist(FormSubmission $submission, Reviewer $reviewer, User $candidate): SubmissionReviewer` — isi closure transaksi (bar. 59–73) | 28 |

**Urutan `all()` mengikat.** Urutan asal: `scheme-types`, `submission-types`,
`budget-components`, `output-types`, `trls`. Pembagian di atas mempertahankannya —
jangan mengelompokkan ulang berdasarkan selera, karena layar konfigurasi mendaftarnya
dalam urutan ini.

Konversi `(int) $response->value` yang sudah ada di `handle()` ikut pindah **apa
adanya** ke `selectedSchemeId()`. Ia sudah lolos tier strict di tempatnya sekarang;
jangan menambah cast baru di mana pun.

- [ ] **Step 1: Catat titik awal**

```bash
CI php tools/limits.php app/Actions/Config/SaveReferenceEntity.php \
  app/Support/Config/ReferenceEntityRegistry.php app/Actions/Scheme/SyncSchemeSelection.php \
  app/Actions/Review/AssignReviewer.php
CI ./vendor/bin/pest --filter='ReferenceEntity|Scheme|AssignReviewer|REV-ASSIGN'
```

- [ ] **Step 2: Extract Method sesuai tabel seam**

- [ ] **Step 3: Buktikan method lolos dengan komentar utuh (K1)**

```bash
git diff -U0 -- app/Actions/Config/ app/Support/Config/ app/Actions/Scheme/ app/Actions/Review/AssignReviewer.php \
  | grep -E '^-\s*(//|\*|/\*)' | head                                                     # kosong
CI php tools/limits.php app/Actions/Config/SaveReferenceEntity.php \
  app/Support/Config/ReferenceEntityRegistry.php app/Actions/Scheme/SyncSchemeSelection.php \
  app/Actions/Review/AssignReviewer.php | grep -E 'terlalu (panjang|kompleks)'               # kosong
```

- [ ] **Step 4: Jalankan test-nya**

Run: perintah Pest di Step 1. Expected: hijau, jumlah sama.

- [ ] **Step 5: Commit ekstraksi — komentar belum disentuh**

```bash
CI composer run analyse:strict
CI ./vendor/bin/pint -q
git add app
git commit -m "refactor(config): ekstrak cabang simpan, registri, pilihan skema, dan penugasan"
```

Berkasnya sengaja masih di antrean: pelanggaran prosanya belum dibereskan.
Commit ini yang dibaca Task 9 untuk membuktikan K1.

- [ ] **Step 6: Padatkan prosa (K2)**

`ReferenceEntityRegistry` (run 12) memuat dua fakta yang hanya tercatat di sana:
`is_active` tipe luaran dulu dibaca di mana-mana tapi tak pernah ditulis (BR-SC-04),
dan tabel TRL tidak punya kolom `is_active` sehingga tombol Nonaktifkan akan gagal.
Keduanya bertahan.

- [ ] **Step 7: Bersih, keluarkan dari antrean**

```bash
for f in app/Actions/Config/SaveReferenceEntity.php app/Support/Config/ReferenceEntityRegistry.php \
         app/Actions/Scheme/SyncSchemeSelection.php app/Actions/Review/AssignReviewer.php; do
  sed -i "\|^${f}$|d" quality-legacy.txt
done
```
Setel `max.legacy.files: 122`.

- [ ] **Step 8: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, 940 test.

- [ ] **Step 9: Commit**

```bash
CI ./vendor/bin/pint -q
git add app quality-legacy.txt quality-budget.txt
git commit -m "refactor(config): padatkan prosa empat berkas dan keluarkan dari antrean"
```

---

### Task 8: `NotifyActiveSubmissionPeriods` — karakterisasi dulu, baru bedah

Satu-satunya berkas batch ini **tanpa test**. Test karakterisasi mengunci perilaku
apa adanya sebelum disentuh — termasuk yang terasa janggal.

**Files:**
- Create: `tests/Feature/Period/NotifyActiveSubmissionPeriodsTest.php`
- Create: `app/Queries/Period/PeriodsOpeningToday.php`
- Modify: `app/Console/Commands/NotifyActiveSubmissionPeriods.php`
- Modify: `quality-legacy.txt`, `quality-budget.txt`

**Interfaces:**
- Produces: `PeriodsOpeningToday::run(CarbonInterface $now): Collection<int, SubmissionPeriod>`

- [ ] **Step 1: Tulis test karakterisasi**

```php
<?php

// tests/Feature/Period/NotifyActiveSubmissionPeriodsTest.php
//
// Karakterisasi notify:active-submission-periods sebelum dibedah. Perilakunya
// dikunci apa adanya, termasuk yang terasa janggal — tujuan test ini menjaga
// refactor, bukan menilai aturannya.

use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionPeriodActiveNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

function periodeDenganTanggal(string $buka, string $tutup): SubmissionPeriod
{
    $period = SubmissionPeriod::factory()->create();

    foreach (['Buka' => $buka, 'Tutup' => $tutup] as $label => $kapan) {
        SubmissionDate::create([
            'submission_period_id' => $period->id,
            'submission_date_label_id' => SubmissionDateLabel::firstOrCreate(['name' => $label])->id,
            'datetime' => $kapan,
        ]);
    }

    return $period;
}

beforeEach(function () {
    Notification::fake();
    Cache::flush();
    Carbon::setTestNow('2026-10-03 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('memberi tahu pengguna non-admin saat periode dibuka hari ini', function () {
    $peneliti = User::factory()->create();
    periodeDenganTanggal('2026-10-03 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods')->assertExitCode(0);

    Notification::assertSentTo($peneliti, SubmissionPeriodActiveNotification::class);
});

it('tidak memberi tahu Admin maupun Super Admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Admin', 'web'));
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('Super Admin', 'web'));
    periodeDenganTanggal('2026-10-03 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');

    Notification::assertNotSentTo($admin, SubmissionPeriodActiveNotification::class);
    Notification::assertNotSentTo($superAdmin, SubmissionPeriodActiveNotification::class);
});

it('mengabaikan periode yang dibuka kemarin', function () {
    User::factory()->create();
    periodeDenganTanggal('2026-10-02 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');

    Notification::assertNothingSent();
});

it('mengabaikan periode yang dibuka hari ini tapi jamnya belum tiba', function () {
    // Sekarang 09:00, pembukaannya 10:00: tanggalnya hari ini, tapi `between()`
    // menolaknya karena waktu sekarang masih di luar rentang.
    User::factory()->create();
    periodeDenganTanggal('2026-10-03 10:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');

    Notification::assertNothingSent();
});

it('tidak memberi tahu dua kali untuk periode yang sama', function () {
    $peneliti = User::factory()->create();
    periodeDenganTanggal('2026-10-03 08:00:00', '2026-10-31 23:59:00');

    $this->artisan('notify:active-submission-periods');
    $this->artisan('notify:active-submission-periods');

    Notification::assertSentToTimes($peneliti, SubmissionPeriodActiveNotification::class, 1);
});

it('mengabaikan periode tanpa tanggal sama sekali', function () {
    User::factory()->create();
    SubmissionPeriod::factory()->create();

    $this->artisan('notify:active-submission-periods')->assertExitCode(0);

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Jalankan terhadap kode SEKARANG, pastikan LULUS**

Run: `CI ./vendor/bin/pest tests/Feature/Period/NotifyActiveSubmissionPeriodsTest.php`
Expected: **6 passed**. Sudah diverifikasi terhadap kode saat dokumen ini ditulis.

Ini test karakterisasi, bukan TDD — ia **harus hijau sebelum refactor**. Kalau ada yang
merah, test-nya yang salah membaca perilaku sekarang: betulkan **test-nya**, jangan
kodenya. Test pertama dan kelima membuktikan pengiriman positif, jadi test negatif
yang hanya `assertNothingSent()` tidak lolos secara hampa.

- [ ] **Step 3: Tulis `PeriodsOpeningToday`**

```php
<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Periode yang tanggal pertamanya jatuh hari ini dan sudah berjalan pada `$now`.
 *
 * @method static Collection<int, SubmissionPeriod> run(CarbonInterface $now)
 */
class PeriodsOpeningToday implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, SubmissionPeriod> */
    public function handle(CarbonInterface $now): Collection
    {
        return SubmissionPeriod::with('submissionDates')
            ->get()
            ->filter(static function (SubmissionPeriod $period) use ($now): bool {
                $dates = $period->submissionDates->sortBy('datetime');
                $first = $dates->first();
                $last = $dates->last();

                if (!$first instanceof SubmissionDate || !$last instanceof SubmissionDate) {
                    return false;
                }

                $start = Carbon::parse($first->datetime);

                return $start->isToday() && $now->between($start, Carbon::parse($last->datetime));
            })
            ->values();
    }
}
```

Sudah diuji lolos tier strict. Dua `@var` inline di versi command **diganti
`instanceof`** — command ada di tier default yang mengizinkannya, `Queries/` tidak
(AGENTS.md aturan 5).

- [ ] **Step 4: Bedah `handle()` command**

```php
    public function handle(): int
    {
        foreach (PeriodsOpeningToday::run(Carbon::now()) as $period) {
            $cacheKey = "submission_period_notified_{$period->id}";

            if (Cache::has($cacheKey) || !$this->notify($period)) {
                continue;
            }

            // Ditandai setahun supaya run terjadwal berikutnya tidak mengirim ulang.
            Cache::put($cacheKey, true, now()->addYear());

            $this->info("Notification sent for period: {$period->name}");
        }

        $this->info('Done checking active submission periods.');

        return 0;
    }
```

`notify(SubmissionPeriod $period): bool` memuat blok `try`/`catch` (bar. 62–97)
**apa adanya** beserta ketiga komentar BR-NOT-03-nya, mengembalikan `$terkirim`
di jalur sukses dan `false` di dua jalur `continue` sebelumnya.

- [ ] **Step 5: Jalankan test karakterisasi, pastikan TETAP LULUS**

Run: `CI ./vendor/bin/pest tests/Feature/Period/NotifyActiveSubmissionPeriodsTest.php`
Expected: **6 passed**.

- [ ] **Step 6: Commit ekstraksi — komentar belum disentuh**

```bash
CI composer run analyse:strict
CI ./vendor/bin/pint -q
git add app tests
git commit -m "refactor(period): kunci notifikasi periode dengan test lalu ekstrak kuerinya"
```

Berkasnya sengaja masih di antrean: pelanggaran prosanya belum dibereskan.
Commit ini yang dibaca Task 9 untuk membuktikan K1.

- [ ] **Step 7: Padatkan prosa (K2)**

Tiga komentar BR-NOT-03 di `notify()` menjelaskan tiga hal berbeda: kenapa satu
periode gagal tidak menghentikan loop, kenapa periode gagal **tidak** di-cache, dan
kenapa `catch` ini hanya menangkap kegagalan mencari penerima (pengirimannya sudah
ditelan `Deliver`). Padatkan, jangan gabungkan jadi satu kalimat yang kehilangan dua.

- [ ] **Step 8: Bersih, keluarkan dari antrean**

```bash
CI php tools/limits.php app/Console/Commands/NotifyActiveSubmissionPeriods.php app/Queries/Period/PeriodsOpeningToday.php
sed -i '\|^app/Console/Commands/NotifyActiveSubmissionPeriods\.php$|d' quality-legacy.txt
```
Setel `max.legacy.files: 121`.

- [ ] **Step 9: Gerbang penuh**

Run: `CI composer run quality` — Expected: hijau, **946 test**.

- [ ] **Step 10: Commit**

```bash
CI ./vendor/bin/pint -q
git add app tests quality-legacy.txt quality-budget.txt
git commit -m "refactor(period): padatkan prosa notifikasi periode dan keluarkan dari antrean"
```

---

### Task 9: Penutup

**Files:**
- Modify: `docs/design/refactor-plan-batch-php-non-controller.md`

- [ ] **Step 1: Gerbang penuh, dua-duanya**

Run: `CI composer run quality` — Expected: hijau, **946 test**.
Run: `npm run quality` — Expected: hijau.

- [ ] **Step 2: Buktikan K1 dari riwayat git**

Setiap commit ekstraksi di Task 3–8 memuat kata `ekstrak` di pesannya. Pertama,
tidak satu pun boleh menghapus baris komentar:

```bash
BASE=<hash yang dicatat sebelum Task 2 Step 1>
for c in $(git log --format=%h --grep='ekstrak' "$BASE"..HEAD); do
  echo "== $c $(git log -1 --format=%s "$c")"
  git show "$c" -- app/ | grep -E '^-\s*(//|\*|/\*)' | grep -vE '^-\s*\*\s*$' | head -3
done
```
Expected: enam baris `==`, **tanpa** baris apa pun di bawah masing-masing.

Kedua, method-nya harus sudah lolos **di commit itu** — sebelum prosa dipadatkan:

```bash
for c in $(git log --format=%h --grep='ekstrak' "$BASE"..HEAD); do
  git worktree add -f "../k1-$c" "$c" >/dev/null 2>&1
  files=$(git show --name-only --format= "$c" -- app/ | grep '\.php$' | tr '\n' ' ')
  docker run --rm -v "$PWD/../k1-$c":/app -v "$PWD/vendor":/app/vendor -w /app \
    -u "$(id -u):$(id -g)" simpas-ci:8.4 php tools/limits.php $files \
    | grep -E 'terlalu (panjang|kompleks|banyak)' && echo "  ✗ K1 dilanggar di $c"
  git worktree remove --force "../k1-$c"
done
```
Expected: tidak ada `✗ K1 dilanggar`. Yang boleh tersisa di commit-commit itu hanya
`prosa komentar terlalu panjang` — dan itu yang dibereskan commit berikutnya.

- [ ] **Step 3: Baca ulang teks yang terhapus (K2a)**

```bash
git diff "$BASE"..HEAD -- app/ | grep -E '^-\s*(//|\*)' | grep -vE '^-\s*\*\s*$'
```

Setiap baris yang keluar di sini dibaca manusia dan dibandingkan dengan yang tersisa.
Pertanyaannya satu: **adakah fakta tentang dunia** — perilaku library, sifat
PostgreSQL, kebiasaan LPPM, batasan skema — yang tidak lagi tercatat di mana pun?
Tooling tidak bisa melihat ini; batch prosa lalu kehilangan empat fakta yang lolos
seluruh gerbang.

- [ ] **Step 4: Catat hasilnya di dokumen ini**

Ubah **Status** jadi `selesai <tanggal>, mendarat di <hash>`, dan tulis tabel
Sebelum/Sesudah: antrean 141 → 121, test 939 → 946, `max.mixed` 20 → 20.

- [ ] **Step 5: Commit**

```bash
git add docs/design/refactor-plan-batch-php-non-controller.md
git commit -m "docs(design): catat hasil batch PHP non-controller"
```
