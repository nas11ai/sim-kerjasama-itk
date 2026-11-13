<?php

namespace Database\Seeders\Qa;

use App\Actions\Announcement\PublishAnnouncement;
use App\Actions\Announcement\RetractAnnouncement;
use App\Actions\Form\CreateForm;
use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\GrantFormAccess;
use App\Actions\Form\PublishFormDraft;
use App\Actions\Form\PublishPhaseDraft;
use App\Actions\Period\CreateSubmissionPeriod;
use App\Actions\Review\CreateEvaluationForm;
use App\Actions\Review\PublishEvaluationFormVersion;
use App\Actions\Scheme\SaveScheme;
use App\Data\Request\AnnouncementInput;
use App\Data\Request\EvaluationFormDetailsInput;
use App\Data\Request\FormAccessInput;
use App\Data\Request\FormDraftInput;
use App\Data\Request\FormFieldInput;
use App\Data\Request\FormInput;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\FormPhaseInput;
use App\Data\Request\PeriodDateInput;
use App\Data\Request\PhaseDraftInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Data\Request\SchemeInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Data\Request\SubmissionPeriodInput;
use App\Enums\DeadlineType;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionType;
use App\Models\TechnologyReadinessLevel;
use App\Models\User;
use App\Support\SubmissionTitleField;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Katalog bersama data uji QA. Semuanya lewat jalur Form Builder, Skema, dan Periode yang dipakai
 * Operator, dan lahir di awal tahun lalu: Temporal Field Binding (BR-FE-11) membuang isian yang
 * lebih muda dari pengajuannya.
 */
final class QaCatalog
{
    /** Nama TKT baku 1–9, ditampilkan "TKT n: nama". */
    private const TRLS = [
        1 => 'Prinsip Dasar', 2 => 'Formulasi Konsep', 3 => 'Pembuktian Konsep', 4 => 'Validasi Lab',
        5 => 'Validasi Lingkungan Relevan', 6 => 'Demonstrasi Prototipe', 7 => 'Demonstrasi Lingkungan Nyata',
        8 => 'Sistem Lengkap Teruji', 9 => 'Sistem Terbukti Beroperasi',
    ];

    public static function build(User $admin): QaCatalogResult
    {
        $lastYear = now()->year - 1;

        $catalog = QaClock::at(Carbon::create($lastYear, 1, 5, 9), fn (): array => self::library($admin, $lastYear));

        // Periode tahun ini dibuka enam minggu lalu: cukup untuk linimasa skenario, dan Batas Submit
        // (+3 bulan) tetap di depan saat QA mulai.
        $opened = now()->subDays(42)->setTime(8, 0);
        $catalog['periods']['current'] = QaClock::at($opened, fn (): SubmissionPeriod => self::period($admin, 'Periode '.now()->year, $catalog['phaseIds'], $opened));

        return new QaCatalogResult(
            schemes: $catalog['schemes'],
            forms: $catalog['forms'],
            steps: $catalog['steps'],
            fields: $catalog['fields'],
            periods: $catalog['periods'],
            trls: $catalog['trls'],
        );
    }

