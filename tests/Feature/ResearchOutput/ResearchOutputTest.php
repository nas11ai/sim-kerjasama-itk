<?php

// tests/Feature/ResearchOutput/ResearchOutputTest.php
//
// BR-RO-01 s/d BR-RO-09 — luaran yang benar-benar terwujud.
//
// Bedakan dari Target Luaran (BR-SM-14/15) yang merupakan luaran yang
// **dijanjikan**. Kecocokan keduanya sengaja tidak ditegakkan (register #19):
// penelitian memang menyimpang, dan memaksanya cocok mendorong peneliti
// berbohong di target atau menyembunyikan luaran nyata.

use App\Actions\Override\GrantPhaseOverride;
use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\ResearchOutput;
use App\Models\Scheme;
use App\Models\SubmissionType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function outputType(string $code, array $override = []): OutputTypeDefinition
{
    return OutputTypeDefinition::updateOrCreate(['code' => $code], array_merge([
        'name' => ucfirst($code),
        'is_active' => true,
        'requires_files' => false,
        'submission_type' => null,
        'fields' => [],
    ], $override));
}

/** @return array{submission: FormSubmission, owner: User} */
function outputFixture(string $status = Approved::class, ?string $submissionTypeName = null): array
{
    $owner = verifiedResearcher();

    $schemeId = null;

    if ($submissionTypeName !== null) {
        $type = SubmissionType::firstOrCreate(['name' => $submissionTypeName]);
        $schemeId = Scheme::factory()->create(['submission_type_id' => $type->id])->id;
    }

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'scheme_id' => $schemeId,
        'status' => $status,
        'is_submitted' => true,
    ]);

    return ['submission' => $submission, 'owner' => $owner];
}

it('[RO-OUTPUT-01] luaran ditolak pada submission yang belum APPROVED', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(UnderReview::class);
    outputType('publication');

    expect(fn () => SaveResearchOutput::run($s, $owner, ['output_type' => 'publication']))
        ->toThrow(ValidationException::class)
        ->and(ResearchOutput::count())->toBe(0);
});

it('[RO-OUTPUT-02] submission APPROVED bisa menerima luaran', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('publication');

    $output = SaveResearchOutput::run($s, $owner, ['output_type' => 'publication']);

    expect($output->form_submission_id)->toBe($s->id)
        ->and($output->created_by)->toBe($owner->id);
});

it('[RO-OUTPUT-03] tipe pks ditolak di luar CommunityService', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'Research');
    outputType('pks', ['submission_type' => 'CommunityService']);

    expect(fn () => SaveResearchOutput::run($s, $owner, ['output_type' => 'pks']))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-04] tipe pks diterima pada CommunityService', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'CommunityService');
    outputType('pks', ['submission_type' => 'CommunityService']);

    $output = SaveResearchOutput::run($s, $owner, ['output_type' => 'pks']);

    expect($output->output_type)->toBe('pks');
});

it('[RO-OUTPUT-05] PKS yang masih berjalan boleh tanpa end_date', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'CommunityService');
    outputType('pks', [
        'submission_type' => 'CommunityService',
        'fields' => [
            ['key' => 'title', 'required' => true],
            // end_date tidak wajib: kerja sama yang masih berjalan memang belum
            // punya tanggal berakhir, dan memaksanya membuat orang mengarang.
            ['key' => 'end_date', 'required' => false],
        ],
    ]);

    $output = SaveResearchOutput::run($s, $owner, [
        'output_type' => 'pks',
        'metadata' => ['title' => 'Kerja sama pendampingan UMKM'],
    ]);

    expect($output->metadata['end_date'] ?? null)->toBeNull();
});

