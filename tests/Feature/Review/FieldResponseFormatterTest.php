<?php

// tests/Feature/Review/FieldResponseFormatterTest.php
//
// Model tidak merakit HTML. `formatted_value` dipakai layar Inertia DAN
// ringkasan teks polos di ReviewSummary, jadi keluarannya wajib terbaca di
// keduanya — keputusan menjadikannya tautan milik layar.

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
