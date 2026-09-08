<?php

// tests/Unit/Inertia/PageResolutionTest.php

use App\Support\Inertia\RenderedPage;
use App\Support\Inertia\RenderedPageCatalog;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Nama halaman Inertia hanyalah string sampai ia sampai di browser: resolver
 * di resources/js/app.ts memetakannya ke `./Pages/{nama}.vue`. Test feature
 * meng-assert string yang sama dengan yang dikirim controller, jadi nama yang
 * tidak menunjuk berkas mana pun lolos seluruh suite dan baru gagal di layar
 * pengguna. Guard ini yang menangkapnya.
 */
it('setiap halaman yang di-render controller punya berkas Vue', function () {
    $catalog = new RenderedPageCatalog(
        app_path('Http/Controllers'),
        resource_path('js/Pages'),
    );

    $missing = array_map(
        fn (RenderedPage $page) => "{$page->page} ({$page->location()})",
        $catalog->missing(),
    );

    expect($missing)->toBe([], "Halaman Inertia tanpa berkas Vue:\n- ".implode("\n- ", $missing));
});