    /**
     * @return array{schemes: array{dasar: Scheme, terapan: Scheme, pengabdian: Scheme}, forms: array{penelitian: Form, pengabdian: Form, monev: Form, luaran: Form}, steps: array{penelitian: array{submit: FormPhaseDetail, monev1: FormPhaseDetail, output: FormPhaseDetail}, pengabdian: array{submit: FormPhaseDetail, monev1: FormPhaseDetail, output: FormPhaseDetail}}, fields: array{penelitian: array<string, FormField>, pengabdian: array<string, FormField>, monev: array<string, FormField>}, periods: array{last: SubmissionPeriod}, phaseIds: list<int>, trls: list<TechnologyReadinessLevel>}
     */
    private static function library(User $admin, int $lastYear): array
    {
        $trls = self::trls();
        $schemes = self::schemes($trls);

        $forms = [
            'penelitian' => self::form($admin, 'Pengajuan Penelitian', 'Pengajuan', self::submissionFields(withTrl: true)),
            'pengabdian' => self::form($admin, 'Pengajuan Pengabdian kepada Masyarakat', 'Pengajuan', self::submissionFields(withTrl: false)),
            'monev' => self::form($admin, 'Laporan Kemajuan', 'Monitoring Evaluasi Pengajuan', [
                ['textarea', 'Capaian Kegiatan', true, null],
                ['file', 'Berkas Laporan Kemajuan', false, null],
            ]),
            'luaran' => self::form($admin, 'Unggah Luaran', 'Monitoring Evaluasi Pengajuan', [
                ['textarea', 'Keterangan Luaran', false, null],
            ]),
        ];

        $access = [];
        foreach ($forms as $key => $form) {
            $access[$key] = self::grant($admin, $form);
        }

        $substansi = self::evaluationForm($admin, 'Penilaian Substansi', [['number', 'Skor', true], ['textarea', 'Catatan Penilai', false]]);
        $kemajuan = self::evaluationForm($admin, 'Penilaian Laporan Kemajuan', [['number', 'Skor Kemajuan', true], ['textarea', 'Catatan Penilai', false]]);

        $steps = [];
        $phaseIds = [];
        foreach (['penelitian' => 'Tahap Penelitian', 'pengabdian' => 'Tahap Pengabdian'] as $key => $title) {
            $phase = self::phase($admin, $title, $access[$key], $access['monev'], $access['luaran'], $substansi, $kemajuan);
            $phaseIds[] = $phase->id;
            $steps[$key] = self::stepsOf($phase);
        }

        self::announcements($admin);

        return [
            'schemes' => $schemes,
            'forms' => $forms,
            'steps' => $steps,
            'fields' => [
                'penelitian' => self::fieldsOf($forms['penelitian']),
                'pengabdian' => self::fieldsOf($forms['pengabdian']),
                'monev' => self::fieldsOf($forms['monev']),
            ],
            'periods' => ['last' => self::period($admin, "Periode {$lastYear}", $phaseIds, Carbon::create($lastYear, 1, 1))],
            'phaseIds' => $phaseIds,
            'trls' => $trls,
        ];
    }

    /**
     * Acuan baku yang tidak punya jalur buat di aplikasi (lihat B25 catatan bug), jadi ditulis langsung.
     *
     * @return list<TechnologyReadinessLevel>
     */
    private static function trls(): array
    {
        $levels = [];
        foreach (self::TRLS as $level => $name) {
            $levels[] = TechnologyReadinessLevel::firstOrCreate(['level' => $level], ['name' => $name]);
        }

        return $levels;
    }

    /**
     * BR-REV-01 — ambang penilai milik skema. BR-SCH-05 — TKT yang diizinkan per skema.
     *
     * @param  list<TechnologyReadinessLevel>  $trls
     * @return array{dasar: Scheme, terapan: Scheme, pengabdian: Scheme}
     */
    private static function schemes(array $trls): array
    {
        $rules = ['min_reviewer_count' => 2, 'max_reviewer_workload' => 10, 'max_student_members' => 3];
        $penelitian = SubmissionType::firstOrCreate(['name' => 'Penelitian'])->id;
        $pengabdian = SubmissionType::firstOrCreate(['name' => 'Pengabdian kepada Masyarakat'])->id;
        $trlIds = fn (int $from, int $to): array => array_values(array_map(
            fn (TechnologyReadinessLevel $trl): int => $trl->id,
            array_filter($trls, fn (TechnologyReadinessLevel $trl): bool => $trl->level >= $from && $trl->level <= $to),
        ));

        return [
            'dasar' => SaveScheme::run(new SchemeInput('Penelitian Dasar', 'QA-PD', 75_000_000, 5, 12,
                SchemeType::firstOrCreate(['name' => 'Penelitian Dasar'])->id, $penelitian, $rules, $trlIds(1, 3))),
            'terapan' => SaveScheme::run(new SchemeInput('Penelitian Terapan', 'QA-PT', 150_000_000, 5, 12,
                SchemeType::firstOrCreate(['name' => 'Penelitian Terapan'])->id, $penelitian, $rules, $trlIds(4, 6))),
            'pengabdian' => SaveScheme::run(new SchemeInput('Pengabdian kepada Masyarakat', 'QA-PKM', 50_000_000, 4, 8,
                SchemeType::firstOrCreate(['name' => 'Pengabdian Masyarakat'])->id, $pengabdian, $rules, [])),
        ];
    }

