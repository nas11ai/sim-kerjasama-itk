<?php

// K2 — pencarian global topbar (docs/requirement-analysis/ux/ia/00_navigation/topbar.md).
// BDD belum punya skenario pencarian global, jadi test ini tidak menyebut ID skenario.

use App\Enums\ReviewerStanding;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\User;
use App\Models\UserProfile;
use App\Queries\Search\GlobalSearch;
use App\States\Submission\UnderReview;
use App\Support\Identity\ReviewerTypeCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

/** @param  array<string, mixed>  $attributes */
function userBernama(string $name, ?string $nidn = null, array $attributes = []): User
{
    $user = User::factory()->create(['name' => $name, ...$attributes]);
    if ($nidn !== null) {
        UserProfile::factory()->create(['user_id' => $user->id, 'nidn' => $nidn]);
    }

    return $user;
}

function reviewerBernama(string $name, string $type = 'internal'): Reviewer
{
    return Reviewer::factory()->create(['user_id' => userBernama($name)->id, 'reviewer_type' => $type]);
}

function penontonDengan(string ...$permissions): User
{
    $viewer = sessionViewer(array_shift($permissions));
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $viewer->givePermissionTo($permission);
    }

    return $viewer;
}

it('[W47] pemegang users.verify menemukan user lewat nama atau NIDN', function () {
    $viewer = sessionViewer('users.verify');
    $budi = userBernama('Dr. Budi Santoso', '0012345678');

    $lewatNama = cariSebagai($viewer, 'budi')['users'];
    $lewatNidn = cariSebagai($viewer, '1234567')['users'];

    expect($lewatNama)->toHaveCount(1)
        ->and($lewatNama[0]['id'])->toBe($budi->id)
        ->and($lewatNama[0]['label'])->toBe('Dr. Budi Santoso')
        ->and($lewatNama[0]['url'])->toBe(route('admin.users.show', $budi->id))
        ->and(array_column($lewatNidn, 'id'))->toBe([$budi->id]);
});

it('sublabel user: NIDN atau email, ditambah Nonaktif bila akunnya nonaktif', function () {
    $viewer = sessionViewer('users.manage');
    userBernama('Kirana Satu', '0012345678');
    userBernama('Kirana Dua', null, ['email' => 'kirana.dua@itk.ac.id']);
    userBernama('Kirana Tiga', '0098765432', ['is_active' => false]);

    $sublabels = array_column(cariSebagai($viewer, 'kirana')['users'], 'sublabel', 'label');

    expect($sublabels)->toBe([
        'Kirana Dua' => 'kirana.dua@itk.ac.id',
        'Kirana Satu' => 'NIDN 0012345678',
        'Kirana Tiga' => 'NIDN 0098765432 · Nonaktif',
    ]);
});

it('peneliti tidak mendapat grup User maupun Reviewer', function () {
    $peneliti = pemegang('submissions.view-own');
    userBernama('Budi Santoso', '0012345678');
    reviewerBernama('Budi Reviewer');

    $this->actingAs($peneliti)->getJson(route('search', ['q' => 'budi']))
        ->assertOk()
        ->assertJsonPath('users', [])
        ->assertJsonPath('reviewers', []);
});

it('pemegang reviewers.assign menemukan reviewer lewat nama', function () {
    $viewer = sessionViewer('reviewers.assign');
    $reviewer = reviewerBernama('Prof. Wulan Sari', 'external');

    $hits = cariSebagai($viewer, 'wulan')['reviewers'];

    expect($hits)->toBe([[
        'id' => $reviewer->id,
        'label' => 'Prof. Wulan Sari',
        'sublabel' => ReviewerTypeCatalog::LABELS['external'].' · '.ReviewerStanding::of($reviewer->refresh())->label(),
        'url' => route('admin.reviewers.index', ['search' => 'Prof. Wulan Sari']),
    ]]);
});

it('tanpa reviewers.assign grup Reviewer kosong', function () {
    $viewer = sessionViewer('users.manage');
    reviewerBernama('Prof. Wulan Sari');

    $result = cariSebagai($viewer, 'wulan');

    expect($result['reviewers'])->toBe([])
        ->and($result['users'])->toHaveCount(1);
});

it('grup User paling banyak 5 hasil dan % dicari sebagai teks biasa', function () {
    $viewer = sessionViewer('users.verify');
    foreach (range(1, 7) as $i) {
        userBernama("Pengguna Uji {$i}");
    }

    expect(cariSebagai($viewer, 'pengguna')['users'])->toHaveCount(GlobalSearch::LIMIT)
        ->and(cariSebagai($viewer, '%%')['users'])->toBe([]);
});

it('grup Reviewer paling banyak 5 hasil dan % dicari sebagai teks biasa', function () {
    $viewer = sessionViewer('reviewers.assign');
    foreach (range(1, 7) as $i) {
        reviewerBernama("Penilai Uji {$i}");
    }

    expect(cariSebagai($viewer, 'penilai')['reviewers'])->toHaveCount(GlobalSearch::LIMIT)
        ->and(cariSebagai($viewer, '%%')['reviewers'])->toBe([]);
});

it('nama yang diawali istilah tampil lebih dulu', function () {
    $viewer = penontonDengan('users.verify', 'reviewers.assign');
    reviewerBernama('Ahmad Budiman');
    reviewerBernama('Budi Santoso');

    $result = cariSebagai($viewer, 'budi');

    expect(array_column($result['users'], 'label'))->toBe(['Budi Santoso', 'Ahmad Budiman'])
        ->and(array_column($result['reviewers'], 'label'))->toBe(['Budi Santoso', 'Ahmad Budiman']);
});

it('jumlah query tidak bertambah dengan jumlah hasil', function () {
    $viewer = penontonDengan('users.verify', 'reviewers.assign', 'submissions.view-all');
    $hitung = function (string $q) use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        cariSebagai($viewer, $q);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $hitung('pemanasan');

    $tunggal = reviewerBernama('Tunggal Satu');
    UserProfile::factory()->create(['user_id' => $tunggal->user_id]);
    pengajuanTerkirim('Tunggal Kajian', $tunggal->user()->firstOrFail());
    $satuHit = $hitung('tunggal');

    foreach (range(1, 5) as $i) {
        $reviewer = reviewerBernama("Ganda {$i}");
        UserProfile::factory()->create(['user_id' => $reviewer->user_id]);
        pengajuanTerkirim("Ganda Kajian {$i}", $reviewer->user()->firstOrFail());
    }
    $limaHit = $hitung('ganda');

    expect($limaHit)->toBeLessThanOrEqual($satuHit);
});
