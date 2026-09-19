<?php

// tests/Unit/Data/RequestDataTest.php

use App\Data\Request\ReferenceEntityInput;
use App\Data\Request\SchemeInput;
use Illuminate\Support\ValidatedInput;

it('menyusun masukan skema dari input tervalidasi', function () {
    $input = SchemeInput::fromValidated(new ValidatedInput([
        'name' => 'Penelitian Dasar',
        'code' => 'PD',
        'max_budget' => 10_000_000,
        'max_members' => 4,
        'duration_months' => 12,
        'scheme_type_id' => 2,
        'rules' => ['min_reviewer_count' => 2],
        'allowed_trl_ids' => [3, 4],
    ]));

    expect($input->name)->toBe('Penelitian Dasar')
        ->and($input->maxBudget)->toBe(10_000_000)
        ->and($input->schemeTypeId)->toBe(2)
        ->and($input->submissionTypeId)->toBeNull()
        ->and($input->rules)->toBe(['min_reviewer_count' => 2])
        ->and($input->allowedTrlIds)->toBe([3, 4]);
});

it('membaca angka yang dikirim sebagai teks, bukan menjadikannya nol', function () {
    // Aturan `integer` menerima "10000000"; BR-SCH-02 — batas bernilai nol
    // akan menolak setiap anggaran tanpa ada yang tahu sebabnya.
    $input = SchemeInput::fromValidated(new ValidatedInput([
        'name' => 'Penelitian Terapan',
        'code' => 'PT',
        'max_budget' => '10000000',
        'max_members' => '3',
        'duration_months' => '6',
        'allowed_trl_ids' => ['5'],
    ]));

    expect($input->maxBudget)->toBe(10_000_000)
        ->and($input->maxMembers)->toBe(3)
        ->and($input->allowedTrlIds)->toBe([5]);
});

it('membedakan TRL yang tidak dikirim dari TRL yang dikosongkan', function () {
    $base = ['name' => 'X', 'code' => 'X', 'max_budget' => 1, 'max_members' => 1, 'duration_months' => 1];

    // null berarti "jangan sentuh"; daftar kosong berarti "hapus semua" (BR-SCH-05).
    expect(SchemeInput::fromValidated(new ValidatedInput($base))->allowedTrlIds)->toBeNull()
        ->and(SchemeInput::fromValidated(new ValidatedInput([...$base, 'allowed_trl_ids' => []]))->allowedTrlIds)
        ->toBe([])
        ->and(SchemeInput::fromValidated(new ValidatedInput($base))->rules)->toBeNull();
});

it('menyusun masukan entity acuan', function () {
    expect(ReferenceEntityInput::fromValidated(new ValidatedInput(['name' => 'Tipe Baru']))->name)
        ->toBe('Tipe Baru');
});
