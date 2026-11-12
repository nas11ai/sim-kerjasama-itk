<?php

// tests/Feature/FormControllerUpdateOptionsTest.php
//
// Dulu FormController::update() (kini PublishFormDraft, BR-FE-34) menulis $option->order lalu ->save(), padahal
// tabel form_field_options (database/migrations/2025_06_04_070309_create_form_field_options_table.php)
// hanya punya id, form_field_id, label, created_at, updated_at. Assignment
// langsung (bukan lewat $fillable) melewati mass-assignment guard, jadi
// query INSERT/UPDATE-nya membawa kolom "order" yang tidak ada dan
// PostgreSQL menolaknya dengan SQLSTATE[42703]. Setiap admin yang mengedit
// field dengan opsi (select/radio/checkbox) pasti menabrak ini.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function actingFormAdmin(): User
{
    Permission::findOrCreate('schemes.manage');
    $user = User::factory()->create();
    $user->givePermissionTo('schemes.manage');

    return $user;
}

it('updates a form field with existing options without crashing on a missing order column', function () {
    $admin = actingFormAdmin();

    $form = Form::factory()->create();
    $fieldType = FieldType::create(['name' => 'Pilihan '.fake()->unique()->numerify('####-####')]);
    $field = FormField::create([
        'form_id' => $form->id,
        'label' => 'Jenis Kelamin',
        'is_required' => true,
        'field_type_id' => $fieldType->id,
        'order' => 1,
    ]);
    $option = FormFieldOption::create([
        'form_field_id' => $field->id,
        'label' => 'Laki-laki',
    ]);

    $payload = [
        'fields' => [
            [
                'id' => $field->id,
                'field_type_id' => $fieldType->id,
                'label' => 'Jenis Kelamin',
                'is_required' => true,
                'options' => [
                    ['id' => $option->id, 'label' => 'Laki-laki'],
                    ['label' => 'Perempuan'],
                ],
            ],
        ],
    ];

    $this->actingAs($admin)->put(route('admin.forms.draft.update', $form), $payload)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.forms.publish', $form))->assertSessionHasNoErrors();

    expect(FormFieldOption::where('form_field_id', $field->id)->pluck('label')->sort()->values()->all())
        ->toBe(['Laki-laki', 'Perempuan']);
});