it('[RO-OUTPUT-06] tipe ip tanpa berkas ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('ip', ['requires_files' => true]);

    expect(fn () => SaveResearchOutput::run($s, $owner, ['output_type' => 'ip']))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-07] tipe ip dengan satu berkas tersimpan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('ip', ['requires_files' => true]);

    $output = SaveResearchOutput::run($s, $owner, [
        'output_type' => 'ip',
        'files' => [['file_path' => 'luaran/paten.pdf']],
    ]);

    expect($output->files()->count())->toBe(1);
});

it('[RO-OUTPUT-17] tipe prototype tanpa berkas ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('prototype', ['requires_files' => true]);

    // BR-RO-04 menyebut DUA tipe. Implementasi yang menuliskan `ip` saja lolos
    // tanpa ada yang gagal — sampai ada prototipe kosong masuk laporan LPPM.
    expect(fn () => SaveResearchOutput::run($s, $owner, ['output_type' => 'prototype']))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-18] tipe prototype dengan satu berkas tersimpan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('prototype', ['requires_files' => true]);

    $output = SaveResearchOutput::run($s, $owner, [
        'output_type' => 'prototype',
        'files' => [['file_path' => 'luaran/prototipe.zip']],
    ]);

    expect($output->files()->count())->toBe(1);
});

it('[RO-OUTPUT-08] tipe di luar output_type_definitions ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();

    expect(fn () => SaveResearchOutput::run($s, $owner, ['output_type' => 'sertifikat-halal']))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-09] tipe baru yang terdaftar langsung bisa dipakai', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();

    // Menambah tipe adalah perubahan data, bukan perubahan kode.
    outputType('sertifikat-halal');

    $output = SaveResearchOutput::run($s, $owner, ['output_type' => 'sertifikat-halal']);

    expect($output->output_type)->toBe('sertifikat-halal');
});

it('[RO-OUTPUT-21] metadata wajib yang kosong ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('article', ['fields' => [['key' => 'title', 'required' => true]]]);

    expect(fn () => SaveResearchOutput::run($s, $owner, [
        'output_type' => 'article',
        'metadata' => ['journal_name' => 'Jurnal Uji'],
    ]))->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-22] metadata yang tidak wajib boleh kosong', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('article', ['fields' => [
        ['key' => 'title', 'required' => true],
        ['key' => 'doi', 'required' => false],
    ]]);

    $output = SaveResearchOutput::run($s, $owner, [
        'output_type' => 'article',
        'metadata' => ['title' => 'Optimasi Nutrisi Hidroponik'],
    ]);

    expect($output->metadata['title'])->toBe('Optimasi Nutrisi Hidroponik');
});

it('[RO-OUTPUT-06] luaran ditolak setelah pengajuan ditarik', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('publication');

    $s->status->transitionTo(Withdrawn::class);

    expect(fn () => SaveResearchOutput::run($s->fresh(), $owner, ['output_type' => 'publication']))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-10] luaran tetap ada sebagai data historis setelah penarikan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('publication');

    $output = SaveResearchOutput::run($s, $owner, ['output_type' => 'publication']);

    $s->status->transitionTo(Withdrawn::class);

    expect(ResearchOutput::find($output->id))->not->toBeNull()
        ->and($output->fresh()->output_type)->toBe('publication');
});

it('[RO-OUTPUT-11] submitted_by boleh menyunting luaran', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();

    expect($owner->can('manageResearchOutput', $s))->toBeTrue();
});

