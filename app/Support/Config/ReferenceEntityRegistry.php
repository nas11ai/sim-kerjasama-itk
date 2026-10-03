<?php

namespace App\Support\Config;

use App\Models\BudgetComponent;
use App\Models\OutputTypeDefinition;
use App\Models\SchemeType;
use App\Models\SubmissionType;
use App\Models\TechnologyReadinessLevel;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Daftar data acuan yang dikelola LPPM dari satu layar.
 *
 * Sengaja **bukan** semua tabel lookup. `PhaseType`, `FormType`, dan
 * `FieldType` adalah kosakata mesin Form Engine — menambahnya dari layar
 * berarti membuat nilai yang tidak dikenali kode mana pun, dan itu gagal jauh
 * dari tempat penambahannya. Yang masuk sini hanya acuan yang isinya memang
 * keputusan LPPM.
 *
 * `SubmissionType` masuk daftar tapi tidak dapat disunting (BR-SC-02): ia
 * mengikat Scheme, Submission, Review, dan Monev sekaligus, jadi nilai ketiga
 * akan menjadi cabang yang tidak ditangani satu pun dari keempatnya.
 * Menampilkannya sebagai baris read-only lebih jujur daripada
 * menyembunyikannya — Admin yang tidak menemukannya akan mengira ada layar
 * lain.
 */
final class ReferenceEntityRegistry
{
    /**
     * @return array<string, ReferenceEntity<covariant Model>>
     */
    public static function all(): array
    {
        return collect([...self::masterData(), ...self::taxonomies()])
            ->keyBy(fn (ReferenceEntity $e) => $e->key)
            ->all();
    }

    /**
     * @return list<ReferenceEntity<covariant Model>>
     */
    private static function masterData(): array
    {
        return [
            new ReferenceEntity(
                key: 'scheme-types',
                label: 'Tipe Skema',
                model: SchemeType::class,
                usageRelation: 'schemes',
                usedBy: 'Skema',
            ),
            new ReferenceEntity(
                key: 'submission-types',
                label: 'Tipe Submission',
                model: SubmissionType::class,
                usageRelation: 'schemes',
                usedBy: 'Skema, Submission, Review, Monev',
                editable: false,
            ),
            new ReferenceEntity(
                key: 'budget-components',
                label: 'Komponen Anggaran',
                model: BudgetComponent::class,
                usageRelation: 'budgetLineItems',
                usedBy: 'Rencana anggaran pengajuan',
            ),
        ];
    }

    /**
     * @return list<ReferenceEntity<covariant Model>>
     */
    private static function taxonomies(): array
    {
        return [
            // BR-SC-04 — taksonomi luaran adalah keputusan LPPM, bukan kosakata
            // mesin Form Engine. Sebelum ini `is_active`-nya dibaca di mana-mana
            // tapi tidak ada satu pun jalur yang menulisnya, jadi seluruh
            // pembacaan itu menyiapkan diri untuk keadaan yang tidak mungkin.
            new ReferenceEntity(
                key: 'output-types',
                label: 'Tipe Luaran',
                model: OutputTypeDefinition::class,
                usageRelation: 'researchOutputs',
                usedBy: 'luaran dan target luaran pengajuan',
                additionalUsageRelations: ['submissionTargetOutputs'],
                codeColumn: 'code',
            ),
            new ReferenceEntity(
                key: 'trls',
                label: 'Technology Readiness Level',
                model: TechnologyReadinessLevel::class,
                usageRelation: 'schemes',
                usedBy: 'Skema dan form pengajuan',
                // Tabelnya tidak punya `is_active`. Dinyatakan di sini alih-alih
                // ditebak layar, supaya tombol Nonaktifkan tidak muncul lalu
                // gagal pada kolom yang memang tidak ada.
                deactivatable: false,
            ),
        ];
    }

    /**
     * @return ReferenceEntity<covariant Model>
     */
    public static function get(string $key): ReferenceEntity
    {
        $entity = self::all()[$key] ?? null;

        if (!$entity instanceof ReferenceEntity) {
            throw new InvalidArgumentException("Data acuan \"{$key}\" tidak dikelola dari layar konfigurasi.");
        }

        return $entity;
    }
}
