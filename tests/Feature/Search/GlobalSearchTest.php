<?php

// K2 — pencarian global topbar (docs/requirement-analysis/ux/ia/00_navigation/topbar.md).
// BDD belum punya skenario pencarian global, jadi test ini tidak menyebut ID skenario.

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\UnderReview;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;

/** @return array{submissions: list<array<string, mixed>>, users: list<array<string, mixed>>, reviewers: list<array<string, mixed>>} */
function cariSebagai(User $viewer, string $q): array
{
    return test()->actingAs($viewer)->getJson(route('search', ['q' => $q]))->assertOk()->json();
}

function pemegang(string ...$permissions): User
{
    $user = verifiedResearcher();
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user->refresh();
}

/** @param  array<string, mixed>  $attributes */
function pengajuanBerjudul(string $title, User $lead, array $attributes = []): FormSubmission
{
    return FormSubmission::factory()->create([
        'form_id' => Form::factory()->create(['title' => $title])->id,
        'submitted_by' => $lead->id,
        ...$attributes,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function pengajuanTerkirim(string $title, User $lead, array $attributes = []): FormSubmission
{
    return pengajuanBerjudul($title, $lead, ['is_submitted' => true, 'status' => UnderReview::class, ...$attributes]);
}

function ikutTimAktif(User $user, FormSubmission $submission): void
{
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => ($user->userProfile ?? UserProfile::factory()->create(['user_id' => $user->id]))->id,
        'status' => 'active',
    ]);
}

it('tamu ditolak', function () {
    $this->getJson(route('search', ['q' => 'ab']))->assertUnauthorized();
});

it('istilah kurang dari 2 huruf ditolak', function () {
    $viewer = pemegang('submissions.view-own');

    $this->actingAs($viewer)->getJson(route('search', ['q' => 'a']))
        ->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->actingAs($viewer)->getJson(route('search', ['q' => str_repeat('a', 101)]))
        ->assertUnprocessable()->assertJsonValidationErrors('q');
});

it('[BR-SM-07] peneliti hanya menemukan pengajuan yang ia ketuai atau ikuti', function () {
    $sari = pemegang('submissions.view-own');
    $budi = pemegang('submissions.view-own');
    $rina = pemegang('submissions.view-own');
    $milikSari = pengajuanBerjudul('Penelitian Dasar Komposit', $sari);
    ikutTimAktif($budi, $milikSari);
    pengajuanBerjudul('Penelitian Dasar Komposit', $rina);

    $expected = [[
        'id' => $milikSari->id,
        'label' => 'Penelitian Dasar Komposit',
        'url' => route('user.submissions.show', $milikSari->id),
    ]];

    foreach ([$sari, $budi] as $viewer) {
        $result = cariSebagai($viewer, 'komposit');

        expect(array_map(fn (array $h) => Arr::only($h, ['id', 'label', 'url']), $result['submissions']))->toBe($expected)
            ->and($result['users'])->toBe([])
            ->and($result['reviewers'])->toBe([]);
    }
});

it('pencarian juga mencocokkan nama ketua', function () {
    $operator = pemegang('submissions.view-all');
    $rina = verifiedResearcher();
    $rina->update(['name' => 'Dr. Rina Wulandari']);
    $milikRina = pengajuanTerkirim('Robotika Lanjut', $rina);

    $ids = collect(cariSebagai($operator, 'rina')['submissions'])->pluck('id')->all();

    expect($ids)->toBe([$milikRina->id]);
});

it('view-all tidak melihat draft dan laporan monev', function () {
    $operator = pemegang('submissions.view-all');
    $lead = verifiedResearcher();
    $induk = pengajuanTerkirim('Kajian Mangrove', $lead);
    pengajuanBerjudul('Kajian Mangrove', $lead);
    pengajuanTerkirim('Kajian Mangrove', $lead, ['parent_submission_id' => $induk->id]);

    $hits = cariSebagai($operator, 'mangrove')['submissions'];

    expect(array_map(fn (array $h) => Arr::only($h, ['id', 'url']), $hits))
        ->toBe([['id' => $induk->id, 'url' => route('admin.submissions.show', $induk->id)]]);
});

it('tanpa view-own maupun view-all grup Pengajuan kosong', function () {
    $tanpaIzin = verifiedResearcher();
    pengajuanBerjudul('Kajian Mangrove', $tanpaIzin);

    expect(cariSebagai($tanpaIzin, 'mangrove')['submissions'])->toBe([]);
});

it('paling banyak 5 hasil, terbaru dulu', function () {
    $operator = pemegang('submissions.view-all');
    $lead = verifiedResearcher();
    $ids = [];
    foreach (range(1, 7) as $i) {
        $ids[] = pengajuanTerkirim('Riset Pesisir', $lead, ['updated_at' => now()->subDays(10 - $i)])->id;
    }

    $hits = cariSebagai($operator, 'pesisir')['submissions'];

    expect(collect($hits)->pluck('id')->all())->toBe(array_slice(array_reverse($ids), 0, 5));
});

it('% dan _ dicari sebagai teks biasa', function () {
    $operator = pemegang('submissions.view-all');
    $lead = verifiedResearcher();
    pengajuanTerkirim('Alpha', $lead);
    pengajuanTerkirim('Beta', $lead);

    expect(cariSebagai($operator, '%%')['submissions'])->toBe([])
        ->and(cariSebagai($operator, 'a_p')['submissions'])->toBe([]);
});

it('sublabel memuat status dan nama ketua', function () {
    $operator = pemegang('submissions.view-all');
    $rina = verifiedResearcher();
    $rina->update(['name' => 'Dr. Rina']);
    $milikRina = pengajuanTerkirim('Kajian Mangrove', $rina);

    $hit = cariSebagai($operator, 'mangrove')['submissions'][0];

    expect($hit['sublabel'])->toBe($milikRina->refresh()->status->label().' · Dr. Rina');
});