    /** @return list<array{0: string, 1: string, 2: bool, 3: list<array{id: null, label: string}>|null}> */
    private static function submissionFields(bool $withTrl): array
    {
        $fields = [
            [SubmissionTitleField::TYPE, 'Judul', true, null],
            ['textarea', 'Abstrak', true, null],
            ['text', 'Kata Kunci', false, null],
            ['scheme_selector', 'Skema', true, null],
        ];

        if ($withTrl) {
            $fields[] = ['trl_selector', 'TKT Saat Ini', true, null];
        }

        return [
            ...$fields,
            ['file', 'Berkas Proposal', true, null],
            ['date', 'Rencana Tanggal Mulai', false, null],
            ['select', 'Bidang Fokus', true, array_map(fn (string $label): array => ['id' => null, 'label' => $label],
                ['Energi Terbarukan', 'Pangan dan Pertanian', 'Kemaritiman', 'Teknologi Informasi', 'Kebencanaan'])],
        ];
    }

    /** @param  list<array{0: string, 1: string, 2: bool, 3: list<array{id: null, label: string}>|null}>  $fields */
    private static function form(User $admin, string $title, string $type, array $fields): Form
    {
        $form = CreateForm::run(
            new FormInput($title, "Formulir {$title} untuk data uji QA.", FormType::firstOrCreate(['name' => $type])->id, true),
            new FormDraftInput(array_map(fn (array $field): FormFieldInput => new FormFieldInput(
                id: null,
                fieldTypeId: FieldType::firstOrCreate(['name' => $field[0]])->id,
                label: $field[1],
                isRequired: $field[2],
                options: $field[3],
            ), $fields)),
            $admin,
        );
        PublishFormDraft::run($form, $admin);

        return $form->refresh();
    }

    /** ADR 0013 — akses untuk Institusi berlaku ke seluruh pohon organisasi di bawahnya. */
    private static function grant(User $admin, Form $form): FormAccessControl
    {
        GrantFormAccess::run(new FormAccessInput(
            $form->id,
            'submissions.create',
            [Organization::where('type', 'institution')->sole()->id],
        ), $admin);

        return FormAccessControl::where('form_id', $form->id)->sole();
    }

    /** @param  list<array{0: string, 1: string, 2: bool}>  $fields */
    private static function evaluationForm(User $admin, string $title, array $fields): ReviewEvaluationForm
    {
        $form = CreateEvaluationForm::run(new EvaluationFormDetailsInput($title, null), $admin);
        PublishEvaluationFormVersion::run($form, array_map(fn (array $field): ReviewFormFieldInput => new ReviewFormFieldInput(
            null,
            FieldType::firstOrCreate(['name' => $field[0]])->id,
            $field[1],
            null,
            $field[2],
            $field[0] === 'number' ? ['min_value' => 0, 'max_value' => 100] : null,
            null,
        ), $fields), $admin);

        return $form;
    }