it('[RO-OUTPUT-12] co-investigator juga boleh menyunting luaran', function () {
    ['submission' => $s] = outputFixture();

    $co = verifiedResearcher();
    ResearchMember::create([
        'form_submission_id' => $s->id,
        'user_profile_id' => $co->userProfile->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    expect($co->can('manageResearchOutput', $s))->toBeTrue();
});

it('[RO-OUTPUT-13] member biasa hanya bisa membaca luaran', function () {
    ['submission' => $s] = outputFixture();

    $member = verifiedResearcher();
    ResearchMember::create([
        'form_submission_id' => $s->id,
        'user_profile_id' => $member->userProfile->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    expect($member->can('manageResearchOutput', $s))->toBeFalse();
});

it('[RO-OUTPUT-19] reviewer hanya bisa membaca luaran', function () {
    ['submission' => $s] = outputFixture();

    // Reviewer menilai; ia tidak melaporkan — dan luaran adalah laporan
    // peneliti. Satu-satunya peran di tabel Access Control yang tidak pernah
    // punya skenario sampai audit M4.
    $reviewer = verifiedResearcher();

    expect($reviewer->can('manageResearchOutput', $s))->toBeFalse();
});

it('[RO-OUTPUT-20] Operator pemegang outputs.manage boleh menyunting luaran', function () {
    ['submission' => $s] = outputFixture();

    Permission::findOrCreate('outputs.manage', 'web');
    $operator = verifiedResearcher();
    $operator->givePermissionTo('outputs.manage');

    // Register #35 — sengaja berbeda dari anggaran: anggaran komitmen peneliti,
    // luaran laporan fakta yang memasok pelaporan LPPM.
    expect($operator->fresh()->can('manageResearchOutput', $s))->toBeTrue();
});

// BR-RO-08 — sumbu **kapan**. Batasnya milik FormPhaseDetail "Upload Luaran",
// bukan mekanisme tersendiri (ADR 0014). Membaca setelah tenggat tidak butuh
// override; hanya menulis yang tertutup (BR-FE-15) — membedakannya memangkas
// jumlah perpanjangan yang harus diterbitkan Operator.

it('[RO-OUTPUT-14] lewat batas Upload Luaran, penyuntingan ditolak', function () {
    $this->travelTo('2026-12-10 09:00:00');

    $fx = wizardFixture();
    outputType('publication');

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $fx['detail']->submissionDate->update(['datetime' => '2026-12-01 23:59:00']);

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.outputs.store', $submission), [
            'output_type' => 'publication',
        ])
        ->assertForbidden();

    expect(ResearchOutput::count())->toBe(0);
});

it('[RO-OUTPUT-15] lewat batas, luaran lama tetap terbaca tanpa override', function () {
    $fx = wizardFixture();
    outputType('publication');

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $output = SaveResearchOutput::run($submission, $fx['user'], ['output_type' => 'publication']);

    $this->travelTo('2026-12-10 09:00:00');
    $fx['detail']->submissionDate->update(['datetime' => '2026-12-01 23:59:00']);

    // Membaca tidak pernah bergerbang deadline — yang tertutup hanya menulis.
    $this->actingAs($fx['user'])
        ->get(route('user.submissions.show', $submission))
        ->assertOk();

    expect(ResearchOutput::find($output->id))->not->toBeNull();
});

it('[RO-OUTPUT-16] override yang berlaku membuka kembali penyuntingan luaran', function () {
    $this->travelTo('2026-12-10 09:00:00');

    $fx = wizardFixture();
    outputType('publication');

    $submission = FormSubmission::factory()->create([
        'form_id' => $fx['form']->id,
        'submitted_by' => $fx['user']->id,
        'submission_period_id' => $fx['period']->id,
        'form_phase_detail_id' => $fx['detail']->id,
        'status' => Approved::class,
        'is_submitted' => true,
    ]);

    $fx['detail']->submissionDate->update(['datetime' => '2026-12-01 23:59:00']);

    Permission::findOrCreate('submissions.view-all', 'web');
    $operator = verifiedResearcher();
    $operator->givePermissionTo('submissions.view-all');

    GrantPhaseOverride::run(
        $submission,
        $fx['detail']->fresh(),
        $operator,
        'Kendala unggah berkas pada hari terakhir',
        '2026-12-20 23:59:00'
    );

    $this->actingAs($fx['user'])
        ->post(route('user.submissions.outputs.store', $submission), [
            'output_type' => 'publication',
        ])
        ->assertRedirect();

    expect(ResearchOutput::count())->toBe(1);
});
