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

    expect($result['loc'])->toBe(8)
        ->and($result['methods'])->toHaveCount(1)
        ->and($result['methods'][0]->name)->toBe('first');

    unlink($path);
});

it('menghitung LOC seperti wc -l untuk berkas yang diakhiri baris baru', function () {
    $path = writeSource("<?php\nclass Sample {}\n");

    expect(CodeMetrics::forFile($path)['loc'])->toBe(2);

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

it('tidak tertipu interpolasi kurung kurawal di dalam string', function () {
    $path = writeSource(<<<'PHP'
    <?php
    class Sample
    {
        public function text(string $path): string
        {
            $message = "Berkas tidak terbaca: {$path}";

            if ($path === '') {
                return "kosong: ${path}";
            }

            return $message;
        }

        public function second(): int
        {
            return 2;
        }
    }
    PHP);

    $methods = CodeMetrics::forFile($path)['methods'];

    expect($methods)->toHaveCount(2)
        ->and($methods[0]->returns)->toBe(2)
        ->and($methods[0]->loc)->toBe(8);

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

it('tidak menganggap impor fungsi sebagai method', function () {
    $path = writeSource(<<<'PHP'
    <?php
    use function Sample\helper;

    class Sample
    {
        public function only(): int
        {
            return helper();
        }
    }
    PHP);

    $methods = CodeMetrics::forFile($path)['methods'];

    expect($methods)->toHaveCount(1)
        ->and($methods[0]->name)->toBe('only');

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
