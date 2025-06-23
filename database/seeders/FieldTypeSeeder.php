<?php

namespace Database\Seeders;

use App\Models\FieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FieldTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
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
                ];

                foreach ($fieldTypes as $type) {
                    FieldType::firstOrCreate(['name' => $type]);
                }
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
