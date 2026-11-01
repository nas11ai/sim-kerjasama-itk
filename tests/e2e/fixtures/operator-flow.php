<?php

// Fixture smoke S4 (tests/e2e/smoke.spec.ts, E2E_FIXTURE=operator-flow), di atas EndToEndFlowSeeder:
// satu pengajuan SUBMITTED milik peneliti@itk.ac.id pada skema DIPA-2026, plus satu form evaluasi
// pada detail Pengajuan. Jalankan pada database uji, jangan pada data sungguhan:
//   php tests/e2e/fixtures/operator-flow.php

use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Submitted;
use App\Support\Audit\AuditTrail;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$lead = User::where('email', 'peneliti@itk.ac.id')->sole();
$form = Form::where('title', 'Pengajuan Penelitian 2026')->sole();
$detail = $form->formAccessControls()->sole()->formPhaseDetails()->sole();

$submission = FormSubmission::factory()->create([
    'form_id' => $form->id,
    'form_phase_detail_id' => $detail->id,
    'scheme_id' => Scheme::where('code', 'DIPA-2026')->sole()->id,
    'submitted_by' => $lead->id,
    'is_submitted' => true,
    'status' => Submitted::class,
]);
AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, $submission, $lead);

$evaluation = ReviewEvaluationForm::firstOrCreate(
    ['title' => 'Penilaian Substansi', 'form_phase_detail_id' => $detail->id],
    ['is_active' => true, 'is_required' => true, 'order' => 1],
);
ReviewFormField::firstOrCreate(
    ['review_evaluation_form_id' => $evaluation->id, 'label' => 'Kebaruan Penelitian'],
    ['field_type_id' => FieldType::firstOrCreate(['name' => 'text'])->id, 'is_required' => false, 'order' => 1],
);

echo "submission={$submission->id}\n";
