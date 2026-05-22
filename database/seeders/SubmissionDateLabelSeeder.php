<?php

namespace Database\Seeders;

use App\Models\SubmissionDateLabel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubmissionDateLabelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $submission_date_labels = [
                    'Mulai Daftar Pengajuan',
                    'Selesai Daftar Pengajuan',
                    'Mulai Evaluasi Pengajuan',
                    'Selesai Evaluasi Pengajuan',
                ];

                foreach ($submission_date_labels as $submission_date_label) {
                    SubmissionDateLabel::create([
                        'name' => $submission_date_label
                    ]);
                }
            });
        } catch (\Exception $e) {
        }
    }
}
