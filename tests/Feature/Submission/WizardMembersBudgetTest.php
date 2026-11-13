<?php

// tests/Feature/Submission/WizardMembersBudgetTest.php
//
// Read model langkah 4 (Anggota, RESEARCHER-02-08) dan langkah 5 (Anggaran, RESEARCHER-02-04).
// Batas dibaca dari aturan skema yang sama dengan Action penambahnya, supaya layar dan
// penolakan tidak pernah berselisih.

use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Partner;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\Scheme;
use App\Models\StudentMember;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Submission\ResearchMemberCandidates;
use App\Queries\Submission\SubmissionWizard;
use App\States\Submission\Draft;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @param  array<string, mixed>  $attributes */
function membersDraft(array $attributes = []): FormSubmission
{
    $fx = wizardFixture();

    return FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Draft::class,
        ...$attributes,
    ]);
}

function lecturer(string $name, ?string $nidn = null): User
{
    $user = User::factory()->create(['name' => $name]);
    UserProfile::factory()->create(['user_id' => $user->id, 'nidn' => $nidn]);
    Permission::findOrCreate('submissions.create', 'web');
    $user->givePermissionTo('submissions.create');

    return $user->refresh();
}

function joinAsMember(FormSubmission $submission, User $user, string $status = 'active'): ResearchMember
{
    return $submission->researchMembers()->create([
        'user_profile_id' => $user->userProfile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => $status,
    ]);
}

function budgetItem(FormSubmission $submission, BudgetComponent $component, int $volume, int $price): BudgetLineItem
{
    return BudgetLineItem::create([
        'form_submission_id' => $submission->id,
        'budget_component_id' => $component->id,
        'item_name' => 'Item '.$component->name,
        'volume' => $volume,
        'unit' => 'paket',
        'unit_price' => $price,
        'total' => $volume * $price,
    ]);
}

function withSchemeSelector(FormSubmission $submission): void
{
    FormField::create([
        'form_id' => $submission->form_id,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'scheme_selector'])->id,
        'label' => 'Skema',
        'is_required' => true,
        'order' => 1,
    ]);
}

// ---------- langkah 4: anggota ----------

it('[SM-MEMBER-06] ketua tercantum dan tidak bisa dikeluarkan', function () {
    $submission = membersDraft();
    $member = joinAsMember($submission, lecturer('Dewi Anggota', '0011223344'));

    $members = SubmissionWizard::run($submission->submittedBy, $submission, 4)['members'];

    expect($members->members)->toHaveCount(2)
        ->and($members->members[0]->id)->toBe(0)
        ->and($members->members[0]->name)->toBe($submission->submittedBy->name)
        ->and($members->members[0]->roleLabel)->toBe('Ketua')
        ->and($members->members[0]->removable)->toBeFalse()
        ->and($members->members[1]->id)->toBe($member->id)
        ->and($members->members[1]->roleLabel)->toBe('Co-Investigator')
        ->and($members->members[1]->nidn)->toBe('0011223344')
        ->and($members->members[1]->removable)->toBeTrue();
});

it('anggota biasa melihat anggota lain tanpa tombol keluarkan', function () {
    $submission = membersDraft();
    $viewer = lecturer('Budi Anggota');
    joinAsMember($submission, $viewer);

    $members = SubmissionWizard::run($viewer, $submission, 4)['members'];

    expect($members->members[1]->removable)->toBeFalse();
});

it('[SM-MEMBER-03] batas anggota dari skema', function () {
    $scheme = Scheme::factory()->create(['max_members' => 3, 'rules' => ['max_student_members' => 2]]);
    $submission = membersDraft(['scheme_id' => $scheme->id]);

    $members = SubmissionWizard::run($submission->submittedBy, $submission, 4)['members'];

    expect($members->memberLimit)->toBe(3)
        ->and($members->studentLimit)->toBe(2)
        ->and($members->roleOptions)->toBe([
            ['value' => ResearchMember::ROLE_CO_INVESTIGATOR, 'label' => 'Co-Investigator'],
            ['value' => ResearchMember::ROLE_MEMBER, 'label' => 'Anggota'],
        ]);
});

