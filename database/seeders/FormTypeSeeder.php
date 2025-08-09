<?php

namespace Database\Seeders;

use App\Models\FormType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $form_types = [
                    'Biodata',
                    'Pengajuan',
                    'Penilaian Pengajuan',
                    'Monitoring Evaluasi Pengajuan'
                ];

                foreach ($form_types as $form_type) {
                    FormType::create([
                        'name' => $form_type
                    ]);
                }
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
