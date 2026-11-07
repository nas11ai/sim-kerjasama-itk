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
use App\Data\Request\PhaseOverrideInput;
use App\Models\AuditEntry;
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
use App\Support\Lifecycle\DeletionNotPermitted;
use Database\Seeders\OutputTypeDefinitionSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Storage::fake('local'));

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

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('publication')))
        ->toThrow(ValidationException::class)
        ->and(ResearchOutput::count())->toBe(0);
});

it('[RO-OUTPUT-02] submission APPROVED bisa menerima luaran', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('publication');

    $output = SaveResearchOutput::run($s, $owner, outputInput('publication'));

    expect($output->form_submission_id)->toBe($s->id)
        ->and($output->created_by)->toBe($owner->id);
});

it('[RO-OUTPUT-03] tipe pks ditolak di luar CommunityService', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'Research');
    outputType('pks', ['submission_type' => 'CommunityService']);

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('pks')))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-04] tipe pks diterima pada CommunityService', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'CommunityService');
    outputType('pks', ['submission_type' => 'CommunityService']);

    $output = SaveResearchOutput::run($s, $owner, outputInput('pks'));

    expect($output->output_type)->toBe('pks');
});

/** Isian wajib PKS hasil seeder, tanpa end_date: kerja sama yang masih berjalan. */
function runningPks(): array
{
    return ['title' => 'Kerja sama pendampingan UMKM', 'involved_parties' => 'Dinas Koperasi Balikpapan', 'start_date' => '2026-02-01'];
}

it('[RO-OUTPUT-05] PKS yang masih berjalan boleh tanpa end_date', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'CommunityService');
    $this->seed(OutputTypeDefinitionSeeder::class);

    // end_date tidak wajib: kerja sama yang masih berjalan memang belum punya tanggal
    // berakhir, dan memaksanya membuat orang mengarang.
    $output = SaveResearchOutput::run($s, $owner, outputInput('pks', runningPks()));

    expect($output->metadata['end_date'] ?? null)->toBeNull();
});

it('[RO-OUTPUT-06] tipe ip tanpa berkas ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('ip', ['requires_files' => true]);

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('ip')))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-07] tipe ip dengan satu berkas tersimpan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('ip', ['requires_files' => true]);

    $output = SaveResearchOutput::run($s, $owner, outputInput('ip', files: 1));

    $file = $output->files()->sole();
    expect($file->original_name)->toBe('bukti-1.pdf')
        ->and($file->size)->toBeGreaterThan(0)
        ->and(Storage::disk('local')->exists($file->file_path))->toBeTrue()
        ->and($file->file_path)->toStartWith("research-outputs/{$s->id}/");
});

it('[RO-OUTPUT-17] tipe prototype tanpa berkas ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('prototype', ['requires_files' => true]);

    // BR-RO-04 menyebut DUA tipe. Implementasi yang menuliskan `ip` saja lolos
    // tanpa ada yang gagal — sampai ada prototipe kosong masuk laporan LPPM.
    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('prototype')))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-18] tipe prototype dengan satu berkas tersimpan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('prototype', ['requires_files' => true]);

    $output = SaveResearchOutput::run($s, $owner, outputInput('prototype', files: 1));

    expect($output->files()->count())->toBe(1);
});

it('[RO-OUTPUT-08] tipe di luar output_type_definitions ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('sertifikat-halal')))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-09] tipe baru yang terdaftar langsung bisa dipakai', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();

    // Menambah tipe adalah perubahan data, bukan perubahan kode.
    outputType('sertifikat-halal');

    $output = SaveResearchOutput::run($s, $owner, outputInput('sertifikat-halal'));

    expect($output->output_type)->toBe('sertifikat-halal');
});

/** Artikel lengkap menurut spesifikasi seeder. */
function article(array $override = []): array
{
    return [...[
        'title' => 'Optimasi Nutrisi Hidroponik Berbasis IoT',
        'journal_name' => 'Jurnal Teknologi Pertanian Indonesia',
        'journal_type' => 'national_accredited',
        'year' => '2026',
    ], ...$override];
}

it('[RO-OUTPUT-21] metadata wajib yang kosong ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    $this->seed(OutputTypeDefinitionSeeder::class);

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('article', article(['title' => '  ']))))
        ->toThrow(fn (ValidationException $e) => expect($e->errors()['metadata.title'])
            ->toBe(['Isian "Judul Artikel" wajib diisi untuk tipe Artikel Jurnal.']));
});

it('[RO-OUTPUT-22] metadata yang tidak wajib boleh kosong', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture(submissionTypeName: 'CommunityService');
    $this->seed(OutputTypeDefinitionSeeder::class);

    $output = SaveResearchOutput::run($s, $owner, outputInput('pks', [...runningPks(), 'end_date' => '']));

    expect($output->metadata['end_date'])->toBeNull()
        ->and($output->metadata['title'])->toBe('Kerja sama pendampingan UMKM');
});

