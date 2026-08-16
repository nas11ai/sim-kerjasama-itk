<?php

namespace Database\Seeders;

use App\Models\BudgetComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BudgetComponentSeeder extends Seeder
{
    /** @var list<string> */
    private const COMPONENTS = [
        'Bahan habis pakai',
        'Peralatan penunjang',
        'Perjalanan',
        'Honorarium',
        'Publikasi & luaran',
        'Analisis laboratorium',
        'Lain-lain',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::COMPONENTS as $name) {
                BudgetComponent::firstOrCreate(['name' => $name], ['is_active' => true]);
            }
        });
    }
}
