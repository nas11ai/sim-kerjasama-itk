<?php

// tests/Feature/Review/FieldResponseFormatterTest.php
//
// Model tidak merakit HTML. `formatted_value` dipakai layar Inertia DAN
// ringkasan teks polos di ReviewSummary, jadi keluarannya wajib terbaca di
// keduanya — keputusan menjadikannya tautan milik layar.

use App\Models\ReviewFormField;
use App\Support\Review\FieldResponseFormatter;

it('mengembalikan string kosong saat tidak ada nilai', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('text', null)))->toBe('');
});

it('tidak pernah mengembalikan HTML untuk URL', function () {
    $hasil = FieldResponseFormatter::readable(evaluationFieldResponse('url', 'https://itk.ac.id'));

    expect($hasil)->toBe('https://itk.ac.id')
        ->and($hasil)->not->toContain('<a ');
});

it('menggabungkan pilihan checkbox jadi satu baris', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('checkbox', '["A","B"]')))
        ->toBe('A, B');
});

it('memformat angka dengan dua desimal', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('number', '3.5')))->toBe('3.50');
});

it('mengembalikan nilai apa adanya untuk tipe tanpa aturan khusus', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('email', 'a@itk.ac.id')))
        ->toBe('a@itk.ac.id');
});

it('mengembalikan tanggal dalam bentuk yang terbaca', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('date', '2026-09-20')))
        ->toBe('20 Sep 2026');
});

it('membiarkan tanggal yang tidak terbaca apa adanya', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('date', 'bukan tanggal')))
        ->toBe('bukan tanggal');
});

it('menampilkan label pilihan, bukan nilainya', function () {
    $response = evaluationFieldResponse('select', 'a');
    $response->reviewFormField->reviewFormFieldOptions()->create([
        'label' => 'Sangat Baik',
        'value' => 'a',
        'order' => 1,
    ]);

    expect(FieldResponseFormatter::readable($response->fresh()))->toBe('Sangat Baik');
});

it('tetap memakai accessor formatted_value di model', function () {
    expect(evaluationFieldResponse('number', '7')->formatted_value)->toBe('7.00');
});

it('tidak mengambil opsi milik field lain', function () {
    $response = evaluationFieldResponse('select', '4');
    $field = $response->reviewFormField;

    // Field lain, opsinya dibuat lebih dulu supaya barisnya lebih tua. Ia yang
    // menang kalau `orWhere('label')` lolos dari penyaringan per field.
    $lain = ReviewFormField::create([
        'review_evaluation_form_version_id' => $field->review_evaluation_form_version_id,
        'field_type_id' => $field->field_type_id,
        'label' => 'Isian lain',
        'is_required' => false,
        'order' => 2,
    ]);
    $lain->reviewFormFieldOptions()->create(['label' => '4', 'value' => '9', 'order' => 1]);

    $field->reviewFormFieldOptions()->create(['label' => 'Baik', 'value' => '4', 'order' => 1]);

    expect(FieldResponseFormatter::readable($response->fresh()))->toBe('Baik');
});

it('menggabungkan pilihan checkbox yang tersimpan sebagai angka', function () {
    expect(FieldResponseFormatter::readable(evaluationFieldResponse('checkbox', '[1,2]')))
        ->toBe('1, 2');
});

it('menerjemahkan checkbox yang digabung koma jadi label pilihannya', function () {
    // ShowPage.vue menyimpannya sebagai nilai opsi yang digabung koma, jadi
    // inilah satu-satunya bentuk yang benar-benar ada di basis data.
    $response = evaluationFieldResponse('checkbox', 'a,c');
    $options = $response->reviewFormField->reviewFormFieldOptions();
    $options->create(['label' => 'Sangat Baik', 'value' => 'a', 'order' => 1]);
    $options->create(['label' => 'Cukup', 'value' => 'c', 'order' => 2]);

    expect(FieldResponseFormatter::readable($response->fresh()))->toBe('Sangat Baik, Cukup');
});

it('tidak memicu deprecation saat nilai jawaban null', function () {
    $response = evaluationFieldResponse('text', null);

    $deprecations = [];
    set_error_handler(function (int $no, string $str) use (&$deprecations): bool {
        $deprecations[] = $str;

        return true;
    }, E_DEPRECATED);

    try {
        $response->hasValue();
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([]);
});
