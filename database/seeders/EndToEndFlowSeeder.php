<?php

namespace Database\Seeders;

use App\Actions\Form\PublishPhaseVersion;
use App\Actions\Review\CreateEvaluationForm;
use App\Actions\Review\PublishEvaluationFormVersion;
use App\Data\Request\EvaluationFormDetailsInput;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseStructureInput;
use App\Data\Request\ReviewFormFieldInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Enums\DeadlineType;
use App\Enums\VerificationStatus;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Models\SubmissionType;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\SubmissionTitleField;
use Illuminate\Database\Seeder;

/**
 * Data minimum supaya alur pengajuan bisa dicoba langsung di dev:
 * peneliti mengajukan → operator menugaskan reviewer → disetujui.
 *
 * Sengaja idempoten (firstOrCreate di mana-mana) supaya bisa dijalankan
 * berulang tanpa menumpuk data.
 *
 *   php artisan db:seed --class=EndToEndFlowSeeder
 *
 * Akun yang dibuat — kata sandi sama dengan emailnya:
 *   peneliti@itk.ac.id   mengajukan
 *   operator@itk.ac.id   menugaskan reviewer, menutup ringkasan
 *   reviewer1@itk.ac.id  mengevaluasi
 *   reviewer2@itk.ac.id  mengevaluasi
 */
class EndToEndFlowSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['name' => 'Teknik Informatika'],
            ['type' => 'study_program']
        );

        // Peran DDD dari matriks PermissionSeeder, bukan permission pilihan tangan: peneliti
        // butuh submissions.view-own untuk membuka daftar pengajuannya sendiri.
        $this->call(PermissionSeeder::class);

        $this->user('peneliti@itk.ac.id', 'Dr. Budi Santoso', $organization)->assignRole('researcher');

        $operator = $this->user('operator@itk.ac.id', 'Operator LPPM', $organization);
        // BR-REV-01 — penugasan reviewer dijaga Policy `assignReviewers` (reviewers.assign, peran operator).
        $operator->assignRole('operator');
        $operator->givePermissionTo('users.manage');

        foreach (['reviewer1@itk.ac.id' => 'Dr. Sari Wijaya', 'reviewer2@itk.ac.id' => 'Dr. Andi Mappa'] as $email => $name) {
            $user = $this->user($email, $name, $organization);
            // REVIEWER-03-* dijaga permission, bukan sekadar baris Reviewer.
            $user->assignRole('reviewer_internal');

            Reviewer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'reviewer_type' => 'internal',
                    'start_date' => now()->subMonth(),
                    'end_date' => now()->addYear(),
                ]
            );
        }

        $scheme = $this->scheme();
        $period = $this->period();
        [$form, $phase] = $this->form($organization);

        $this->phaseDetail($form, $phase, $period, $organization, $operator);

        $this->command->info('Selesai. Coba alurnya:');
        $this->command->info('  1. Masuk sebagai peneliti@itk.ac.id, buat pengajuan pada Form "'.$form->title.'"');
        $this->command->info('  2. Masuk sebagai operator@itk.ac.id, tugaskan '.$scheme->rules['min_reviewer_count'].' reviewer');
        $this->command->info('  3. Kedua reviewer mengirim evaluasi; tanpa ulir terbuka, status menjadi Disetujui (BR-REV-05)');
    }

    private function user(string $email, string $name, Organization $organization): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                // Kata sandi = email. Hanya untuk dev; jangan dipakai di mana pun lagi.
                'password' => bcrypt($email),
                'email_verified_at' => now(),
            ]
        );

        UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            // BR-IAM-04 — akun `pending` bisa menyiapkan pengajuan, tapi tidak mengirimnya.
            ['organization_id' => $organization->id, 'verification_status' => VerificationStatus::Active]
        );

        return $user;
    }

    private function scheme(): Scheme
    {
        $schemeType = SchemeType::firstOrCreate(['name' => 'Penelitian Dasar']);
        $submissionType = SubmissionType::firstOrCreate(['name' => 'Penelitian']);

        return Scheme::firstOrCreate(
            ['code' => 'DIPA-2026'],
            [
                'name' => 'Penelitian DIPA 2026',
                'scheme_type_id' => $schemeType->id,
                'submission_type_id' => $submissionType->id,
                'max_budget' => 75_000_000,
                'max_members' => 5,
                'duration_months' => 12,
                // Ambang reviewer milik skema, bukan konstanta di kode (BR-REV-01).
                'rules' => ['min_reviewer_count' => 2, 'max_reviewer_workload' => 10],
                'is_active' => true,
            ]
        );
    }

    private function period(): SubmissionPeriod
    {
        return SubmissionPeriod::firstOrCreate(
            ['name' => 'Periode 2026'],
            ['is_force_closed' => false]
        );
    }

    /** @return array{0: Form, 1: FormPhase} */
    private function form(Organization $organization): array
    {
        $formType = FormType::firstOrCreate(['name' => 'Pengajuan']);

        $form = Form::firstOrCreate(
            ['title' => 'Pengajuan Penelitian 2026'],
            [
                'description' => 'Form pengajuan penelitian untuk periode 2026.',
                'form_type_id' => $formType->id,
                'is_active' => true,
            ]
        );
        // BR-FE-34 — field di bawah ditulis langsung, jadi Form ini dianggap sudah terbit (G3-W6).
        $form->forceFill(['published_at' => $form->published_at ?? now()])->save();

        $text = FieldType::firstOrCreate(['name' => 'text']);
        $textarea = FieldType::firstOrCreate(['name' => 'textarea']);

        // BR-SCH-04 — tanpa pemilih skema, batas anggaran dan jumlah reviewer skema tidak berlaku.
        $schemeSelector = FieldType::firstOrCreate(['name' => 'scheme_selector']);
        // BR-SM-22 — jawabannya menjadi judul pengajuan di daftar dan detail.
        $submissionTitle = FieldType::firstOrCreate(['name' => SubmissionTitleField::TYPE]);

        $fields = [
            ['Judul Penelitian', $submissionTitle, true, 1],
            ['Abstrak', $textarea, true, 2],
            ['Kata Kunci', $text, false, 3],
            ['Skema Penelitian', $schemeSelector, true, 4],
        ];

        foreach ($fields as [$label, $type, $required, $order]) {
            FormField::firstOrCreate(
                ['form_id' => $form->id, 'label' => $label],
                [
                    'field_type_id' => $type->id,
                    'is_required' => $required,
                    'order' => $order,
                    // BR-FE-18 — field yang lahir wajib berlaku sejak dibuat.
                    'required_since' => $required ? now()->subYear() : null,
                ]
            );
        }

        $phase = FormPhase::firstOrCreate(
            ['title' => 'Fase Pengajuan 2026'],
            ['is_active' => true]
        );

        FormAccessControl::firstOrCreate([
            'form_id' => $form->id,
            'permission' => 'submissions.create',
            'organization_id' => $organization->id,
        ]);

        return [$form, $phase];
    }

    private function phaseDetail(Form $form, FormPhase $phase, SubmissionPeriod $period, Organization $organization, User $actor): void
    {
        $accessControl = FormAccessControl::where([
            'form_id' => $form->id,
            'organization_id' => $organization->id,
        ])->firstOrFail();

        // ADR 0019 — langkah membaca Batas Submit dari kalender periode ini.
        SubmissionDate::firstOrCreate(
            [
                'type' => DeadlineType::Submit,
                'submission_period_id' => $period->id,
            ],
            ['datetime' => now()->addMonths(3)]
        );
        // BR-FE-36 — langkah Pengajuan direview, jadi angkatannya baru siap bila Batas Evaluasi ada.
        SubmissionDate::firstOrCreate(
            [
                'type' => DeadlineType::Evaluation,
                'submission_period_id' => $period->id,
            ],
            ['datetime' => now()->addMonths(4)]
        );

        // ADR 0014 — periode menawarkan form lewat tahap yang ditautkan kepadanya; tanpa tautan
        // ini langkah 1 wizard berbunyi "Belum ada periode pengajuan yang dibuka".
        SubmissionPeriodPhase::firstOrCreate([
            'submission_period_id' => $period->id,
            'form_phase_id' => $phase->id,
        ]);

        // BR-FE-32 — langkah lahir lewat penerbitan Versi 1 tahap; idempoten per tahap.
        if ($phase->latestVersion()->exists()) {
            return;
        }

        // W158 — AssignReviewers membagikan form evaluasi aktif langkah ini ke tiap reviewer yang
        // ditugaskan, jadi keputusan Operator punya dasar penilaian. BR-REV-18 — lewat jalur pustaka.
        $evaluation = CreateEvaluationForm::run(new EvaluationFormDetailsInput('Penilaian Substansi', null), $actor);
        PublishEvaluationFormVersion::run($evaluation, [
            new ReviewFormFieldInput(null, FieldType::firstOrCreate(['name' => 'text'])->id, 'Kebaruan Penelitian', null, false, null, null),
        ], $actor);

        PublishPhaseVersion::run($phase, new PhaseStructureInput([new FormPhaseDetailInput(
            id: null,
            formAccessControlId: $accessControl->id,
            phaseTypeId: PhaseType::firstOrCreate(['name' => 'Pengajuan'])->id,
            // Fase ini memang direview — persetujuan hampa tidak berlaku di sini (ADR 0017).
            needsReview: true,
            deadlineType: DeadlineType::Submit,
            deadlineCycle: null,
            evaluationForms: [new StepEvaluationFormInput($evaluation->id, isRequired: true, isActive: true)],
        )]), $actor);
    }
}
