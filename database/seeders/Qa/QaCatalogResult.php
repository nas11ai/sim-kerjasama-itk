<?php

namespace Database\Seeders\Qa;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormPhaseDetail;
use App\Models\Scheme;
use App\Models\SubmissionPeriod;
use App\Models\TechnologyReadinessLevel;

/** Katalog bersama yang dipakai setiap set: skema, form pengajuan beserta langkah dan isiannya, dua periode. */
final readonly class QaCatalogResult
{
    /**
     * @param  array{dasar: Scheme, terapan: Scheme, pengabdian: Scheme}  $schemes
     * @param  array{penelitian: Form, pengabdian: Form, monev: Form, luaran: Form}  $forms
     * @param  array{penelitian: array{submit: FormPhaseDetail, monev1: FormPhaseDetail, output: FormPhaseDetail}, pengabdian: array{submit: FormPhaseDetail, monev1: FormPhaseDetail, output: FormPhaseDetail}}  $steps
     * @param  array{penelitian: array<string, FormField>, pengabdian: array<string, FormField>, monev: array<string, FormField>}  $fields
     * @param  array{last: SubmissionPeriod, current: SubmissionPeriod}  $periods
     * @param  list<TechnologyReadinessLevel>  $trls
     */
    public function __construct(
        public array $schemes,
        public array $forms,
        public array $steps,
        public array $fields,
        public array $periods,
        public array $trls,
    ) {}

    /** Form pengajuan pemilik skema ini: skema Pengabdian lewat form Pengabdian, sisanya lewat form Penelitian. */
    public static function formKeyOf(string $schemeKey): string
    {
        return $schemeKey === 'pengabdian' ? 'pengabdian' : 'penelitian';
    }
}
