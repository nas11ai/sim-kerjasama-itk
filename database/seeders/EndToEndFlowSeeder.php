<?php

namespace Database\Seeders;

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormField;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormType;
use App\Models\Organization;
use App\Models\PhaseType;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\SchemeType;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

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

        $researcher = $this->user('peneliti@itk.ac.id', 'Dr. Budi Santoso', $organization);
        Permission::findOrCreate('submissions.create', 'web');
        $researcher->givePermissionTo('submissions.create');

        $operator = $this->user('operator@itk.ac.id', 'Operator LPPM', $organization);
        Permission::findOrCreate('users.manage', 'web');
        Permission::findOrCreate('submissions.view-all', 'web');
        // BR-REV-01 — penugasan reviewer dijaga Policy `assignReviewers` (reviewers.assign).
        Permission::findOrCreate('reviewers.assign', 'web');
        $operator->givePermissionTo(['users.manage', 'submissions.view-all', 'reviewers.assign']);

        foreach (['reviewer1@itk.ac.id' => 'Dr. Sari Wijaya', 'reviewer2@itk.ac.id' => 'Dr. Andi Mappa'] as $email => $name) {
            $user = $this->user($email, $name, $organization);
            // REVIEWER-03-* dijaga permission, bukan sekadar baris Reviewer.
            $user->givePermissionTo(Permission::findOrCreate('reviewers.evaluate', 'web'));

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

        $this->phaseDetail($form, $phase, $period, $organization);

        $this->command->info('Selesai. Coba alurnya:');
        $this->command->info('  1. Masuk sebagai peneliti@itk.ac.id, buat pengajuan pada Form "'.$form->title.'"');
        $this->command->info('  2. Masuk sebagai operator@itk.ac.id, tugaskan '.$scheme->rules['min_reviewer_count'].' reviewer');
        $this->command->info('  3. Setelah evaluasi masuk dan ringkasan ditutup, status menjadi Disetujui');
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
            ['organization_id' => $organization->id]
        );

        return $user;
    }

    private function scheme(): Scheme
    {
        $schemeType = SchemeType::firstOrCreate(['name' => 'Penelitian Dasar']);
        $submissionType = SubmissionType::firstOrCreate(['name' => 'Research']);

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
            ]
        );

        $text = FieldType::firstOrCreate(['name' => 'text']);
        $textarea = FieldType::firstOrCreate(['name' => 'textarea']);

        $fields = [
            ['Judul Penelitian', $text, true, 1],
            ['Abstrak', $textarea, true, 2],
            ['Kata Kunci', $text, false, 3],
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

    private function phaseDetail(Form $form, FormPhase $phase, SubmissionPeriod $period, Organization $organization): void
    {
        $accessControl = FormAccessControl::where([
            'form_id' => $form->id,
            'organization_id' => $organization->id,
        ])->firstOrFail();

        $label = SubmissionDateLabel::firstOrCreate(['name' => 'Batas Submit']);

        $date = SubmissionDate::firstOrCreate(
            [
                'submission_date_label_id' => $label->id,
                'submission_period_id' => $period->id,
            ],
            ['datetime' => now()->addMonths(3)]
        );

        $phaseType = PhaseType::firstOrCreate(['name' => 'Pengajuan']);

        FormPhaseDetail::firstOrCreate(
            [
                'form_phase_id' => $phase->id,
                'form_access_control_id' => $accessControl->id,
            ],
            [
                'phase_type_id' => $phaseType->id,
                'order' => 1,
                'submission_date_id' => $date->id,
                // Fase ini memang direview — persetujuan hampa tidak berlaku
                // di sini (ADR 0017).
                'needs_review' => true,
            ]
        );
    }
}