it('kunci di luar spesifikasi dibuang dan angka disimpan sebagai bilangan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    $this->seed(OutputTypeDefinitionSeeder::class);

    $output = SaveResearchOutput::run($s, $owner, outputInput('article', article(['is_verified' => true])));

    expect($output->metadata)->not->toHaveKey('is_verified')
        ->and($output->metadata['year'])->toBe(2026)
        // jsonb menata ulang urutan kunci; yang diuji himpunannya.
        ->and(array_keys($output->metadata))->toEqualCanonicalizing(['title', 'journal_name', 'journal_type', 'year', 'doi', 'url']);
});

it('nilai yang tidak sesuai tipe field ditolak', function (array $override, string $key) {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    $this->seed(OutputTypeDefinitionSeeder::class);

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('article', article($override))))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey("metadata.{$key}"));

    expect(ResearchOutput::count())->toBe(0);
})->with([
    'tahun bukan angka' => [['year' => 'dua ribu'], 'year'],
    'tipe jurnal di luar pilihan' => [['journal_type' => 'predator'], 'journal_type'],
    'URL bukan http' => [['url' => 'javascript:alert(1)'], 'url'],
]);

it('[RO-OUTPUT-06] luaran ditolak setelah pengajuan ditarik', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('publication');

    $s->status->transitionTo(Withdrawn::class);

    expect(fn () => SaveResearchOutput::run($s->fresh(), $owner, outputInput('publication')))
        ->toThrow(ValidationException::class);
});

it('[RO-OUTPUT-10] luaran tetap ada sebagai data historis setelah penarikan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('publication');

    $output = SaveResearchOutput::run($s, $owner, outputInput('publication'));

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

    // Peran `researcher` memegang outputs.manage (W138): anggota tetap hanya membaca.
    $member = seededRole('researcher');
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
    $reviewer = seededRole('researcher');
    $reviewer->assignRole('reviewer_internal');

    expect($reviewer->can('manageResearchOutput', $s))->toBeFalse();
});

it('[RO-OUTPUT-20] Operator pemegang outputs.manage boleh menyunting luaran', function () {
    ['submission' => $s] = outputFixture();

    $operator = seededRole('operator');

    // Register #35 — sengaja berbeda dari anggaran: anggaran komitmen peneliti,
    // luaran laporan fakta yang memasok pelaporan LPPM.
    expect($operator->can('manageResearchOutput', $s))->toBeTrue();
});

it('[RO-OUTPUT-25] peneliti di luar tim tidak bisa menambah luaran meski memegang outputs.manage', function () {
    ['submission' => $s] = outputFixture();
    outputType('publication');

    $stranger = seededRole('researcher');

    $this->actingAs($stranger)
        ->post(route('research-outputs.store', $s), ['output_type' => 'publication'])
        ->assertForbidden();

    expect(ResearchOutput::count())->toBe(0);
});

it('Operator seeder memegang outputs.manage', function () {
    $this->seed(PermissionSeeder::class);

    expect(Role::findByName('operator', 'web')->hasPermissionTo('outputs.manage'))->toBeTrue();
});

// BR-RO-08 — sumbu **kapan**. Batasnya milik FormPhaseDetail "Upload Luaran",
// bukan mekanisme tersendiri (ADR 0014). Membaca setelah tenggat tidak butuh
// override; hanya menulis yang tertutup (BR-FE-15) — membedakannya memangkas
// jumlah perpanjangan yang harus diterbitkan Operator.

// Fixture W139: Batas Submit detail pengajuan sudah lewat di setiap test di bawah, karena
// begitulah keadaan setiap pengajuan yang sudah disetujui. Yang menggerbangi hanya Batas Akhir.
function approvedLongAgo(string $deadline = '2026-12-01 23:59:00'): array
{
    $fx = outputUploadFixture($deadline);
    $fx['detail']->submissionDate->update(['datetime' => '2026-06-01 23:59:00']);
    outputType('publication');

    return $fx;
}

it('[RO-OUTPUT-14] lewat batas Upload Luaran, penyuntingan ditolak', function () {
    $this->travelTo('2026-12-10 09:00:00');
    $fx = approvedLongAgo();

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'publication'])
        ->assertForbidden();

    expect(ResearchOutput::count())->toBe(0);
});

it('luaran bisa ditambahkan sebelum Batas Akhir meski Batas Submit pengajuan sudah lewat', function () {
    $this->travelTo('2026-11-20 09:00:00');
    $fx = approvedLongAgo();

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'publication'])
        ->assertRedirect();

    expect(ResearchOutput::count())->toBe(1);
});

it('[RO-OUTPUT-15] lewat batas, luaran lama tetap terbaca tanpa override', function () {
    $this->travelTo('2026-11-20 09:00:00');
    $fx = approvedLongAgo();
    $output = SaveResearchOutput::run($fx['submission'], $fx['user'], outputInput('publication'));

    $this->travelTo('2026-12-10 09:00:00');

    // Membaca tidak pernah bergerbang deadline — yang tertutup hanya menulis.
    $this->actingAs($fx['user'])
        ->get(route('user.submissions.show', $fx['submission']))
        ->assertOk();

    expect(ResearchOutput::find($output->id))->not->toBeNull();
});