    /** BR-FE-28 — satu tahap untuk satu Form pengajuan; Laporan Monev dan Luaran memakai Form lain. */
    private static function phase(
        User $admin,
        string $title,
        FormAccessControl $submission,
        FormAccessControl $monev,
        FormAccessControl $output,
        ReviewEvaluationForm $substansi,
        ReviewEvaluationForm $kemajuan,
    ): FormPhase {
        $pengajuan = PhaseType::firstOrCreate(['name' => 'Pengajuan'])->id;
        $evaluasi = PhaseType::firstOrCreate(['name' => 'Evaluasi Pengajuan'])->id;

        $phase = CreateFormPhase::run(new FormPhaseInput($title, null, true), new PhaseDraftInput([
            new FormPhaseDetailInput(null, $submission->id, $pengajuan, true, DeadlineType::Submit, null,
                [new StepEvaluationFormInput($substansi->id, isRequired: true, isActive: true)]),
            new FormPhaseDetailInput(null, $monev->id, $evaluasi, true, DeadlineType::Submit, 1,
                [new StepEvaluationFormInput($kemajuan->id, isRequired: true, isActive: true)]),
            new FormPhaseDetailInput(null, $output->id, $evaluasi, false, DeadlineType::Output, null),
        ]), $admin);
        PublishPhaseDraft::run($phase, $admin);

        return $phase->refresh();
    }

    /** @return array{submit: FormPhaseDetail, monev1: FormPhaseDetail, output: FormPhaseDetail} */
    private static function stepsOf(FormPhase $phase): array
    {
        $details = $phase->latestVersion()->sole()->formPhaseDetails()->orderBy('order')->get();

        return ['submit' => $details[0], 'monev1' => $details[1], 'output' => $details[2]];
    }

    /** @return array<string, FormField> */
    private static function fieldsOf(Form $form): array
    {
        $keys = [
            'Judul' => 'judul', 'Abstrak' => 'abstrak', 'Kata Kunci' => 'kata_kunci', 'Skema' => 'skema',
            'TKT Saat Ini' => 'tkt', 'Berkas Proposal' => 'proposal', 'Rencana Tanggal Mulai' => 'mulai',
            'Bidang Fokus' => 'bidang', 'Capaian Kegiatan' => 'capaian', 'Berkas Laporan Kemajuan' => 'laporan',
        ];

        return $form->formFields()->get()
            ->keyBy(fn (FormField $field): string => $keys[$field->label] ?? $field->label)
            ->all();
    }

    /**
     * ADR 0019 — tanggal milik periode. BR-FE-36 — langkah yang dinilai butuh Batas Evaluasi.
     * Jadwal relatif terhadap `$opens`: periode tahun lalu dibuka 1 Januari, tahun ini dibuka sekarang.
     *
     * @param  list<int>  $phaseIds
     */
    private static function period(User $admin, string $name, array $phaseIds, CarbonInterface $opens): SubmissionPeriod
    {
        $at = fn (int $months): string => Carbon::instance($opens)->addMonths($months)->setTime(23, 59)->format('Y-m-d H:i:s');

        return CreateSubmissionPeriod::run(new SubmissionPeriodInput($name, [
            new PeriodDateInput(DeadlineType::Submit, null, $at(3)),
            new PeriodDateInput(DeadlineType::Evaluation, null, $at(4)),
            new PeriodDateInput(DeadlineType::Revision, null, $at(5)),
            new PeriodDateInput(DeadlineType::Submit, 1, $at(8)),
            new PeriodDateInput(DeadlineType::Evaluation, 1, $at(9)),
            new PeriodDateInput(DeadlineType::Output, null, $at(11)),
        ], $phaseIds), $admin);
    }

    private static function announcements(User $admin): void
    {
        $items = [
            ['Pembukaan Pengajuan Penelitian dan Pengabdian', 'public', []],
            ['Panduan Pengisian Rencana Anggaran', 'public', []],
            ['Jadwal Penilaian Proposal', 'private', ['reviewer_internal', 'reviewer_external']],
            ['Perubahan Jadwal Pengumpulan Laporan', 'public', []],
        ];

        $published = [];
        foreach ($items as [$title, $type, $roles]) {
            $published[] = PublishAnnouncement::run(new AnnouncementInput(
                title: $title,
                content: "<p>{$title}. Informasi lengkap tersedia di halaman SIMPAS.</p>",
                type: $type,
                expiredAt: null,
                targetRoles: $roles,
            ), $admin);
        }

        // Satu pengumuman ditarik, supaya jejaknya ada di log audit.
        RetractAnnouncement::run($published[3], $admin);
    }
}
