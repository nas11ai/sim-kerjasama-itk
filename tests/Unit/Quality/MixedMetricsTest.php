<?php

// tests/Unit/Quality/MixedMetricsTest.php

use App\Support\Quality\MixedMetrics;

it('menghitung mixed di tanda tangan dan di docblock, bukan di komentar prosa', function () {
    $path = sys_get_temp_dir().'/mixed_'.uniqid().'.php';
    file_put_contents($path, <<<'PHP'
    <?php
    class Sample
    {
        /**
         * Deskripsi yang menyebut mixed bukan tipe.
         *
         * @param array<string, mixed> $values
         */
        public function read(array $values, mixed $default): mixed
        {
            // iterator memberi `mixed`, jadi disempitkan dulu
            return $values['mixed'] ?? $default;
        }
    }
    PHP);

    expect(MixedMetrics::count($path))->toBe(3);

    unlink($path);
});
