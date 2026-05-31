<?php

use App\Models\Scheme;

test('example', function () {
    expect(true)->toBeTrue();
});

it('getRule return value based on key', function(){
    $scheme = new Scheme([
        'rules' => [
            'min_reviewer_count' => 3,
            'nested' => ['value' => 99],
        ]
    ]);
    expect($scheme->getRule('min_reviewer_count'))->toBe(3)
    ->and($scheme->getRule('nested.value'))->toBe(99);
});

it('getRule return default if there`s no key assigned', function(){
    $scheme = new Scheme(['rules' => []]);
    expect($scheme->getRule('unknown', 'fallback'))->toBe('fallback');
});

it('minReviewerCount use default value "2" if there`s no rule assigned', function(){
    $scheme = new Scheme(['rules'=> []]);
    expect($scheme->minReviewerCount())->toBe(2);
});
it('minReviewerCount ambil dari rules dan cast ke int', function () {
    $scheme = new Scheme([
    'rules' => ['min_reviewer_count' => '5'],
    ]);

    expect($scheme->minReviewerCount())->toBe(5);
});

it('maxReviewerWorkload pakai default 10 jika rule tidak ada', function () {
    $scheme = new Scheme(['rules' => []]);

    expect($scheme->maxReviewerWorkload())->toBe(10);
});

it('maxReviewerWorkload ambil dari rules dan cast ke int', function () {
    $scheme = new Scheme([
        'rules' => ['max_reviewer_workload' => '12'],
    ]);

    expect($scheme->maxReviewerWorkload())->toBe(12);
});