it('[RO-OUTPUT-16] override yang berlaku membuka kembali penyuntingan luaran', function () {
    $this->travelTo('2026-12-10 09:00:00');
    $fx = approvedLongAgo();
    $operator = seededRole('operator');

    GrantPhaseOverride::run(
        $fx['submission'],
        $fx['outputDetail']->fresh(),
        $operator,
        new PhaseOverrideInput('Kendala unggah berkas pada hari terakhir', '2026-12-20 23:59:00'),
    );

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'publication'])
        ->assertRedirect();

    expect(ResearchOutput::count())->toBe(1);
});

it('[RO-OUTPUT-26] tanpa detail Upload Luaran, luaran tetap bisa ditambahkan setelah Batas Submit pengajuan lewat', function () {
    $this->travelTo('2026-12-10 09:00:00');
    $fx = approvedLongAgo();
    $fx['outputDetail']->delete();

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'publication'])
        ->assertRedirect();

    expect(ResearchOutput::count())->toBe(1);
});

it('[RO-OUTPUT-27] penutupan paksa periode tidak menutup Upload Luaran', function () {
    $this->travelTo('2026-11-20 09:00:00');
    $fx = approvedLongAgo();
    // Langsung di kolomnya: CloseSubmissionPeriod melewati periode yang Batas Submit-nya sudah lewat (W68).
    $fx['period']->update(['is_force_closed' => true]);

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), ['output_type' => 'publication'])
        ->assertRedirect();

    expect(ResearchOutput::count())->toBe(1);
});

// ---------- BR-RO-06: luaran tidak pernah dihapus ----------

it('[RO-OUTPUT-23] luaran tidak bisa dihapus peneliti', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('artikel');

    $output = SaveResearchOutput::run($s, $owner, outputInput('artikel'));

    // Dijaga di model, bukan di Policy: penghapusan lewat relasi, tinker, atau
    // seeder tidak melewati Policy mana pun.
    expect(fn () => $output->delete())->toThrow(DeletionNotPermitted::class)
        ->and(ResearchOutput::query()->whereKey($output->id)->exists())->toBeTrue();
});

it('[RO-OUTPUT-24] luaran yang salah dikoreksi lewat penyuntingan', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('artikel', ['fields' => [['key' => 'title', 'required' => true]]]);

    $output = SaveResearchOutput::run($s, $owner, outputInput('artikel', ['title' => 'Judul Keliru']));

    SaveResearchOutput::run($s, $owner, outputInput('artikel', ['title' => 'Judul Benar']), $output);

    expect($output->fresh()->metadata['title'])->toBe('Judul Benar')
        // Koreksi tidak boleh berwujud "hapus lalu buat ulang" — baris yang sama
        // harus bertahan, karena itulah yang menautkan luaran ke laporan lama.
        ->and(ResearchOutput::query()->where('form_submission_id', $s->id)->count())->toBe(1);
});

// ---------- W141: berkas bukti sungguhan ----------

it('path berkas dari klien tidak diterima', function () {
    $this->travelTo('2026-11-20 09:00:00');
    $fx = outputUploadFixture();
    outputType('ip', ['requires_files' => true]);

    $this->actingAs($fx['user'])
        ->post(route('research-outputs.store', $fx['submission']), [
            'output_type' => 'ip',
            'files' => [['file_path' => 'submissions/lain.pdf']],
        ])
        ->assertSessionHasErrors('files.0');

    expect(ResearchOutput::count())->toBe(0);
});

it('sunting HKI tanpa unggah ulang', function () {
    $this->travelTo('2026-11-20 09:00:00');
    $fx = outputUploadFixture();
    outputType('ip', ['requires_files' => true, 'fields' => [['key' => 'title', 'required' => true]]]);
    outputType('prototype', ['requires_files' => true]);
    $output = SaveResearchOutput::run($fx['submission'], $fx['user'], outputInput('ip', ['title' => 'Paten Lama'], files: 1));

    $this->actingAs($fx['user'])
        ->put(route('research-outputs.update', $output), [
            'output_type' => 'ip',
            'metadata' => ['title' => 'Paten Baru'],
        ])
        ->assertRedirect();

    // Berkas lama tetap dihitung untuk BR-RO-04, juga saat tipenya diganti.
    SaveResearchOutput::run($fx['submission'], $fx['user'], outputInput('prototype'), $output->fresh());

    expect($output->fresh()->output_type)->toBe('prototype')
        ->and($output->files()->count())->toBe(1);
});

it('berkas tidak tertinggal saat penyimpanan ditolak', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('ip', ['requires_files' => true, 'fields' => [['key' => 'title', 'required' => true]]]);

    expect(fn () => SaveResearchOutput::run($s, $owner, outputInput('ip', files: 1)))
        ->toThrow(ValidationException::class);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('menyimpan luaran mencatat jumlah berkas di audit', function () {
    ['submission' => $s, 'owner' => $owner] = outputFixture();
    outputType('ip', ['requires_files' => true]);

    $output = SaveResearchOutput::run($s, $owner, outputInput('ip', files: 2));

    expect(AuditEntry::query()->where('description', 'Luaran penelitian disimpan')->where('subject_id', $output->id)->sole()
        ->properties['new']['files_added'] ?? null)->toBe(2);
});
