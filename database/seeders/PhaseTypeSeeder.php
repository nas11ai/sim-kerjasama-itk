<?php

namespace Database\Seeders;

use App\Models\PhaseType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhaseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $phase_types = [
                    'Pengajuan',
                    'Evaluasi Pengajuan',
                ];

                foreach ($phase_types as $phase_type) {
                    PhaseType::create([
                        'name' => $phase_type
                    ]);
                }
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
