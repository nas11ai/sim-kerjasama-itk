# Refactor Gelombang 0–1 — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai `superpowers:subagent-driven-development`
> (disarankan) atau `superpowers:executing-plans` untuk mengerjakan rencana ini tugas per
> tugas. Langkahnya memakai checkbox (`- [ ]`) sebagai penanda.

**Goal:** Membangun pagar kualitas yang ditegakkan mesin, lalu memakainya pertama kali
untuk memberi bentuk pada data yang selama ini bertipe `mixed`.

**Architecture:** Satu pustaka pengukur berbasis tokenizer PHP di `app/Support/Quality/`,
dua berkas plafon teks di akar repo, dan satu test Pest yang menegakkannya — meniru
`BddCoverageTest` + `pending.txt` yang sudah terbukti jalan di repo ini. Nol dependensi
baru.

**Tech Stack:** Laravel 13 · PHP 8.4 · Pest · PHPStan/Larastan · spatie/laravel-data

**Spec:** [`docs/design/refactor-plan.md`](refactor-plan.md) v1.3

## Global Constraints

- **Identifier bahasa Inggris.** Variabel, method, kelas, properti. Prosa komentar dan
  dokumen tetap bahasa Indonesia.
- **Prosa komentar maksimal 3 baris.** Anotasi `@param`/`@return`/`@throws`/`@var`
  dikecualikan — level 10 menuntutnya. Alasan panjang tempatnya di `docs/design/`.
- **Dilarang menyiasati PHPStan.** Tanpa `@phpstan-ignore`, baseline, `assert()`, inline
  `@var`, cast, atau pelebaran tipe. (AGENTS.md aturan 5)
- **Dilarang percabangan khusus testing di production code.** (AGENTS.md aturan 6)
- **PostgreSQL, bukan MySQL.** (AGENTS.md aturan 4)
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case. (AGENTS.md aturan 8)
- **Verifikasi wajib:** `composer run quality` dan `npm run quality` hijau sebelum tugas
  apa pun dinyatakan selesai.
- **Host PHP bukan 8.4.** Jalankan lewat container:
  ```bash
  docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
    -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 <perintah>
  ```
  Image `php:8.4-cli` di AGENTS.md **tidak punya `pdo_pgsql`** dan menggagalkan 689 test.
  Pakai `simpas-ci:8.4`.

---

## Peta Berkas

| Berkas | Tanggung jawab |
| ------ | -------------- |
| `app/Support/Quality/MethodMetric.php` | Hasil ukur satu method |
| `app/Support/Quality/CodeMetrics.php` | Mengukur berkas PHP lewat `token_get_all()` |
| `app/Support/Quality/VueMetrics.php` | Mengukur berkas `.vue` |
| `app/Support/Quality/CommentMetrics.php` | Menghitung baris prosa komentar |
| `app/Support/Quality/DuplicationScanner.php` | Blok enam baris yang muncul di >1 berkas |
| `app/Support/Quality/BudgetFile.php` | Membaca `quality-budget.txt` |
| `quality-budget.txt` | Angka ambang dan plafon |
| `quality-legacy.txt` | Antrean kerja refactor |
| `tests/Unit/Architecture/QualityBudgetTest.php` | Dua belas assertion penegak |

`App\Support\Bdd\LedgerFile` **dipakai ulang** untuk membaca `quality-legacy.txt` —
formatnya identik `pending.txt`.

---

# GELOMBANG 0 — Pasang pagar

### Task 1: Pengukur method

**Files:**
- Create: `app/Support/Quality/MethodMetric.php`
- Create: `app/Support/Quality/CodeMetrics.php`
- Test: `tests/Unit/Quality/CodeMetricsTest.php`

**Interfaces:**
- Consumes: —
- Produces: `CodeMetrics::forFile(string $path): array{loc: int, methods: list<MethodMetric>}`
- Produces: `MethodMetric` readonly: `name`, `loc`, `complexity`, `parameters`, `returns`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Unit/Quality/CodeMetricsTest.php

use App\Support\Quality\CodeMetrics;

function writeSource(string $contents): string
{
    $path = sys_get_temp_dir().'/metrics_'.uniqid().'.php';
    file_put_contents($path, $contents);

    return $path;
}

it('menghitung LOC berkas dan jumlah method', function () {
    $path = writeSource(<<<'PHP'
    <?php
    class Sample
    {
        public function first(): int
        {
            return 1;
        }
    }
    PHP);

    $result = CodeMetrics::forFile($path);

    expect($result['loc'])->toBe(9)
        ->and($result['methods'])->toHaveCount(1)
        ->and($result['methods'][0]->name)->toBe('first');

    unlink($path);
});

it('menghitung cyclomatic complexity dari setiap percabangan', function () {
    $path = writeSource(<<<'PHP'
    <?php
    class Sample
    {
        public function branching(int $n): string
        {
            if ($n > 1 && $n < 10) {
                return 'a';
            }

            foreach ([1, 2] as $x) {
                if ($x) {
                    return 'b';
                }
            }

            return 'c';
        }
    }
    PHP);

    $method = CodeMetrics::forFile($path)['methods'][0];

    // 1 dasar + if + && + foreach + if
    expect($method->complexity)->toBe(5)
        ->and($method->returns)->toBe(3)
        ->and($method->parameters)->toBe(1);

    unlink($path);
});

it('tidak tertipu kurung kurawal di dalam string', function () {
    $path = writeSource(<<<'PHP'
    <?php
    class Sample
    {
        public function text(): string
        {
            $value = '} bukan penutup {';

            return $value;
        }

        public function second(): int
        {
            return 2;
        }
    }
    PHP);

    expect(CodeMetrics::forFile($path)['methods'])->toHaveCount(2);

    unlink($path);
});

it('tidak menghitung deklarasi tanpa badan', function () {
    $path = writeSource(<<<'PHP'
    <?php
    interface Contract
    {
        public function withoutBody(): void;
    }
    PHP);

    expect(CodeMetrics::forFile($path)['methods'])->toHaveCount(0);

    unlink($path);
});

