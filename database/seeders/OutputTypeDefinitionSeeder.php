<?php

namespace Database\Seeders;

use App\Models\OutputTypeDefinition;
use Illuminate\Database\Seeder;

/**
 * BR-RO-05/09, W140 — spesifikasi isi enam tipe luaran DDD (`supporting/03_research_output.md`).
 * Idempoten. Baris yang sudah ada hanya dilengkapi spesifikasinya: nama dan status aktif milik
 * keputusan Admin di Konfigurasi Umum, jadi tidak ditimpa.
 */
class OutputTypeDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::definitions() as $code => $definition) {
            $type = OutputTypeDefinition::firstOrNew(['code' => $code]);

            if (!$type->exists) {
                $type->name = $definition['name'];
                $type->is_active = true;
            }

            $type->fill([
                'fields' => $definition['fields'],
                'requires_files' => $definition['requires_files'] ?? false,
                'submission_type' => $definition['submission_type'] ?? null,
            ])->save();
        }
    }

    /**
     * @return array<string, array{name: string, fields: list<array<string, mixed>>, requires_files?: bool, submission_type?: string}>
     */
    public static function definitions(): array
    {
        return [
            'article' => ['name' => 'Artikel Jurnal', 'fields' => [
                self::field('title', 'Judul Artikel', required: true),
                self::field('journal_name', 'Nama Jurnal', required: true),
                self::choice('journal_type', 'Tipe Jurnal', true, [
                    'national' => 'Nasional',
                    'national_accredited' => 'Nasional Terakreditasi',
                    'international' => 'Internasional',
                    'international_reputable' => 'Internasional Bereputasi',
                ]),
                self::field('year', 'Tahun Terbit', 'number', true),
                self::field('doi', 'DOI'),
                self::field('url', 'URL', 'url'),
            ]],
            'book' => ['name' => 'Buku', 'fields' => [
                self::field('title', 'Judul Buku', required: true),
                self::field('publisher', 'Penerbit', required: true),
                self::field('year', 'Tahun Terbit', 'number', true),
                self::field('isbn', 'ISBN'),
            ]],
            // BR-RO-04 — dua tipe yang wajib berlampiran.
            'ip' => ['name' => 'HKI', 'requires_files' => true, 'fields' => [
                self::field('title', 'Judul HKI', required: true),
                self::choice('ip_type', 'Jenis HKI', true, [
                    'patent' => 'Paten',
                    'simple_patent' => 'Paten Sederhana',
                    'copyright' => 'Hak Cipta',
                    'industrial_design' => 'Desain Industri',
                    'trademark' => 'Merek',
                ]),
                self::field('registration_number', 'Nomor Pendaftaran'),
                self::field('registration_date', 'Tanggal Pendaftaran', 'date'),
                self::choice('status', 'Status', false, ['registered' => 'Terdaftar', 'granted' => 'Diberikan']),
            ]],
            'prototype' => ['name' => 'Prototipe', 'requires_files' => true, 'fields' => [
                self::field('name', 'Nama Prototipe', required: true),
                self::choice('prototype_type', 'Jenis Prototipe', true, [
                    'hardware' => 'Perangkat Keras',
                    'software' => 'Perangkat Lunak',
                    'other' => 'Lainnya',
                ]),
                self::field('description', 'Deskripsi', 'textarea'),
            ]],
            // BR-RO-02 — perjanjian kerja sama adalah luaran pengabdian.
            'pks' => ['name' => 'PKS — Perjanjian Kerja Sama', 'submission_type' => 'Pengabdian kepada Masyarakat', 'fields' => [
                self::field('title', 'Judul Kerja Sama', required: true),
                self::field('involved_parties', 'Pihak Terlibat', required: true),
                self::field('start_date', 'Tanggal Mulai', 'date', true),
                // BR-RO-03 — kerja sama yang masih berjalan belum punya tanggal berakhir.
                [...self::field('end_date', 'Tanggal Berakhir', 'date'),
                    'hint' => 'Kosongkan tanggal berakhir kalau kerja samanya masih berjalan.',
                    'empty_label' => 'Masih berjalan'],
                self::field('description', 'Deskripsi', 'textarea'),
            ]],
            'meeting' => ['name' => 'Seminar', 'fields' => [
                self::field('title', 'Judul Makalah', required: true),
                self::choice('meeting_type', 'Jenis Pertemuan', true, [
                    'seminar' => 'Seminar',
                    'conference' => 'Konferensi',
                    'workshop' => 'Lokakarya',
                ]),
                self::field('year', 'Tahun', 'number', true),
                self::field('description', 'Deskripsi', 'textarea'),
            ]],
        ];
    }

    /** @return array{key: string, label: string, type: string, required: bool} */
    private static function field(string $key, string $label, string $type = 'text', bool $required = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required];
    }

    /**
     * @param  array<string, string>  $options  nilai → label
     * @return array<string, mixed>
     */
    private static function choice(string $key, string $label, bool $required, array $options): array
    {
        return [...self::field($key, $label, 'select', $required), 'options' => array_map(
            fn (string $value, string $text): array => ['value' => $value, 'label' => $text],
            array_keys($options),
            array_values($options),
        )];
    }
}
