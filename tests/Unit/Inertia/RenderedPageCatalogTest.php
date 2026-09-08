<?php

// tests/Unit/Inertia/RenderedPageCatalogTest.php

use App\Support\Inertia\RenderedPageCatalog;
use Tests\TestCase;

// Membaca berkas, bukan tabel — sama alasannya dengan tests/Unit/Bdd.
uses(TestCase::class);

function catalogFixture(string $php, string ...$vuePages): RenderedPageCatalog
{
    $root = sys_get_temp_dir().'/rpc-'.uniqid();
    mkdir($root.'/app/Http/Controllers', 0777, true);
    mkdir($root.'/resources/js/Pages', 0777, true);

    file_put_contents($root.'/app/Http/Controllers/DemoController.php', $php);

    foreach ($vuePages as $page) {
        $target = $root.'/resources/js/Pages/'.$page.'.vue';
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, '<template><div /></template>');
    }

    return new RenderedPageCatalog($root.'/app/Http/Controllers', $root.'/resources/js/Pages');
}

it('menemukan Inertia::render dan helper inertia() sekaligus', function () {
    $catalog = catalogFixture(<<<'PHP'
        <?php
        class DemoController
        {
            public function a() { return Inertia::render('Users/IndexPage', []); }
            public function b() { return inertia('Users/EditPage', []); }
        }
        PHP);

    expect(array_map(fn ($p) => $p->page, $catalog->pages()))
        ->toBe(['Users/IndexPage', 'Users/EditPage']);
});

it('mencatat berkas dan baris supaya pesan gagal bisa ditelusuri', function () {
    $catalog = catalogFixture("<?php\n\n\nreturn Inertia::render('Halaman', []);\n");

    $page = $catalog->pages()[0];

    expect($page->line)->toBe(4)
        ->and($page->location())->toBe('Controllers/DemoController.php:4');
});

it('melaporkan halaman yang berkas Vue-nya tidak ada', function () {
    $catalog = catalogFixture(<<<'PHP'
        <?php
        return [Inertia::render('Ada', []), Inertia::render('Hilang', [])];
        PHP, 'Ada');

    expect(array_map(fn ($p) => $p->page, $catalog->missing()))->toBe(['Hilang']);
});

it('menemukan halaman di dalam subdirektori', function () {
    $catalog = catalogFixture(
        "<?php return Inertia::render('Reviewer/EvaluationForm/ShowPage', []);",
        'Reviewer/EvaluationForm/ShowPage'
    );

    expect($catalog->missing())->toBe([]);
});

it('tidak tertipu method lain yang namanya berakhiran inertia', function () {
    $catalog = catalogFixture("<?php return \$this->notInertia('Bukan', []);");

    expect($catalog->pages())->toBe([]);
});

it('melempar kalau direktori controller tidak ada', function () {
    $catalog = new RenderedPageCatalog('/tidak/ada', '/juga/tidak');

    expect(fn () => $catalog->pages())->toThrow(RuntimeException::class);
});
