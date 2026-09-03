<?php

use App\Models\FormSubmission;
use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves scheme from the column', function () {
    $scheme = Scheme::factory()->create();
    $submission = FormSubmission::factory()->create(['scheme_id' => $scheme->id]);

    expect($submission->scheme->id)->toBe($scheme->id);
});

it('returns null when no scheme is selected', function () {
    $submission = FormSubmission::factory()->create(['scheme_id' => null]);

    expect($submission->scheme)->toBeNull();
});

it('eager loads scheme without extra queries per row', function () {
    $scheme = Scheme::factory()->create();
    FormSubmission::factory()->count(3)->create(['scheme_id' => $scheme->id]);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    FormSubmission::with('scheme')->get()->each(fn ($s) => $s->scheme?->name);

    expect($queries)->toBe(2); // satu untuk submissions, satu untuk schemes
});
