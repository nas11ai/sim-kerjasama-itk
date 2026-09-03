<?php

namespace App\Support\Bdd;

/**
 * Satu skenario Gherkin, sudah diurai jadi bagian yang dipakai guard.
 */
final readonly class ScenarioDescriptor
{
    /**
     * @param  list<string>  $tags  seluruh tag, tanpa '@'
     * @param  list<string>  $businessRules  tag yang berbentuk BR-XX-NN
     */
    public function __construct(
        public string $id,
        public array $tags,
        public array $businessRules,
        public ?string $milestone,
        public ?string $class,
        public string $file,
        public int $line,
    ) {}

    /**
     * Lokasi ringkas untuk pesan kegagalan guard.
     */
    public function location(): string
    {
        return basename($this->file).':'.$this->line;
    }
}
