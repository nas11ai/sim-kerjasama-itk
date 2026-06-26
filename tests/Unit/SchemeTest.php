<?php

use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns rule value from rules jsonb column', function () {
    $scheme = Scheme::factory()->create([
        'rules' => [
            'min_reviewer_count' => 3,
            'max_reviewer_workload' => 5,
        ],
    ]);

    expect($scheme->getRule('min_reviewer_count'))->toBe(3)
        ->and($scheme->getRule('max_reviewer_workload'))->toBe(5);
});

it('returns default value when rule key does not exist', function () {
    $scheme = Scheme::factory()->create([
        'rules' => null,
    ]);

    expect($scheme->getRule('min_reviewer_count', 2))->toBe(2)
        ->and($scheme->getRule('nonexistent_key', 'default'))->toBe('default');
});

it('returns null when no default provided and key does not exist', function () {
    $scheme = Scheme::factory()->create([
        'rules' => null,
    ]);

    expect($scheme->getRule('nonexistent_key'))->toBeNull();
});

it('returns min reviewer count with default 2 when rules is null', function () {
    $scheme = Scheme::factory()->create([
        'rules' => null,
    ]);

    expect($scheme->minReviewerCount())->toBe(2);
});

it('returns min reviewer count from rules when set', function () {
    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => 5],
    ]);

    expect($scheme->minReviewerCount())->toBe(5);
});

it('returns max reviewer workload with default 10 when rules is null', function () {
    $scheme = Scheme::factory()->create([
        'rules' => null,
    ]);

    expect($scheme->maxReviewerWorkload())->toBe(10);
});

it('returns max reviewer workload from rules when set', function () {
    $scheme = Scheme::factory()->create([
        'rules' => ['max_reviewer_workload' => 7],
    ]);

    expect($scheme->maxReviewerWorkload())->toBe(7);
});

it('casts string rule value to int for minReviewerCount', function () {
    $scheme = Scheme::factory()->create([
        'rules' => ['min_reviewer_count' => '3'],
    ]);

    expect($scheme->minReviewerCount())->toBe(3)
        ->and($scheme->minReviewerCount())->toBeInt();
});

it('casts string rule value to int for maxReviewerWorkload', function () {
    $scheme = Scheme::factory()->create([
        'rules' => ['max_reviewer_workload' => '5'],
    ]);

    expect($scheme->maxReviewerWorkload())->toBe(5)
        ->and($scheme->maxReviewerWorkload())->toBeInt();
});
