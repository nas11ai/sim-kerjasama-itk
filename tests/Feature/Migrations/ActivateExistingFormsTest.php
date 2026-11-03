<?php

// tests/Feature/Migrations/ActivateExistingFormsTest.php
//
// W91 — Form yang tersimpan nonaktif sebelum E2 berperilaku aktif, karena flag-nya tak pernah
// dibaca. Migration menyamakan datanya dengan perilaku itu, kecuali Form Biodata (W61).

use App\Models\Form;

function runActivateExistingForms(): void
{
    $migration = require database_path('migrations/2026_11_03_120000_activate_existing_forms.php');
    $migration->up();
}

it('mengaktifkan Form lama yang tersimpan nonaktif', function () {
    $form = Form::factory()->create(['is_active' => false, 'form_type_id' => regularFormType()->id]);

    runActivateExistingForms();

    expect($form->fresh()->is_active)->toBeTrue();
});

it('membiarkan Form Biodata tetap nonaktif', function () {
    $biodata = Form::factory()->create(['is_active' => false, 'form_type_id' => biodataFormType()->id]);

    runActivateExistingForms();

    expect($biodata->fresh()->is_active)->toBeFalse();
});

it('tidak mengubah Form yang sudah aktif', function () {
    $form = Form::factory()->create(['is_active' => true, 'form_type_id' => regularFormType()->id]);
    $before = $form->fresh()->updated_at;

    runActivateExistingForms();

    expect($form->fresh()->updated_at->equalTo($before))->toBeTrue();
});