it('menghitung promoted property sebagai parameter constructor', function () {
    $path = writeSource(<<<'PHP'
    <?php
    class Sample
    {
        public function __construct(
            private readonly int $a,
            private readonly int $b,
        ) {}
    }
    PHP);

    expect(CodeMetrics::forFile($path)['methods'][0]->parameters)->toBe(2);

    unlink($path);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Quality/CodeMetricsTest.php
```
Expected: FAIL — `Class "App\Support\Quality\CodeMetrics" not found`

- [ ] **Step 3: Tulis `MethodMetric`**

```php
<?php

namespace App\Support\Quality;

/** Hasil ukur satu method. Readonly karena ia hasil pengukuran, bukan keadaan. */
final class MethodMetric
{
    public function __construct(
        public readonly string $name,
        public readonly int $loc,
        public readonly int $complexity,
        public readonly int $parameters,
        public readonly int $returns,
    ) {}
}
```

- [ ] **Step 4: Tulis `CodeMetrics`**

```php
<?php

namespace App\Support\Quality;

use RuntimeException;

/**
 * Mengukur berkas PHP lewat tokenizer, bukan regex.
 *
 * Regex tidak bisa membedakan `}` penutup method dari `}` di dalam string, dan
 * plafon yang dihitung keliru menghabiskan kepercayaan pada pagarnya.
 */
final class CodeMetrics
{
    /** @var list<int> token yang menambah satu jalur keputusan */
    private const BRANCH_TOKENS = [
        T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH,
        T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_COALESCE,
    ];

    /** @return array{loc: int, methods: list<MethodMetric>} */
    public static function forFile(string $path): array
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException("Berkas tidak terbaca: {$path}");
        }

        return [
            'loc' => substr_count($source, "\n") + 1,
            'methods' => self::methods($source),
        ];
    }

    /** @return list<MethodMetric> */
    private static function methods(string $source): array
    {
        $tokens = token_get_all($source);
        $total = count($tokens);
        $methods = [];

        for ($i = 0; $i < $total; $i++) {
            $token = $tokens[$i];

            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $openParen = self::seek($tokens, $i, $total, '(');

            if ($openParen === null) {
                continue;
            }

            $openBrace = self::seekBody($tokens, $openParen, $total);

            // Deklarasi abstrak dan interface berakhir di `;`, bukan `{`.
            if ($openBrace === null) {
                continue;
            }

            $body = self::measureBody($tokens, $openBrace, $total);

            $methods[] = new MethodMetric(
                name: self::nameAt($tokens, $i, $total),
                loc: $body['loc'],
                complexity: $body['complexity'],
                parameters: self::countParameters($tokens, $openParen, $total),
                returns: $body['returns'],
            );

            $i = $body['endIndex'];
        }

        return $methods;
    }

    /** @param array<int, array{0: int, 1: string, 2: int}|string> $tokens */
    private static function nameAt(array $tokens, int $from, int $total): string
    {
        for ($i = $from + 1; $i < $total; $i++) {
            if ($tokens[$i] === '(') {
                return '{closure}';
            }

            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                return $tokens[$i][1];
            }
        }

        return '{unknown}';
    }

    /** @param array<int, array{0: int, 1: string, 2: int}|string> $tokens */
    private static function seek(array $tokens, int $from, int $total, string $needle): ?int
    {
        for ($i = $from; $i < $total; $i++) {
            if ($tokens[$i] === $needle) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Menghitung `T_VARIABLE` di kedalaman satu. Promoted property ikut terhitung,
     * dan memang harus: ia parameter constructor.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function countParameters(array $tokens, int $openParen, int $total): int
    {
        $depth = 0;
        $parameters = 0;

        for ($i = $openParen; $i < $total; $i++) {
            $token = $tokens[$i];

            if ($token === '(') {
                $depth++;

                continue;
            }

            if ($token === ')') {
                $depth--;

                if ($depth === 0) {
                    break;
                }

                continue;
            }

            if ($depth === 1 && is_array($token) && $token[0] === T_VARIABLE) {
                $parameters++;
            }
        }

        return $parameters;
    }

    /** @param array<int, array{0: int, 1: string, 2: int}|string> $tokens */
    private static function seekBody(array $tokens, int $from, int $total): ?int
    {
        for ($i = $from; $i < $total; $i++) {
            if ($tokens[$i] === ';') {
                return null;
            }

            if ($tokens[$i] === '{') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @return array{loc: int, complexity: int, returns: int, endIndex: int}
     */
    private static function measureBody(array $tokens, int $openBrace, int $total): array
    {
        $depth = 0;
        $complexity = 1;
        $returns = 0;
        $endIndex = $openBrace;

        for ($i = $openBrace; $i < $total; $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                $depth++;

                continue;
            }

            if ($token === '}') {
                $depth--;

                if ($depth === 0) {
                    $endIndex = $i;

                    break;
                }

                continue;
            }

            if ($token === '?') {
                $complexity++;

                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], self::BRANCH_TOKENS, true)) {
                $complexity++;
            }

            if ($token[0] === T_RETURN) {
                $returns++;
            }
        }

        return [
            'loc' => self::lineSpan($tokens, $openBrace, $endIndex, $total),
            'complexity' => $complexity,
            'returns' => $returns,
            'endIndex' => $endIndex,
        ];
    }

    /** @param array<int, array{0: int, 1: string, 2: int}|string> $tokens */
    private static function lineSpan(array $tokens, int $start, int $end, int $total): int
    {
        $first = null;
        $last = null;

        for ($i = $start; $i >= 0; $i--) {
            if (is_array($tokens[$i])) {
                $first = $tokens[$i][2];

                break;
            }
        }

        for ($i = $end; $i < $total; $i++) {
            if (is_array($tokens[$i])) {
                $last = $tokens[$i][2];

                break;
            }
        }

        if ($first === null || $last === null) {
            return 0;
        }

        return max(1, $last - $first);
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Quality/CodeMetricsTest.php
```
Expected: PASS, 5 test

- [ ] **Step 6: Pastikan pengukurnya sendiri lolos level 10**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/phpstan analyse app/Support/Quality --level=10 --no-progress
```
Expected: `[OK] No errors`. Kalau ada error, perbaiki penyebabnya — jangan tambahkan
`@phpstan-ignore`.

- [ ] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app/Support/Quality tests/Unit/Quality
git commit -m "feat(quality): ukur LOC dan kompleksitas method lewat tokenizer"
```

---

### Task 2: Pengukur Vue, komentar, dan duplikasi

**Files:**
- Create: `app/Support/Quality/VueMetrics.php`
- Create: `app/Support/Quality/CommentMetrics.php`
- Create: `app/Support/Quality/DuplicationScanner.php`
- Test: `tests/Unit/Quality/CommentMetricsTest.php`
- Test: `tests/Unit/Quality/DuplicationScannerTest.php`

**Interfaces:**
- Consumes: —
- Produces: `VueMetrics::loc(string $path): int`
- Produces: `CommentMetrics::longestProseRun(string $path): int`
- Produces: `DuplicationScanner::countSharedBlocks(list<string> $paths): int`
- Produces: `DuplicationScanner::filesSharing(list<string> $paths): array<string, int>`

- [ ] **Step 1: Tulis test komentar yang gagal**

```php
<?php

// tests/Unit/Quality/CommentMetricsTest.php

use App\Support\Quality\CommentMetrics;

function writeCommented(string $contents): string
{
    $path = sys_get_temp_dir().'/comments_'.uniqid().'.php';
    file_put_contents($path, $contents);

    return $path;
}

it('menghitung baris prosa terpanjang', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    // baris prosa pertama yang cukup panjang
    // baris prosa kedua yang cukup panjang
    // baris prosa ketiga yang cukup panjang
    // baris prosa keempat yang cukup panjang
    class Sample {}
    PHP);

    expect(CommentMetrics::longestProseRun($path))->toBe(4);

    unlink($path);
});

it('tidak menghitung anotasi sebagai prosa', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     * @return array<string, int>
     * @throws RuntimeException
     */
    class Sample {}
    PHP);

    // Level 10 menuntut docblock kaya; membatasinya akan membuat dua aturan
    // repo ini berkelahi.
    expect(CommentMetrics::longestProseRun($path))->toBe(0);

    unlink($path);
});

it('menghitung prosa di dalam docblock yang juga punya anotasi', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    /**
     * Kalimat penjelas pertama yang panjang.
     * Kalimat penjelas kedua yang panjang.
     *
     * @return void
     */
    class Sample {}
    PHP);

    expect(CommentMetrics::longestProseRun($path))->toBe(2);

    unlink($path);
});

it('memulai hitungan baru setelah baris kode menyela', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    // prosa pertama yang cukup panjang
    // prosa kedua yang cukup panjang
    class Sample {}
    // prosa ketiga yang cukup panjang
    PHP);

    expect(CommentMetrics::longestProseRun($path))->toBe(2);

    unlink($path);
});
```

- [ ] **Step 2: Tulis test duplikasi yang gagal**

```php
<?php

// tests/Unit/Quality/DuplicationScannerTest.php

use App\Support\Quality\DuplicationScanner;

function writeBlock(string $name, string $contents): string
{
    $path = sys_get_temp_dir().'/dup_'.uniqid().'_'.$name;
    file_put_contents($path, $contents);

    return $path;
}

