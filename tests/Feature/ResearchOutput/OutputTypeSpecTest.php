<?php

// tests/Feature/ResearchOutput/OutputTypeSpecTest.php
//
// W140 — spesifikasi isi per tipe luaran (BR-RO-05/09) tinggal di `output_type_definitions.fields`.

use App\Data\View\ResearchOutput\OutputTypeData;
use App\Models\OutputTypeDefinition;
use App\Queries\Submission\RealisedOutputTypeChoices;
use Database\Seeders\OutputTypeDefinitionSeeder;

it('seeder idempoten: enam tipe DDD', function () {
    $this->seed(OutputTypeDefinitionSeeder::class);
    $this->seed(OutputTypeDefinitionSeeder::class);

    expect(OutputTypeDefinition::query()->orderBy('code')->pluck('code')->all())
        ->toBe(['article', 'book', 'ip', 'meeting', 'pks', 'prototype']);
});

it('seeder tidak menimpa keputusan Admin atas nama dan status aktif', function () {
    $this->seed(OutputTypeDefinitionSeeder::class);
    OutputTypeDefinition::query()->where('code', 'article')->update(['name' => 'Artikel', 'is_active' => false]);

    $this->seed(OutputTypeDefinitionSeeder::class);

    $article = OutputTypeDefinition::query()->where('code', 'article')->sole();
    expect($article->name)->toBe('Artikel')
        ->and($article->is_active)->toBeFalse()
        ->and($article->fieldSpecs())->toHaveCount(6);
});

it('seeder menandai tipe berlampiran dan tipe pengabdian', function () {
    $this->seed(OutputTypeDefinitionSeeder::class);

    expect(OutputTypeDefinition::query()->where('requires_files', true)->orderBy('code')->pluck('code')->all())
        ->toBe(['ip', 'prototype'])
        ->and(OutputTypeDefinition::query()->where('code', 'pks')->value('submission_type'))->toBe('Pengabdian kepada Masyarakat');
});

it('spesifikasi melewati entri tanpa kunci dan memakai kunci bila label kosong', function () {
    $type = new OutputTypeDefinition(['fields' => [
        ['label' => 'Tanpa Kunci', 'required' => true],
        ['key' => 'title', 'required' => true],
        ['key' => 'journal_type', 'label' => 'Tipe Jurnal', 'type' => 'select', 'options' => [['value' => 'national', 'label' => 'Nasional']]],
        ['key' => 'misc', 'label' => 'Lain', 'type' => 'bukan-tipe'],
    ]]);

    $specs = $type->fieldSpecs();

    expect(array_map(fn ($spec) => $spec->key, $specs))->toBe(['title', 'journal_type', 'misc'])
        ->and($specs[0]->label)->toBe('title')
        ->and($specs[0]->required)->toBeTrue()
        ->and($specs[1]->type)->toBe('select')
        ->and($specs[1]->optionLabel('national'))->toBe('Nasional')
        ->and($specs[2]->type)->toBe('text');
});

it('judul baris dibaca dari field pertama spesifikasi', function () {
    $this->seed(OutputTypeDefinitionSeeder::class);

    $headline = fn (string $code) => OutputTypeDefinition::query()->where('code', $code)->sole()->headlineKey();

    expect($headline('article'))->toBe('title')
        ->and($headline('prototype'))->toBe('name')
        ->and((new OutputTypeDefinition(['fields' => []]))->headlineKey())->toBeNull();
});

it('pilihan tipe luaran membawa spesifikasi isinya', function () {
    $this->seed(OutputTypeDefinitionSeeder::class);
    // Pengajuan tanpa jenis: PKS (khusus CommunityService) tidak ditawarkan (BR-RO-02).
    $s = outputUploadFixture()['submission'];

    $choices = RealisedOutputTypeChoices::run($s);

    expect($choices[0])->toBeInstanceOf(OutputTypeData::class)
        ->and(array_map(fn (OutputTypeData $type) => $type->code, $choices))->not->toContain('pks')
        ->and(collect($choices)->firstWhere('code', 'ip')->requiresFiles)->toBeTrue()
        ->and(collect($choices)->firstWhere('code', 'article')->fields[0]->label)->toBe('Judul Artikel');
});
