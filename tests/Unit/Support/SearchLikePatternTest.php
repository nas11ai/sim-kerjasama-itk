<?php

// K2 — urutan "prefix dulu" di pencarian global: escape sama dengan contains(), % hanya di belakang.

use App\Support\Search\LikePattern;

it('startsWith meng-escape istilah dan hanya menambah % di belakang', function () {
    expect(LikePattern::startsWith('50%'))->toBe('50\\%%')
        ->and(LikePattern::startsWith('budi'))->toBe('budi%');
});
