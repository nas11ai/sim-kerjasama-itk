<?php

// tests/Feature/Review/FieldResponseValidatorTest.php

use App\Support\Review\FieldResponseValidator;

it('menuntut field wajib yang kosong', function () {
    $response = evaluationFieldResponse('text', null, true);

    expect(FieldResponseValidator::errorsFor($response->fresh()))
        ->toBe(["Isian 'Uji' wajib diisi."]);
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
        ->toBe(["Isian 'Uji' harus berupa alamat email yang valid."]);
});

it('menolak angka yang bukan angka', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('number', 'dua')))
        ->toBe(["Isian 'Uji' harus berupa angka."]);
});

it('menolak URL yang bukan URL', function () {
    expect(FieldResponseValidator::errorsFor(evaluationFieldResponse('url', 'bukan url')))
        ->toBe(["Isian 'Uji' harus berupa URL yang valid."]);
});

it('menerapkan aturan panjang kustom', function () {
    $response = evaluationFieldResponse('text', 'ab', validationRules: ['min_length' => 5]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))
        ->toBe(["Isian 'Uji' minimal 5 karakter."]);
});

it('menghitung panjang dalam karakter, bukan byte', function () {
    $response = evaluationFieldResponse('text', 'éééé', validationRules: ['max_length' => 4]);

    // strlen() menghitung 8 byte dan akan salah menolaknya.
    expect(FieldResponseValidator::errorsFor($response->fresh()))->toBe([]);
});

it('mengumpulkan galat tipe dan galat kustom sekaligus', function () {
    $response = evaluationFieldResponse('email', 'ab', validationRules: ['min_length' => 5]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))->toHaveCount(2);
});

it('melewati aturan kustom yang nilainya bukan skalar', function () {
    // Kolomnya JSON dan jalur tulisnya cuma `nullable|array`, jadi isi seperti
    // ini bisa tersimpan. Aturan yang tak terbaca dilewati, bukan meledak.
    $response = evaluationFieldResponse('text', 'ab', validationRules: ['min_length' => null, 'max_length' => 4]);

    expect(FieldResponseValidator::errorsFor($response->fresh()))->toBe([]);
});