it('[SM-STUDENT-03] skema tanpa batas mahasiswa', function () {
    $scheme = Scheme::factory()->create(['rules' => []]);
    $submission = membersDraft(['scheme_id' => $scheme->id]);

    $members = SubmissionWizard::run($submission->submittedBy, $submission, 4)['members'];

    expect($members->studentLimit)->toBeNull();
});

it('tanpa skema tidak ada batas anggota', function () {
    $submission = membersDraft();

    $members = SubmissionWizard::run($submission->submittedBy, $submission, 4)['members'];

    expect($members->memberLimit)->toBeNull()->and($members->studentLimit)->toBeNull();
});

it('anggota nonaktif tidak tercantum', function () {
    $submission = membersDraft();
    joinAsMember($submission, lecturer('Keluar Dulu'), 'inactive');

    $members = SubmissionWizard::run($submission->submittedBy, $submission, 4)['members'];

    expect($members->members)->toHaveCount(1);
});

it('mahasiswa dan mitra tercantum', function () {
    $submission = membersDraft();
    StudentMember::create(['form_submission_id' => $submission->id, 'name' => 'Sari', 'student_id' => '11221001', 'study_program' => 'Informatika']);
    Partner::create(['form_submission_id' => $submission->id, 'institution_name' => 'PT Mitra', 'contact_person' => 'Pak Andi']);

    $members = SubmissionWizard::run($submission->submittedBy, $submission, 4)['members'];

    expect($members->students[0]->studentId)->toBe('11221001')
        ->and($members->students[0]->studyProgram)->toBe('Informatika')
        ->and($members->partners[0]->institutionName)->toBe('PT Mitra')
        ->and($members->partners[0]->contactPerson)->toBe('Pak Andi');
});

it('langkah selain 4 tidak memuat anggota', function () {
    $submission = membersDraft();

    expect(SubmissionWizard::run($submission->submittedBy, $submission, 2)['members'])->toBeNull()
        ->and(SubmissionWizard::run($submission->submittedBy, $submission, 2)['budget'])->toBeNull();
});

// ---------- kandidat anggota ----------

it('mencari nama tanpa peka huruf', function () {
    $submission = membersDraft();
    $found = lecturer('Rina Wulandari');
    lecturer('Tono Sutono');

    $candidates = ResearchMemberCandidates::run($submission, 'wULAN');

    expect(array_map(fn ($c) => $c->id, $candidates))->toBe([$found->id])
        ->and($candidates[0]->blockedReason)->toBeNull();
});

it('mencari NIDN', function () {
    $submission = membersDraft();
    $found = lecturer('Agus', '0099887766');

    expect(array_map(fn ($c) => $c->id, ResearchMemberCandidates::run($submission, '99887')))->toBe([$found->id]);
});

it('[SM-MEMBER-01] reviewer pengajuan ini ditandai tidak bisa ditambahkan', function () {
    $submission = membersDraft();
    $person = lecturer('Penilai Rahmat');
    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create(['user_id' => $person->id])->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);

    $candidates = ResearchMemberCandidates::run($submission, 'Rahmat');

    expect($candidates[0]->blockedReason)
        ->toBe('Penilai Rahmat sedang ditugaskan sebagai penilai pengajuan ini, jadi ia tidak dapat ditambahkan sebagai anggota.');
});

it('anggota yang sudah ada tidak ditawarkan', function () {
    $submission = membersDraft(['submitted_by' => lecturer('Ketua Lestari')->id]);
    joinAsMember($submission, lecturer('Anggota Lestari'));
    $left = lecturer('Mantan Lestari');
    joinAsMember($submission, $left, 'inactive');

    $candidates = ResearchMemberCandidates::run($submission, 'Lestari');

    expect(array_map(fn ($c) => $c->id, $candidates))->toBe([$left->id]);
});

it('tanpa izin submissions.create tidak ditawarkan', function () {
    $submission = membersDraft();
    $user = User::factory()->create(['name' => 'Staf Tanpa Izin']);
    UserProfile::factory()->create(['user_id' => $user->id]);

    expect(ResearchMemberCandidates::run($submission, 'Staf'))->toBe([]);
});

it('[BR-IAM-05] akun nonaktif tidak ditawarkan', function () {
    $submission = membersDraft();
    lecturer('Pensiun Purnama')->update(['is_active' => false]);

    expect(ResearchMemberCandidates::run($submission, 'Purnama'))->toBe([]);
});

