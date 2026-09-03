<?php

namespace App\Support\Bdd;

use Behat\Gherkin\Keywords\ArrayKeywords;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Parser;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Mengurai seluruh berkas .feature di bawah satu direktori.
 *
 * Parser dipakai sebagai pustaka saja — tidak ada runner Gherkin di repo ini.
 */
final class FeatureCatalog
{
    private const ID_PATTERN = '/^\[([A-Z]+-[A-Z]+-\d+)\]/';

    private const BR_PATTERN = '/^BR-[A-Z]+-\d+$/';

    private const MILESTONE_PATTERN = '/^M[0-5]$/';

    private const CLASSES = ['happy', 'boundary', 'denial'];

    public function __construct(private readonly string $featureRoot) {}

    /**
     * @return list<ScenarioDescriptor>
     */
    public function scenarios(): array
    {
        $parser = $this->parser();
        $descriptors = [];

        foreach ($this->featureFiles() as $file) {
            $feature = $parser->parseFile($file);

            if (!$feature instanceof FeatureNode) {
                continue;
            }

            foreach ($feature->getScenarios() as $scenario) {
                $title = (string) $scenario->getTitle();

                if (preg_match(self::ID_PATTERN, $title, $matches) !== 1) {
                    throw new RuntimeException(
                        "Skenario tanpa ID di {$file}:{$scenario->getLine()} — "
                        ."judul harus diawali [AREA-TOPIK-NN], ditemukan: \"{$title}\""
                    );
                }

                // getTags() sudah mengembalikan list<string> — lihat TaggedNodeTrait.
                $tags = $scenario->getTags();

                $descriptors[] = new ScenarioDescriptor(
                    id: $matches[1],
                    tags: $tags,
                    businessRules: array_values(array_filter(
                        $tags,
                        static fn (string $tag): bool => preg_match(self::BR_PATTERN, $tag) === 1
                    )),
                    milestone: $this->firstMatching($tags, self::MILESTONE_PATTERN),
                    class: $this->firstIn($tags, self::CLASSES),
                    file: $file,
                    line: $scenario->getLine(),
                );
            }
        }

        return $descriptors;
    }

    private function parser(): Parser
    {
        // Keyword English eksplisit — repo ini tidak memakai header '# language:'.
        $keywords = new ArrayKeywords(['en' => [
            'feature' => 'Feature',
            'background' => 'Background',
            'scenario' => 'Scenario',
            'scenario_outline' => 'Scenario Outline|Scenario Template',
            'examples' => 'Examples|Scenarios',
            'given' => 'Given',
            'when' => 'When',
            'then' => 'Then',
            'and' => 'And',
            'but' => 'But',
        ]]);

        return new Parser(new Lexer($keywords));
    }

    /**
     * @param  list<string>  $tags
     */
    private function firstMatching(array $tags, string $pattern): ?string
    {
        foreach ($tags as $tag) {
            if (preg_match($pattern, $tag) === 1) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $allowed
     */
    private function firstIn(array $tags, array $allowed): ?string
    {
        foreach ($tags as $tag) {
            if (in_array($tag, $allowed, true)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * @return list<string> path ABSOLUT — parseFile() menolak path relatif
     */
    private function featureFiles(): array
    {
        if (!is_dir($this->featureRoot)) {
            throw new RuntimeException("Direktori feature tidak ditemukan: {$this->featureRoot}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->featureRoot, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $entry) {
            // Guard eksplisit, bukan anotasi @var — lihat catatan yang sama di
            // BusinessRuleCatalog::markdownFiles().
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if (!$entry->isFile() || $entry->getExtension() !== 'feature') {
                continue;
            }

            $real = realpath($entry->getPathname());

            if ($real === false) {
                throw new RuntimeException("Gagal me-resolve path {$entry->getPathname()}");
            }

            $files[] = $real;
        }

        sort($files);

        return $files;
    }
}
