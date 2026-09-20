<?php

// tests/Feature/Review/FieldResponseValidatorTest.php

use App\Support\Review\FieldResponseValidator;

it('menuntut field wajib yang kosong', function () {
    $response = evaluationFieldResponse('text', null);
    $response->reviewFormField->update(['is_required' => true]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))
        ->toBe(["Field 'Uji' is required"]);
});

it('mendiamkan field tidak wajib yang kosong', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('text', null)))->toBe([]);
});

it('tidak menerapkan aturan tipe pada nilai kosong', function () {
    $response = evaluationFieldResponse('email', null);

    expect(FieldResponseValidator::errorsFor($response))->toBe([]);
});

it('menolak email yang bukan email', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('email', 'bukan-email')))
        ->toBe(["Field 'Uji' must be a valid email address"]);
});

it('menolak angka yang bukan angka', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('number', 'dua')))
        ->toBe(["Field 'Uji' must be a number"]);
});

it('menolak URL yang bukan URL', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('url', 'bukan url')))
        ->toBe(["Field 'Uji' must be a valid URL"]);
});

it('menerapkan aturan panjang kustom', function () {
    $response = evaluationFieldResponse('text', 'ab');
    $response->reviewFormField->update(['validation_rules' => ['min_length' => 5]]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))
        ->toBe(["Field 'Uji' must be at least 5 characters"]);
});

it('menghitung panjang dalam karakter, bukan byte', function () {
    $response = evaluationFieldResponse('text', 'éééé');
    $response->reviewFormField->update(['validation_rules' => ['max_length' => 4]]);

    // strlen() menghitung 8 byte dan akan salah menolaknya.
    expect(FieldResponseValidator::errorsFor($response->fresh()))->toBe([]);
});

it('mengumpulkan galat tipe dan galat kustom sekaligus', function () {
    $response = evaluationFieldResponse('email', 'ab');
    $response->reviewFormField->update(['validation_rules' => ['min_length' => 5]]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))->toHaveCount(2);
});