function sixIdenticalLines(): string
{
    return <<<'TXT'
    const first = computeSomethingLong(input);
    const second = computeSomethingElse(input);
    const third = combineBothResults(first, second);
    const fourth = filterTheResult(third, threshold);
    const fifth = sortTheResult(fourth, sortKey);
    const sixth = returnTheResult(fifth);
    TXT;
}

it('menemukan blok yang muncul di dua berkas', function () {
    $a = writeBlock('a.js', sixIdenticalLines());
    $b = writeBlock('b.js', sixIdenticalLines());

    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(1);

    unlink($a);
    unlink($b);
});

it('tidak menghitung blok yang hanya ada di satu berkas', function () {
    $a = writeBlock('a.js', sixIdenticalLines());

    expect(DuplicationScanner::countSharedBlocks([$a]))->toBe(0);

    unlink($a);
});

it('mengabaikan baris pendek', function () {
    $a = writeBlock('a.js', "const x = 1;\nconst y = 2;\n");
    $b = writeBlock('b.js', "const   x = 1;\nconst y   = 2;\n");

    // `});` dan `return;` muncul di mana-mana; itu tanda baca, bukan duplikasi.
    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(0);

    unlink($a);
    unlink($b);
});

it('menyebut berkas mana saja yang ikut berduplikasi', function () {
    $a = writeBlock('a.js', sixIdenticalLines());
    $b = writeBlock('b.js', sixIdenticalLines());

    $shared = DuplicationScanner::filesSharing([$a, $b]);

    expect($shared)->toHaveKey($a)
        ->and($shared[$a])->toBe(1);

    unlink($a);
    unlink($b);
});
```

- [ ] **Step 3: Jalankan keduanya, pastikan gagal**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Quality/
```
Expected: FAIL — `CommentMetrics` dan `DuplicationScanner` not found

- [ ] **Step 4: Tulis `VueMetrics`**

```php
<?php

namespace App\Support\Quality;

use RuntimeException;

/**
 * Mengukur berkas `.vue`. Hanya LOC.
 *
 * Kompleksitas `<script setup>` butuh parser TypeScript, sementara diagnosis
 * menunjukkan bloat Vue hidup di ukuran berkas — bukan di percabangan.
 */
final class VueMetrics
{
    public static function loc(string $path): int
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException("Berkas tidak terbaca: {$path}");
        }

        return substr_count($source, "\n") + 1;
    }
}
```

- [ ] **Step 5: Tulis `CommentMetrics`**

```php
<?php

namespace App\Support\Quality;

/**
 * Menghitung baris prosa komentar terpanjang dalam satu berkas.
 *
 * Anotasi dikecualikan: PHPStan level 10 menuntut docblock yang kaya, dan
 * membatasinya akan membuat dua aturan repo ini saling berkelahi.
 */
final class CommentMetrics
{
    private const MIN_PROSE_LENGTH = 4;

    public static function longestProseRun(string $path): int
    {
        $source = file_get_contents($path);

        if ($source === false) {
            return 0;
        }

        $longest = 0;
        $current = 0;

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $trimmed = trim($line);

            if (!self::isCommentLine($trimmed)) {
                $longest = max($longest, $current);
                $current = 0;

                continue;
            }

            if (self::isProse($trimmed)) {
                $current++;
            }
        }

        return max($longest, $current);
    }

    private static function isCommentLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '/*');
    }

    /** Baris prosa: ada isinya, bukan anotasi, bukan pembuka atau penutup blok. */
    private static function isProse(string $trimmed): bool
    {
        $body = trim(preg_replace('#^(//+|/\*+|\*+/?)#', '', $trimmed) ?? '');

        if (strlen($body) < self::MIN_PROSE_LENGTH) {
            return false;
        }

        return !str_starts_with($body, '@');
    }
}
```

- [ ] **Step 6: Tulis `DuplicationScanner`**

```php
<?php

namespace App\Support\Quality;

/**
 * Mencari blok enam baris yang muncul di lebih dari satu berkas.
 *
 * Enam dipilih karena lebih pendek dari itu menangkap kebetulan: urutan
 * `import` dan penutup kurung muncul identik di mana-mana.
 */
final class DuplicationScanner
{
    private const WINDOW = 6;

    private const MIN_LINE_LENGTH = 12;

    /** @param list<string> $paths */
    public static function countSharedBlocks(array $paths): int
    {
        return count(array_filter(
            self::index($paths),
            static fn (array $owners): bool => count($owners) > 1,
        ));
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, int> path => jumlah bloknya yang juga ada di berkas lain
     */
    public static function filesSharing(array $paths): array
    {
        $shared = [];

        foreach (self::index($paths) as $owners) {
            if (count($owners) < 2) {
                continue;
            }

            foreach ($owners as $path) {
                $shared[$path] = ($shared[$path] ?? 0) + 1;
            }
        }

        return $shared;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, list<string>> hash blok => berkas yang memuatnya
     */
    private static function index(array $paths): array
    {
        $index = [];

        foreach ($paths as $path) {
            foreach (self::blocks($path) as $hash) {
                if (!isset($index[$hash])) {
                    $index[$hash] = [];
                }

                if (!in_array($path, $index[$hash], true)) {
                    $index[$hash][] = $path;
                }
            }
        }

        return $index;
    }

    /** @return list<string> */
    private static function blocks(string $path): array
    {
        $source = file_get_contents($path);

        if ($source === false) {
            return [];
        }

        $lines = self::significantLines($source);
        $blocks = [];

        for ($i = 0; $i + self::WINDOW <= count($lines); $i++) {
            $blocks[] = md5(implode(' ', array_slice($lines, $i, self::WINDOW)));
        }

        return $blocks;
    }

    /** @return list<string> */
    private static function significantLines(string $source): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $normalised = trim(preg_replace('/\s+/', ' ', $line) ?? '');

            if (strlen($normalised) <= self::MIN_LINE_LENGTH) {
                continue;
            }

            if (str_starts_with($normalised, '//') || str_starts_with($normalised, '*')) {
                continue;
            }

            $lines[] = $normalised;
        }

        return $lines;
    }
}
```

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Quality/
```
Expected: PASS, 13 test (5 dari Task 1 + 4 komentar + 4 duplikasi)

- [ ] **Step 8: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/phpstan analyse app/Support/Quality --level=10 --no-progress
git add app/Support/Quality tests/Unit/Quality
git commit -m "feat(quality): ukur berkas Vue, prosa komentar, dan blok duplikat"
```

---

### Task 3: Pembaca berkas plafon

**Files:**
- Create: `app/Support/Quality/BudgetFile.php`
- Test: `tests/Unit/Quality/BudgetFileTest.php`

**Interfaces:**
- Consumes: —
- Produces: `BudgetFile::__construct(string $path)`, `BudgetFile::value(string $key): int`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Unit/Quality/BudgetFileTest.php

use App\Support\Quality\BudgetFile;
use RuntimeException;

function writeBudget(string $contents): string
{
    $path = sys_get_temp_dir().'/budget_'.uniqid().'.txt';
    file_put_contents($path, $contents);

    return $path;
}

it('membaca angka dan mengabaikan komentar', function () {
    $path = writeBudget(<<<'TXT'
    # komentar
    hard.php.file.loc:     200

    max.legacy.files:      112
    TXT);

    $budget = new BudgetFile($path);

    expect($budget->value('hard.php.file.loc'))->toBe(200)
        ->and($budget->value('max.legacy.files'))->toBe(112);

    unlink($path);
});

it('menolak kunci yang tidak ada alih-alih mengembalikan nol', function () {
    // Nol diam-diam adalah cara plafon jadi longgar tanpa ada yang memutuskan.
    $path = writeBudget("hard.php.file.loc: 200\n");

    expect(fn () => (new BudgetFile($path))->value('missing.key'))
        ->toThrow(RuntimeException::class);

    unlink($path);
});

