<?php

// tests/Feature/Migrations/DropDateReferencesFromFormPhaseDetailsTest.php
//
// ADR 0019 (contract) — langkah fase tidak lagi menunjuk tanggal; jenis + siklus langkah dibaca
// dari kalender periode pengajuan. Ketiga kolom rujukan dihapus dan tidak bisa dikembalikan.

use Illuminate\Support\Facades\Schema;

function dropDateReferencesMigration(): object
{
    return require database_path('migrations/2026_11_12_200000_drop_date_references_from_form_phase_details.php');
}

it('menghapus tiga kolom rujukan tanggal di langkah fase', function () {
    foreach (['submission_date_id', 'evaluation_date_id', 'revision_date_id'] as $column) {
        expect(Schema::hasColumn('form_phase_details', $column))->toBeFalse();
    }

    expect(Schema::hasColumns('form_phase_details', ['deadline_type', 'deadline_cycle']))->toBeTrue();
});

it('tidak bisa dibalik', function () {
    dropDateReferencesMigration()->down();
})->throws(RuntimeException::class, 'Migrasi ini tidak bisa dibalik: rujukan tanggal per langkah sudah dilebur ke kalender periode.');
