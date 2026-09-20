<?php

namespace App\Support\Inertia;

use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Mendaftar setiap halaman Inertia yang di-render controller, lalu memeriksa
 * berkas Vue-nya ada. Test feature meng-assert nama yang *dikirim* controller,
 * jadi nama tanpa berkas tetap lolos — gagalnya baru tampak di browser.
 */
final class RenderedPageCatalog
{
    public function __construct(
        private readonly string $controllerPath,
        private readonly string $pagesPath,
    ) {}

    /**
     * @return array<int, RenderedPage>
     */
    public function pages(): array
    {
        if (!is_dir($this->controllerPath)) {
            throw new RuntimeException("Direktori controller tidak ada: {$this->controllerPath}");
        }

        $pages = [];

        foreach ($this->phpFiles() as $file) {
            $source = (string) file_get_contents($file->getPathname());
            $relative = 'Controllers/'.$file->getRelativePathname();

            // `Inertia::render('X'` dan `inertia('X'`. Awalan [^\w>] mencegah
            // `->inertia(` atau nama fungsi lain yang berakhiran "inertia".
            preg_match_all(
                '/(?:Inertia::render|(?<![\w>])inertia)\(\s*[\'"]([^\'"]+)[\'"]/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE
            );

            foreach ($matches[1] as $match) {
                $pages[] = new RenderedPage(
                    page: $match[0],
                    file: $relative,
                    line: substr_count(substr($source, 0, (int) $match[1]), "\n") + 1,
                );
            }
        }

        return $pages;
    }

    /**
     * Halaman yang di-render tapi berkas Vue-nya tidak ada.
     *
     * @return array<int, RenderedPage>
     */
    public function missing(): array
    {
        return array_values(array_filter(
            $this->pages(),
            fn (RenderedPage $page) => !is_file($this->pagesPath.'/'.$page->page.'.vue'),
        ));
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpFiles(): array
    {
        return iterator_to_array(
            Finder::create()->files()->in($this->controllerPath)->name('*.php')->sortByName(),
            false
        );
    }
}
