<?php

namespace Database\Seeders;

use App\Models\FieldType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FieldTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $fieldTypes = [
                'text',
                'email',
                'number',
                'textarea',
                'select',
                'radio',
                'checkbox',
                'date',
                'time',
                'file',
                'url',
                'phone',
                // UI pattern yang menulis ke kolom sungguhan, bukan form_field_responses
                // (BR-SCH-09).
                'scheme_selector',
                'trl_selector',
            ];

            foreach ($fieldTypes as $type) {
                FieldType::firstOrCreate(['name' => $type]);
            }
        });
    }
}