it('menolak baris yang bentuknya tidak dikenali', function () {
    $path = writeBudget("hard.php.file.loc = 200\n");

    expect(fn () => (new BudgetFile($path))->value('hard.php.file.loc'))
        ->toThrow(RuntimeException::class);

    unlink($path);
});

it('menolak berkas yang tidak terbaca', function () {
    expect(fn () => (new BudgetFile('/tidak/ada.txt'))->value('any.key'))
        ->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Quality/BudgetFileTest.php
```
Expected: FAIL — `Class "App\Support\Quality\BudgetFile" not found`

- [ ] **Step 3: Tulis `BudgetFile`**

```php
<?php

namespace App\Support\Quality;

use RuntimeException;

/**
 * Membaca `quality-budget.txt`.
 *
 * Kunci yang hilang dilempar, bukan dikembalikan sebagai nol: nol diam-diam
 * adalah cara sebuah plafon jadi longgar tanpa ada yang memutuskannya.
 */
final class BudgetFile
{
    private const LINE_PATTERN = '/^([a-z][a-z0-9.]*)\s*:\s*(\d+)$/';

    /** @var array<string, int>|null */
    private ?array $values = null;

    public function __construct(private readonly string $path) {}

    public function value(string $key): int
    {
        $values = $this->values ??= $this->parse();

        if (!array_key_exists($key, $values)) {
            throw new RuntimeException(
                "Kunci \"{$key}\" tidak ada di {$this->path}. Tambahkan barisnya secara sadar."
            );
        }

        return $values[$key];
    }

    /** @return array<string, int> */
    private function parse(): array
    {
        $contents = is_readable($this->path) ? file_get_contents($this->path) : false;

        if ($contents === false) {
            throw new RuntimeException("Berkas plafon tidak terbaca: {$this->path}");
        }

        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match(self::LINE_PATTERN, $trimmed, $matches) !== 1) {
                throw new RuntimeException("Baris tidak dikenali di {$this->path}: \"{$trimmed}\"");
            }

            $values[$matches[1]] = (int) $matches[2];
        }

        return $values;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Quality/BudgetFileTest.php
```
Expected: PASS, 4 test

- [ ] **Step 5: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app/Support/Quality tests/Unit/Quality
git commit -m "feat(quality): baca berkas plafon, tolak kunci yang hilang"
```

---

### Task 4: Terbitkan kedua berkas plafon

Daftarnya **dihasilkan**, bukan diketik: daftar yang diketik tangan meleset sejak hari
pertama, dan plafon yang meleset menghabiskan kepercayaan pada pagarnya.

**Files:**
- Create: `tools/generate-quality-legacy.php`
- Create: `quality-budget.txt`
- Create: `quality-legacy.txt`

**Interfaces:**
- Consumes: `CodeMetrics::forFile()`, `VueMetrics::loc()`, `CommentMetrics::longestProseRun()`
- Produces: `quality-legacy.txt` berisi path relatif, satu per baris, terurut

- [ ] **Step 1: Tulis pembangkit**

```php
<?php

// tools/generate-quality-legacy.php
//
// Dijalankan SEKALI saat pagar dipasang. Setelah itu daftarnya hanya menyusut
// lewat tangan manusia.

require __DIR__.'/../vendor/autoload.php';

use App\Support\Quality\CodeMetrics;
use App\Support\Quality\CommentMetrics;
use App\Support\Quality\VueMetrics;

$root = dirname(__DIR__);

$limits = [
    'php.file.loc' => 200,
    'vue.file.loc' => 250,
    'method.loc' => 40,
    'method.cc' => 8,
    'method.params' => 4,
    'method.returns' => 4,
    'comment.prose' => 3,
];

/** @return list<string> */
$scan = static function (string $dir, string $extension) use ($root): array {
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === $extension) {
            $paths[] = str_replace("{$root}/", '', $file->getPathname());
        }
    }

    sort($paths);

    return $paths;
};

$legacy = [];

foreach ($scan('app', 'php') as $path) {
    $metrics = CodeMetrics::forFile("{$root}/{$path}");

    $exceeds = $metrics['loc'] > $limits['php.file.loc']
        || CommentMetrics::longestProseRun("{$root}/{$path}") > $limits['comment.prose'];

    foreach ($metrics['methods'] as $method) {
        $exceeds = $exceeds
            || $method->loc > $limits['method.loc']
            || $method->complexity > $limits['method.cc']
            || $method->parameters > $limits['method.params']
            || $method->returns > $limits['method.returns'];
    }

    if ($exceeds) {
        $legacy[] = $path;
    }
}

foreach ($scan('resources/js', 'vue') as $path) {
    if (VueMetrics::loc("{$root}/{$path}") > $limits['vue.file.loc']) {
        $legacy[] = $path;
    }
}

sort($legacy);

$header = <<<'TXT'
# Berkas yang belum memenuhi ambang keras di quality-budget.txt.
#
# Daftar ini HANYA BOLEH MENYUSUT. Hapus satu baris setiap kali berkasnya selesai
# dibedah, lalu turunkan max.legacy.files. Assertion 2 menolak berkas yang sudah
# memenuhi ambang tapi masih terdaftar, jadi keduanya tidak bisa berselisih.
#
# Inilah antrean kerja refactor. Ambil dari atas.

TXT;

file_put_contents("{$root}/quality-legacy.txt", $header.implode("\n", $legacy)."\n");

echo count($legacy)." berkas masuk daftar\n";
```

- [ ] **Step 2: Jalankan pembangkitnya**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  php tools/generate-quality-legacy.php
```
Expected: angka di kisaran **112–160**. Spec §1.2 mengukur 112 tanpa aturan komentar;
aturan prosa 3 baris menambah berkas yang ukurannya sudah patuh tapi komentarnya panjang.

Catat angka aktualnya — ia jadi nilai `max.legacy.files` di Step 3. **Jangan** memakai
112 kalau hasilnya berbeda; plafon yang tidak cocok dengan daftarnya membuat assertion 4
gagal sejak hari pertama.

- [ ] **Step 3: Tulis `quality-budget.txt`**

Ganti `<HASIL_STEP_2>` dengan angka dari Step 2.

```
# Plafon kualitas kode. Ditegakkan tests/Unit/Architecture/QualityBudgetTest.php.
#
# Angka hanya boleh TURUN. Menaikkannya berarti menambah utang dan harus dibahas
# eksplisit di PR — aturan yang sama dengan pending.txt.
#
# Rancangannya di docs/design/refactor-plan.md.

# --- Ambang keras: berlaku di luar quality-legacy.txt ---
hard.php.file.loc:     200
hard.vue.file.loc:     250
hard.method.loc:        40
hard.method.cc:          8
hard.method.params:      4
hard.method.returns:     4
hard.comment.prose:      3
hard.contract.leak:      0

# --- Plafon yang menyusut ---
max.legacy.files:      <HASIL_STEP_2>
max.mixed:              48
max.comment.overflow:  234
max.id.nonenglish:     171
max.untyped.json:       22
max.duplicate.blocks: 1670
max.phpstan.default:  1284
```

- [ ] **Step 4: Verifikasi setiap plafon terhadap kenyataan**

Run:
```bash
grep -rn --include='*.php' -E '\bmixed\b' app/ | wc -l                        # 48
grep -rn 'response()->json' app/Http/Controllers/ --include='*.php' | wc -l   # 22
grep -vcE '^\s*(#|$)' quality-legacy.txt                                      # = max.legacy.files
```
Expected: 48, 22, dan jumlah entri sama persis dengan `max.legacy.files`.

- [ ] **Step 5: Commit**

```bash
git add quality-budget.txt quality-legacy.txt tools/generate-quality-legacy.php
git commit -m "feat(quality): terbitkan plafon dan antrean kerja refactor"
```

---

### Task 5: Test penegak

**Files:**
- Create: `tests/Unit/Architecture/QualityBudgetTest.php`

**Interfaces:**
- Consumes: `CodeMetrics`, `VueMetrics`, `CommentMetrics`, `DuplicationScanner`,
  `BudgetFile`, `App\Support\Bdd\LedgerFile`
- Produces: — (terminal)

Assertion 8–9 menyangkut `app/Data/Contract` yang belum ada isinya. Keduanya tetap
dipasang sekarang: memasangnya belakangan meninggalkan jendela ketika kontrak pertama
lahir tanpa penjaga.

- [ ] **Step 1: Tulis test**

```php
<?php

// tests/Unit/Architecture/QualityBudgetTest.php
//
// Pagar kualitas kode. Rancangannya di docs/design/refactor-plan.md.

use App\Support\Bdd\LedgerFile;
use App\Support\Quality\BudgetFile;
use App\Support\Quality\CodeMetrics;
use App\Support\Quality\CommentMetrics;
use App\Support\Quality\DuplicationScanner;
use App\Support\Quality\VueMetrics;

function qualityBudget(): BudgetFile
{
    return new BudgetFile(base_path('quality-budget.txt'));
}

/** @return list<string> */
function legacyList(): array
{
    return (new LedgerFile(base_path('quality-legacy.txt')))->entries();
}

/** @return list<string> */
function scanPaths(string $dir, string $extension): array
{
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === $extension) {
            $paths[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    sort($paths);

    return $paths;
}

/** @return list<string> */
function allSourcePaths(): array
{
    return [...scanPaths('app', 'php'), ...scanPaths('resources/js', 'vue')];
}

/** @return list<string> alasan pelanggaran; kosong berarti memenuhi ambang */
function hardViolations(string $path, BudgetFile $budget): array
{
    $reasons = [];
    $absolute = base_path($path);

    if (str_ends_with($path, '.vue')) {
        if (VueMetrics::loc($absolute) > $budget->value('hard.vue.file.loc')) {
            $reasons[] = 'ukuran berkas';
        }

        return $reasons;
    }

    $metrics = CodeMetrics::forFile($absolute);

    if ($metrics['loc'] > $budget->value('hard.php.file.loc')) {
        $reasons[] = 'ukuran berkas';
    }

    if (CommentMetrics::longestProseRun($absolute) > $budget->value('hard.comment.prose')) {
        $reasons[] = 'prosa komentar terlalu panjang';
    }

    foreach ($metrics['methods'] as $method) {
        if ($method->loc > $budget->value('hard.method.loc')) {
            $reasons[] = "{$method->name}() terlalu panjang";
        }

        if ($method->complexity > $budget->value('hard.method.cc')) {
            $reasons[] = "{$method->name}() terlalu kompleks";
        }

        if ($method->parameters > $budget->value('hard.method.params')) {
            $reasons[] = "{$method->name}() terlalu banyak parameter";
        }

        if ($method->returns > $budget->value('hard.method.returns')) {
            $reasons[] = "{$method->name}() terlalu banyak return";
        }
    }

    return $reasons;
}

it('assertion 1 — berkas di luar daftar memenuhi seluruh ambang keras', function () {
    $budget = qualityBudget();
    $legacy = legacyList();
    $problems = [];

    foreach (allSourcePaths() as $path) {
        if (in_array($path, $legacy, true)) {
            continue;
        }

        $reasons = hardViolations($path, $budget);

        if ($reasons !== []) {
            $problems[] = $path.' — '.implode(', ', array_unique($reasons));
        }
    }

    expect($problems)->toBe([], "Melewati ambang keras:\n".implode("\n", $problems));
});

it('assertion 2 — daftar tidak memuat berkas yang sudah memenuhi ambang', function () {
    $budget = qualityBudget();
    $resolved = [];

    foreach (legacyList() as $path) {
        if (file_exists(base_path($path)) && hardViolations($path, $budget) === []) {
            $resolved[] = $path;
        }
    }

    expect($resolved)->toBe(
        [],
        "Sudah memenuhi ambang. Hapus dari quality-legacy.txt dan turunkan max.legacy.files:\n"
            .implode("\n", $resolved)
    );
});

it('assertion 3 — daftar tidak memuat berkas yang tidak ada', function () {
    $ghosts = array_values(array_filter(
        legacyList(),
        static fn (string $path): bool => !file_exists(base_path($path)),
    ));

    expect($ghosts)->toBe([], 'Berkas tidak ada, hapus barisnya: '.implode(', ', $ghosts));
});

it('assertion 4 — daftar legacy tidak melewati plafonnya', function () {
    expect(count(legacyList()))
        ->toBeLessThanOrEqual(qualityBudget()->value('max.legacy.files'));
});

it('assertion 5 — mixed tidak melewati plafonnya', function () {
    $total = 0;

    foreach (scanPaths('app', 'php') as $path) {
        $source = file_get_contents(base_path($path));
        $total += preg_match_all('/\bmixed\b/', $source === false ? '' : $source);
    }

    expect($total)->toBeLessThanOrEqual(qualityBudget()->value('max.mixed'));
});

it('assertion 6 — blok duplikat tidak melewati plafonnya', function () {
    $absolute = array_map(static fn (string $path): string => base_path($path), allSourcePaths());

    expect(DuplicationScanner::countSharedBlocks($absolute))
        ->toBeLessThanOrEqual(qualityBudget()->value('max.duplicate.blocks'));
});

it('assertion 7 — berkas non-legacy tidak berduplikasi satu sama lain', function () {
    $legacy = legacyList();

    $clean = array_values(array_filter(
        allSourcePaths(),
        static fn (string $path): bool => !in_array($path, $legacy, true),
    ));

    $shared = DuplicationScanner::filesSharing(
        array_map(static fn (string $path): string => base_path($path), $clean)
    );

    // Duplikasi dengan berkas legacy dibiarkan: yang legacy memang akan hilang.
    expect(array_keys($shared))->toBe(
        [],
        'Blok duplikat di berkas non-legacy: '.implode(', ', array_keys($shared))
    );
});

it('assertion 8 — Data\\Contract tidak membocorkan model', function () {
    $leaks = [];

    foreach (scanPaths('app/Data', 'php') as $path) {
        if (!str_contains($path, '/Contract/')) {
            continue;
        }

        $source = file_get_contents(base_path($path));
        $source = $source === false ? '' : $source;

        if (str_contains($source, 'App\\Models\\')) {
            $leaks[] = "{$path} — mengimpor Model";
        }

        if (preg_match('/->(toArray|only)\s*\(/', $source) === 1) {
            $leaks[] = "{$path} — menyebar atribut model";
        }
    }

    expect($leaks)->toBe([], implode("\n", $leaks));
});

it('assertion 9 — Data\\Contract tidak menyusun Data\\View', function () {
    $inverted = [];

    foreach (scanPaths('app/Data', 'php') as $path) {
        if (!str_contains($path, '/Contract/')) {
            continue;
        }

        $source = file_get_contents(base_path($path));

        if ($source !== false && str_contains($source, 'App\\Data\\View\\')) {
            $inverted[] = $path;
        }
    }

    expect($inverted)->toBe(
        [],
        'Kontrak menyusun payload layar; arahnya harus sebaliknya: '.implode(', ', $inverted)
    );
});

it('assertion 10 — response()->json tidak melewati plafonnya', function () {
    $total = 0;

    foreach (scanPaths('app/Http', 'php') as $path) {
        $source = file_get_contents(base_path($path));
        $total += substr_count($source === false ? '' : $source, 'response()->json');
    }

    expect($total)->toBeLessThanOrEqual(qualityBudget()->value('max.untyped.json'));
});

it('assertion 11 — prosa komentar berlebih tidak melewati plafonnya', function () {
    $budget = qualityBudget();
    $overflow = 0;

    foreach (scanPaths('app', 'php') as $path) {
        if (CommentMetrics::longestProseRun(base_path($path)) > $budget->value('hard.comment.prose')) {
            $overflow++;
        }
    }

    expect($overflow)->toBeLessThanOrEqual($budget->value('max.comment.overflow'));
});

it('assertion 12 — identifier non-Inggris tidak melewati plafonnya', function () {
    // Daftar kata, bukan deteksi bahasa: ia mencegah kemunduran pada kata yang
    // sudah dikenal. Penamaan selebihnya urusan review.
    $words = [
        'tercatat', 'terdampak', 'sebelum', 'sesudah', 'pemakai', 'penghalang',
        'rincian', 'institusi', 'sudahAda', 'hasil', 'jumlah', 'berkas', 'daftar',
        'melanggar', 'alasan', 'bersih', 'bocor', 'gemuk', 'beres', 'masalah',
        'undangan', 'anggota', 'mitra', 'keadaan', 'cakupan', 'kuota', 'tujuan',
    ];

    $pattern = '/\$('.implode('|', $words).')\b/i';
    $total = 0;

    foreach (scanPaths('app', 'php') as $path) {
        $source = file_get_contents(base_path($path));
        $total += preg_match_all($pattern, $source === false ? '' : $source);
    }

    expect($total)->toBeLessThanOrEqual(qualityBudget()->value('max.id.nonenglish'));
});
```

- [ ] **Step 2: Jalankan test, pastikan SELURUHNYA lulus**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Architecture/QualityBudgetTest.php
```
Expected: PASS, 12 test.

Kalau assertion 1 atau 2 gagal, pengukur Task 1–2 dan pembangkit Task 4 tidak sepakat.
**Perbaiki pengukurnya, jangan tambah berkas ke daftar** — daftar yang ditambah demi
menyenangkan test adalah utang yang disembunyikan.

Kalau assertion 11 atau 12 gagal, plafonnya salah ketik: sesuaikan dengan angka yang
dilaporkan pesan gagalnya, **bukan** dengan menaikkannya sembarangan.

- [ ] **Step 3: Buktikan pagarnya menggigit**

```bash
cat > app/Actions/FenceProbe.php <<'PHP'
<?php

namespace App\Actions;

class FenceProbe
{
    public function tooComplex(int $a, int $b, int $c, int $d, int $e): string
    {
        if ($a && $b) { return 'a'; }
        if ($c || $d) { return 'b'; }
        foreach ([1] as $x) { if ($x) { return 'c'; } }
        while ($e) { return 'd'; }

        return 'e';
    }
}
PHP

docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Architecture/QualityBudgetTest.php
```
Expected: **FAIL** pada assertion 1, menyebut `tooComplex()` dengan alasan
`terlalu kompleks` dan `terlalu banyak parameter`.

```bash
rm app/Actions/FenceProbe.php
```

Pagar yang tidak pernah dibuktikan menggigit adalah pagar yang tidak ada.

- [ ] **Step 4: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
npm run quality
```
Expected: keduanya hijau; jumlah test bertambah 25 (13 unit + 12 assertion).

- [ ] **Step 5: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add tests/Unit/Architecture
git commit -m "feat(quality): tegakkan plafon lewat dua belas assertion arsitektur"
```

---

### Task 6: Naikkan tier ketat ke level 10

Tiga penyebab, 18 error. Salah satunya bug asli.

**Files:**
- Modify: `phpstan-strict.neon:19` (level 9 → 10)
- Modify: `app/Notifications/Concerns/UsesConfiguredChannels.php`
- Modify: `app/Actions/Review/BuildReviewerDashboard.php:43`
- Modify: `app/Notifications/ReviewerAssignedNotification.php:32`

**Interfaces:**
- Consumes: —
- Produces: seluruh `app/` di luar `excludePaths` tunduk level 10

- [ ] **Step 1: Naikkan levelnya dan lihat kegagalannya**

```bash
sed -i 's/^    level: 9$/    level: 10/' phpstan-strict.neon

docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/phpstan analyse -c phpstan-strict.neon --memory-limit=1G --no-progress
```
Expected: **FAIL**, 18 error dari 3 berkas.

- [ ] **Step 2: Perbaiki `UsesConfiguredChannels` (16 error)**

`static::EVENT_TYPE` bertipe `mixed` karena trait tidak bisa menjanjikan konstanta itu
ada. Penjagaan runtime eksplisit — pola yang sama dengan `CountReferenceEntityUsage`.

```php
<?php

namespace App\Notifications\Concerns;

use App\Support\Notification\NotificationChannels;
use LogicException;

/**
 * BR-NOT-06 — channel diambil dari konfigurasi, bukan ditulis di kelasnya.
 *
 * Kelas yang lupa menyatakan EVENT_TYPE ditangkap NotificationChannelTest.
 */
trait UsesConfiguredChannels
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $eventType = static::EVENT_TYPE;

        // Trait tidak bisa menjanjikan konstanta ini ada maupun bertipe string.
        if (!is_string($eventType)) {
            throw new LogicException(static::class.' harus menyatakan EVENT_TYPE bertipe string.');
        }

        return NotificationChannels::for($eventType);
    }
}
```

- [ ] **Step 3: Perbaiki `BuildReviewerDashboard` (1 error)**

Parameter closure tanpa tipe jadi `mixed`.

Ganti baris 43 dari:
```php
'formSubmission' => fn ($q) => $q->with([
```
menjadi:
```php
'formSubmission' => fn (Builder $query) => $query->with([
```

Tambahkan import di bagian `use`:
```php
use Illuminate\Contracts\Database\Eloquent\Builder;
```

- [ ] **Step 4: Perbaiki `ReviewerAssignedNotification` (1 error) — ini bug asli**

`form_submissions` **tidak punya kolom `title`**. Verifikasi dulu:

```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres -e DB_DATABASE=simpas_test -e HOME=/tmp \
  simpas-ci:8.4 php -r '
require "vendor/autoload.php"; $a = require "bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo implode(", ", Illuminate\Support\Facades\Schema::getColumnListing("form_submissions"));'
```
Expected: daftar kolom **tanpa** `title`.

Jadi `$this->submission->title` selamanya `null` dan barisnya selalu jatuh ke fallback.
Perbaikannya membuang akses mati itu, bukan menambah anotasi.

Ganti baris 32 dari:
```php
->line('Judul: '.($this->submission->title ?? 'Pengajuan #'.$this->submission->id));
```
menjadi:
```php
->line('Pengajuan #'.$this->submission->id);
```

- [ ] **Step 5: Jalankan ulang, pastikan bersih**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/phpstan analyse -c phpstan-strict.neon --memory-limit=1G --no-progress
```
Expected: `[OK] No errors`

- [ ] **Step 6: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau. Test notifikasi yang memeriksa isi email mungkin ikut berubah karena
Step 4 — kalau ada yang gagal, perbaiki **ekspektasi test**-nya, bukan mengembalikan
akses `title` yang mati.

- [ ] **Step 7: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add phpstan-strict.neon app/Notifications app/Actions/Review
git commit -m "feat(quality): naikkan tier ketat ke level 10, buang akses kolom yang tak ada"
```

---

# GELOMBANG 1 — Beri bentuk pada data

Target: `max.mixed` 48 → 12. Dua belas sisanya melintasi batas yang bukan milik kita
(tanda tangan Laravel, aksesor JSON generik, kolom schemaless) dan **dibiarkan dengan
sadar**.

---

### Task 7: Payload notifikasi jadi kontrak bertipe

Enam belas `toArray(): array<string, mixed>` dengan bentuk yang sebenarnya sudah tetap:
`type`, `title`, `message` ada di keenam belasnya, sisanya konteks opsional.

Payload ini **dibaca layar notifikasi** dan kelak API, jadi tempatnya `Data\Contract`.

**Files:**
- Create: `app/Data/Contract/NotificationPayload.php`
- Test: `tests/Unit/Data/NotificationPayloadTest.php`
- Modify: 16 berkas di `app/Notifications/`

**Interfaces:**
- Consumes: —
- Produces: `NotificationPayload::__construct(string $type, string $title, string $message, array<string, int|string|null> $context = [])`
- Produces: `NotificationPayload::toArray(): array<string, int|string|null>`

Dipakai kelas readonly biasa, **bukan** `spatie/laravel-data`: fitur casting dan
validasi paket itu tidak dipakai di sini, sementara tipe kembalian `toArray()`-nya
`array` polos dan akan mengembalikan `mixed` yang baru saja dibuang.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Unit/Data/NotificationPayloadTest.php

use App\Data\Contract\NotificationPayload;

it('menyusun tiga kunci wajib', function () {
    $payload = new NotificationPayload(
        type: 'reviewer_assigned',
        title: 'Penugasan review baru',
        message: 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
    );

    expect($payload->toArray())->toBe([
        'type' => 'reviewer_assigned',
        'title' => 'Penugasan review baru',
        'message' => 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
    ]);
});

it('menggabungkan konteks tambahan', function () {
    $payload = new NotificationPayload(
        type: 'monev_stage_opened',
        title: 'Siklus monev dibuka',
        message: 'Batas pengisian 30 hari.',
        context: ['form_submission_id' => 7, 'form_phase_detail_id' => 3],
    );

    expect($payload->toArray())
        ->toHaveKey('form_submission_id', 7)
        ->and($payload->toArray())->toHaveKey('type', 'monev_stage_opened');
});

it('tidak membiarkan konteks menimpa kunci wajib', function () {
    // Konteks yang bisa menimpa `type` membuat layar notifikasi salah merender.
    $payload = new NotificationPayload(
        type: 'export_completed',
        title: 'Ekspor selesai',
        message: 'Siap diunduh.',
        context: ['type' => 'palsu'],
    );

    expect($payload->toArray()['type'])->toBe('export_completed');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Data/NotificationPayloadTest.php
```
Expected: FAIL — `Class "App\Data\Contract\NotificationPayload" not found`

- [ ] **Step 3: Tulis DTO-nya**

```php
<?php

namespace App\Data\Contract;

/**
 * BR-NOT-01 — bentuk satu notifikasi in-app.
 *
 * Kontrak, bukan payload layar: ia dibaca layar notifikasi dan kelak API,
 * jadi mengubah kuncinya adalah perubahan yang memutus konsumen.
 */
final class NotificationPayload
{
    /** @param array<string, int|string|null> $context */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        // Kunci wajib ditulis belakangan supaya konteks tidak bisa menimpanya.
        return [
            ...$this->context,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Data/NotificationPayloadTest.php
```
Expected: PASS, 3 test

- [ ] **Step 5: Alihkan keenam belas notifikasi**

Untuk **setiap** berkas di `app/Notifications/*.php` yang punya `toArray()`, ganti
polanya. Contoh `ReviewerAssignedNotification`:

Dari:
```php
    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reviewer_assigned',
            'form_submission_id' => $this->submission->id,
            'title' => 'Penugasan review baru',
            'message' => 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
        ];
    }
```

Menjadi:
```php
    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'reviewer_assigned',
            title: 'Penugasan review baru',
            message: 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
            context: ['form_submission_id' => $this->submission->id],
        ))->toArray();
    }
```

Tambahkan `use App\Data\Contract\NotificationPayload;` di setiap berkas.

Daftar berkasnya:
```bash
grep -rl 'public function toArray' app/Notifications/*.php
```

- [ ] **Step 6: Verifikasi `mixed` berkurang**

Run:
```bash
grep -rn --include='*.php' -E '\bmixed\b' app/ | wc -l
```
Expected: **32** (48 − 16)

- [ ] **Step 7: Turunkan plafonnya dan jalankan gerbang**

```bash
sed -i 's/^max.mixed:              48$/max.mixed:              32/' quality-budget.txt

docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau. Test notifikasi yang memeriksa isi `data` tetap lulus — bentuk
keluarannya tidak berubah, hanya cara menyusunnya.

- [ ] **Step 8: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app/Data app/Notifications quality-budget.txt tests/Unit/Data
git commit -m "feat(notification): jadikan payload notifikasi kontrak bertipe"
```

---

### Task 8: Masukan Action jadi DTO Request

Empat Action menerima `array<string, mixed> $attributes`. Itulah tempat yang dimaksud
`Data\Request` di spec §2.

**Files:**
- Create: `app/Data/Request/SchemeInput.php`
- Create: `app/Data/Request/ExportFilter.php`
- Create: `app/Data/Request/ReferenceEntityInput.php`
- Test: `tests/Unit/Data/RequestDataTest.php`
- Modify: `app/Actions/Scheme/SaveScheme.php`
- Modify: `app/Actions/Export/RequestExport.php`
- Modify: `app/Actions/Config/SaveReferenceEntity.php`

**Interfaces:**
- Consumes: —
- Produces: `SchemeInput::__construct(string $name, string $code, int $maxBudget, int $maxMembers, int $durationMonths, ?array $rules)`
- Produces: `ExportFilter::__construct(?string $from, ?string $to, ?int $schemeId)`
- Produces: `ReferenceEntityInput::__construct(string $name)`

`SaveResearchOutput` **tidak** masuk daftar: `@param array{output_type: ...}` miliknya
sudah berbentuk array shape, bukan `mixed`.

- [ ] **Step 1: Periksa bentuk sebenarnya sebelum menulis DTO**

```bash
grep -n "validate(\[" -A 10 app/Http/Controllers/SchemeController.php | head -16
grep -n "validate(\[" -A 8 app/Http/Controllers/Export/ExportController.php | head -12
```

Bentuk DTO **mengikuti aturan validasi yang sudah ada**, bukan ditebak. Kalau ada
ketidakcocokan antara contoh di bawah dan hasil perintah ini, yang benar hasil
perintahnya — sesuaikan DTO-nya dan catat selisihnya di pesan commit.

- [ ] **Step 2: Tulis test yang gagal**

```php
<?php

// tests/Unit/Data/RequestDataTest.php

use App\Data\Request\ExportFilter;
use App\Data\Request\ReferenceEntityInput;
use App\Data\Request\SchemeInput;

it('menyusun masukan skema dari array tervalidasi', function () {
    $input = SchemeInput::fromArray([
        'name' => 'Penelitian Dasar',
        'code' => 'PD',
        'max_budget' => 10_000_000,
        'max_members' => 4,
        'duration_months' => 12,
        'rules' => ['min_reviewer_count' => 2],
    ]);

    expect($input->name)->toBe('Penelitian Dasar')
        ->and($input->maxBudget)->toBe(10_000_000)
        ->and($input->rules)->toBe(['min_reviewer_count' => 2]);
});

it('membiarkan rules kosong sebagai null', function () {
    $input = SchemeInput::fromArray([
        'name' => 'Penelitian Terapan',
        'code' => 'PT',
        'max_budget' => 5_000_000,
        'max_members' => 3,
        'duration_months' => 6,
    ]);

    expect($input->rules)->toBeNull();
});

it('menyusun penyaring ekspor yang seluruhnya opsional', function () {
    expect(ExportFilter::fromArray([])->schemeId)->toBeNull();

    expect(ExportFilter::fromArray(['scheme_id' => 3])->schemeId)->toBe(3);
});

it('menyusun masukan entity acuan', function () {
    expect(ReferenceEntityInput::fromArray(['name' => 'Tipe Baru'])->name)
        ->toBe('Tipe Baru');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 \
  ./vendor/bin/pest tests/Unit/Data/RequestDataTest.php
```
Expected: FAIL — ketiga kelas not found

- [ ] **Step 4: Tulis ketiga DTO**

```php
<?php

namespace App\Data\Request;

/** BR-SCH-01 — masukan tervalidasi untuk membuat atau menyunting skema. */
final class SchemeInput
{
    /** @param array<string, int|string|bool|list<string>>|null $rules */
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $maxBudget,
        public readonly int $maxMembers,
        public readonly int $durationMonths,
        public readonly ?array $rules = null,
    ) {}

    /** @param array<string, mixed> $validated hasil FormRequest, bukan input mentah */
    public static function fromArray(array $validated): self
    {
        $rules = $validated['rules'] ?? null;

        return new self(
            name: is_string($validated['name'] ?? null) ? $validated['name'] : '',
            code: is_string($validated['code'] ?? null) ? $validated['code'] : '',
            maxBudget: is_int($validated['max_budget'] ?? null) ? $validated['max_budget'] : 0,
            maxMembers: is_int($validated['max_members'] ?? null) ? $validated['max_members'] : 0,
            durationMonths: is_int($validated['duration_months'] ?? null) ? $validated['duration_months'] : 0,
            rules: is_array($rules) ? $rules : null,
        );
    }
}
```

```php
<?php

namespace App\Data\Request;

/** BR-RPT-02 — penyaring ekspor; seluruhnya opsional. */
final class ExportFilter
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $schemeId = null,
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromArray(array $validated): self
    {
        return new self(
            from: is_string($validated['from'] ?? null) ? $validated['from'] : null,
            to: is_string($validated['to'] ?? null) ? $validated['to'] : null,
            schemeId: is_int($validated['scheme_id'] ?? null) ? $validated['scheme_id'] : null,
        );
    }
}
```

```php
<?php

namespace App\Data\Request;

/** BR-SC-02 — masukan entity acuan; hanya nama, kodenya diturunkan. */
final class ReferenceEntityInput
{
    public function __construct(public readonly string $name) {}

    /** @param array<string, mixed> $validated */
    public static function fromArray(array $validated): self
    {
        $name = $validated['name'] ?? null;

        return new self(is_string($name) ? $name : '');
    }
}
```

- [ ] **Step 5: Alihkan ketiga Action**

Ganti tanda tangan `handle()` dari `array $attributes` jadi DTO-nya, lalu perbarui
pemanggil di controller agar memakai `::fromArray($validated)`.

Contoh `SaveScheme`:
```php
public function handle(SchemeInput $input, ?Scheme $scheme = null): Scheme
```

Pemanggilnya di `SchemeController`:
```php
SaveScheme::run(SchemeInput::fromArray($validated), $scheme);
```

Hapus juga docblock `@param array<string, mixed> $attributes` dan `@method static ...`
yang menyebut `array<string, mixed>` — keduanya termasuk 48 kemunculan itu.

- [ ] **Step 6: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
```
Expected: hijau. Test yang memanggil Action ini langsung perlu disesuaikan ke DTO —
itu perubahan tanda tangan yang memang disengaja.

- [ ] **Step 7: Hitung ulang dan turunkan plafon**

```bash
grep -rn --include='*.php' -E '\bmixed\b' app/ | wc -l
```

`fromArray()` masing-masing masih memuat satu `@param array<string, mixed>`, dan itu
disengaja: ia batas antara hasil `FormRequest` yang memang tak bertipe dan DTO yang
bertipe. Angka akhir diperkirakan **24–27**; pakai angka aktual.

```bash
sed -i "s/^max.mixed:              32$/max.mixed:              <HASIL>/" quality-budget.txt
```

- [ ] **Step 8: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app/Data app/Actions app/Http/Controllers quality-budget.txt tests/Unit/Data
git commit -m "feat(quality): jadikan masukan Action DTO Request bertipe"
```

---

### Task 9: Sempitkan sisanya dan kunci plafon akhir

**Files:**
- Modify: `app/Models/SubmissionPeriod.php:21`
- Modify: `app/Models/OutputTypeDefinition.php:17`
- Modify: `app/Http/Controllers/Config/ReferenceEntityController.php:113,143`
- Modify: `quality-budget.txt`

**Interfaces:**
- Consumes: —
- Produces: `max.mixed` mencapai nilai akhirnya

- [ ] **Step 1: Perbaiki `@property mixed $form_phases`**

Itu bukan tipe, itu menyerah. Relasinya `hasMany`, jadi:

```php
 * @property-read Collection<int, SubmissionPeriodPhase> $form_phases
```

Verifikasi nama relasinya dulu:
```bash
grep -n "form_phases\|submissionPeriodPhases" app/Models/SubmissionPeriod.php
```

- [ ] **Step 2: Persempit `OutputTypeDefinition::$fields`**

Bentuknya sudah diketahui dari `requiredMetadataKeys()`: tiap entri punya `key` dan
`required`.

```php
 * @property array<int, array{key: string, required: bool}>|null $fields
```

Lalu jalankan PHPStan — kalau `requiredMetadataKeys()` jadi mengeluh, itu berarti
bentuk yang dideklarasikan tidak cocok dengan cara ia dipakai. **Perbaiki yang tidak
cocok**, jangan longgarkan kembali deklarasinya.

- [ ] **Step 3: Persempit kembalian `ReferenceEntityController`**

Baris 113 dan 143 mengembalikan `array<string, mixed>`. Bentuknya sudah tetap:
baca methodnya, lalu tulis array shape yang sebenarnya, mis.

```php
 * @return array<int, array{id: int, name: string, is_active: bool|null, usage_count: int}>
```

- [ ] **Step 4: Jalankan gerbang penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 composer run quality
npm run quality
```
Expected: keduanya hijau.

- [ ] **Step 5: Kunci plafon akhir dan catat sisanya**

```bash
grep -rn --include='*.php' -E '\bmixed\b' app/ | sed 's|app/||'
```

Tempel hasilnya sebagai komentar di `quality-budget.txt` tepat di atas `max.mixed`,
lalu setel angkanya ke jumlah aktual. Sisanya harus jatuh ke empat golongan yang
spec §3.1 sebut; kalau ada yang **di luar** keempatnya, ia belum selesai — kerjakan,
jangan didaftarkan.

```
# Sisa mixed yang dibiarkan dengan sadar (spec §3.1):
#   - 6 tanda tangan rules() milik Laravel
#   - 1 aksesor JSON generik Scheme::getRule
#   - 2 parameter AuditTrail::record
#   - 3 kolom JSON schemaless
max.mixed:              <HASIL>
```

- [ ] **Step 6: Commit**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" simpas-ci:8.4 ./vendor/bin/pint -q
git add app quality-budget.txt
git commit -m "feat(quality): sempitkan sisa mixed, kunci plafon akhirnya"
```

---

## Setelah rencana ini

`quality-legacy.txt` kini berisi antrean kerja gelombang 2–6. Rencana untuk gelombang
berikutnya **diturunkan dari berkas itu**, bukan dikarang: ambil sepuluh baris teratas,
periksa apa yang dilanggarnya, tulis tugasnya.

```bash
head -20 quality-legacy.txt
```

Urutan gelombangnya ada di spec §5. Yang berikutnya: gelombang 2 — memindahkan 123
method logic dari model ke `Actions/` dan `Queries/`, dikerjakan kecil-kecil karena ia
menyentuh hampir seluruh codebase dan yang menahannya hanya 820 test yang sudah ada.