it('pencarian pendek tidak memuat apa pun', function () {
    $submission = membersDraft();
    lecturer('A Pendek');

    expect(ResearchMemberCandidates::run($submission, 'A'))->toBe([]);
});

it('kandidat paling banyak sepuluh', function () {
    $submission = membersDraft();
    foreach (range(1, 12) as $i) {
        lecturer("Banyak Dosen {$i}");
    }

    expect(ResearchMemberCandidates::run($submission, 'Banyak'))->toHaveCount(10);
});

it('memberCandidates dimuat lewat partial reload', function () {
    $submission = membersDraft();
    $found = lecturer('Partial Reload');

    $this->actingAs($submission->submittedBy)
        ->get(route('user.submissions.wizard', [$submission, 4]).'?member_search=Partial')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('memberCandidates')
            ->reloadOnly('memberCandidates', fn (Assert $reload) => $reload
                ->where('memberCandidates.0.id', $found->id)));
});

// ---------- langkah 5: anggaran ----------

it('[SM-WIZARD-04] langkah 5 terkunci tanpa skema', function () {
    $submission = membersDraft();
    withSchemeSelector($submission);

    $budget = SubmissionWizard::run($submission->submittedBy, $submission, 5)['budget'];

    expect($budget->blockedReason)->toBe('Pilih skema di langkah 3 lebih dulu, batas anggaran berasal dari skema.')
        ->and($budget->maxBudget)->toBeNull();
});

it('[SM-WIZARD-09] form tanpa skema tanpa batas', function () {
    $submission = membersDraft();

    $budget = SubmissionWizard::run($submission->submittedBy, $submission, 5)['budget'];

    expect($budget->blockedReason)->toBeNull()
        ->and($budget->maxBudget)->toBeNull()
        ->and($budget->editable)->toBeTrue();
});

it('batas anggaran dari skema', function () {
    $scheme = Scheme::factory()->create(['max_budget' => 50_000_000]);
    $submission = membersDraft(['scheme_id' => $scheme->id]);
    withSchemeSelector($submission);

    $budget = SubmissionWizard::run($submission->submittedBy, $submission, 5)['budget'];

    expect($budget->blockedReason)->toBeNull()->and($budget->maxBudget)->toBe(50_000_000);
});

it('[BUD-ITEM-03] komponen nonaktif tidak ditawarkan', function () {
    $submission = membersDraft();
    $active = BudgetComponent::factory()->create(['name' => 'Bahan Habis Pakai']);
    BudgetComponent::factory()->inactive()->create(['name' => 'Komponen Usang']);

    $components = SubmissionWizard::run($submission->submittedBy, $submission, 5)['budget']->components;

    expect($components)->toContain(['value' => (string) $active->id, 'label' => 'Bahan Habis Pakai'])
        ->and(array_column($components, 'label'))->not->toContain('Komponen Usang');
});

it('[BUD-ITEM-04] item lama dengan komponen nonaktif tetap tampil', function () {
    $submission = membersDraft();
    $component = BudgetComponent::factory()->inactive()->create(['name' => 'Komponen Usang']);
    $item = budgetItem($submission, $component, 2, 500_000);

    $items = SubmissionWizard::run($submission->submittedBy, $submission, 5)['budget']->items;

    expect($items)->toHaveCount(1)
        ->and($items[0]->id)->toBe($item->id)
        ->and($items[0]->componentId)->toBe($component->id)
        ->and($items[0]->component)->toBe('Komponen Usang')
        ->and($items[0]->total)->toBe(1_000_000);
});

it('total = jumlah item', function () {
    $submission = membersDraft();
    $component = BudgetComponent::factory()->create();
    budgetItem($submission, $component, 2, 500_000);
    budgetItem($submission, $component, 3, 100_000);

    expect(SubmissionWizard::run($submission->submittedBy, $submission, 5)['budget']->total)->toBe(1_300_000);
});

it('[BUD-WHO-03] anggota biasa tidak bisa menyunting anggaran', function () {
    $submission = membersDraft();
    $viewer = lecturer('Anggota Biasa');
    $submission->researchMembers()->create([
        'user_profile_id' => $viewer->userProfile->id,
        'role' => ResearchMember::ROLE_MEMBER,
        'status' => 'active',
    ]);

    expect(SubmissionWizard::run($viewer, $submission, 5)['budget']->editable)->toBeFalse();
});
