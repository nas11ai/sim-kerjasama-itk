<?php

// tests/Feature/Dashboard/ResearcherDashboardCardsTest.php
//
// SHARED-02-01 — kartu "Pengajuan Saya". Angkanya turunan predikat yang sudah
// dijaga skenario M1–M2: Active Submission (status bukan REJECTED/WITHDRAWN)
// dan pengajuan terlihat (BR-SM-07). Tidak ada definisi baru di sini.

use App\Data\View\Dashboard\KpiCardData;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\FormChangedNotification;
use App\Queries\Dashboard\ResearcherDashboardCards;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use App\Support\Notification\Deliver;

/** @return array<string, KpiCardData> */
function rdCards(User $user): array
{
    $section = ResearcherDashboardCards::run($user);

    return collect($section->cards)->keyBy('key')->all();
}

function rdOwned(User $owner, string $status, array $extra = []): FormSubmission
{
    return FormSubmission::factory()->create(['submitted_by' => $owner->id, 'status' => $status, ...$extra]);
}

it('section "Pengajuan Saya" berisi empat kartu frame', function () {
    $section = ResearcherDashboardCards::run(User::factory()->create());

    expect($section->key)->toBe('researcher')
        ->and($section->title)->toBe('Pengajuan Saya')
        ->and(array_map(fn (KpiCardData $c) => $c->label, $section->cards))
        ->toBe(['Pengajuan Aktif', 'Dalam Revisi', 'Disetujui', 'Notifikasi']);
});

it('Submission Aktif memakai predikat Active Submission: DRAFT dan APPROVED ikut, REJECTED/WITHDRAWN tidak', function () {
    $budi = User::factory()->create();
    rdOwned($budi, Draft::class);
    rdOwned($budi, UnderReview::class);
    rdOwned($budi, Approved::class);
    rdOwned($budi, Rejected::class);
    rdOwned($budi, Withdrawn::class);

    $cards = rdCards($budi);

    expect($cards['active']->value)->toBe('3')
        ->and($cards['active']->meta)->toBe('1 dalam penilaian')
        ->and($cards['approved']->value)->toBe('1');
});

it('anggota tim aktif ikut terhitung (BR-SM-07)', function () {
    $sari = User::factory()->create();
    $submission = FormSubmission::factory()->create(['status' => NeedsRevision::class]);
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $sari->id])->id,
        'status' => 'active',
    ]);

    $cards = rdCards($sari);

    expect($cards['active']->value)->toBe('1')
        ->and($cards['revision']->value)->toBe('1')
        ->and($cards['revision']->meta)->toBe('Butuh respon');
});

it('laporan monev (anak) tidak terhitung sebagai pengajuan', function () {
    $budi = User::factory()->create();
    $parent = rdOwned($budi, Approved::class);
    rdOwned($budi, Draft::class, ['parent_submission_id' => $parent->id]);

    expect(rdCards($budi)['active']->value)->toBe('1');
});

it('pengajuan orang lain tidak terhitung', function () {
    rdOwned(User::factory()->create(), UnderReview::class);

    $cards = rdCards(User::factory()->create());

    expect($cards['active']->value)->toBe('0')
        ->and($cards['active']->meta)->toBe('0 dalam penilaian');
});

it('kartu Notifikasi = belum dibaca dan menaut ke daftar notifikasi', function () {
    $budi = User::factory()->create();
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));

    $card = rdCards($budi)['notifications'];

    expect($card->value)->toBe('1')
        ->and($card->meta)->toBe('Belum dibaca')
        ->and($card->href)->toBe(route('notifications.index'));
});
