<?php

// tests/Feature/Budget/BudgetMutationTest.php
//
// BR-BUD-10 — menyunting dan menghapus line item.
//
// Sebelum aturan ini, anggaran hanya bisa ditambah. Akibatnya BR-BUD-01 jadi
// jalan buntu: peneliti yang terlanjur melewati batas skema tidak punya cara
// menurunkan totalnya, dan pengajuannya permanen tidak bisa disubmit.

use App\Actions\Budget\DeleteBudgetLineItem;
use App\Actions\Budget\SaveBudgetLineItem;
use App\Actions\Budget\UpdateBudgetLineItem;
use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use App\Support\Audit\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

/** @return array{submission: FormSubmission, owner: User, item: BudgetLineItem} */
function budgetMutationFixture(string $state = Draft::class, ?int $maxBudget = null): array
{
    $owner = User::factory()->create();

    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => $maxBudget === null
            ? null
            : Scheme::factory()->create(['max_budget' => $maxBudget])->id,
    ]);

    $item = BudgetLineItem::factory()->for($submission)->create([
        'item_name' => 'Kertas A4',
        'volume' => 2,
        'unit' => 'rim',
        'unit_price' => 50_000,
        'total' => 100_000,
    ]);

    return ['submission' => $submission, 'owner' => $owner, 'item' => $item];
}

it('[BUD-ITEM-05] ketua pengaju menyunting line item', function () {
    ['submission' => $s, 'owner' => $owner, 'item' => $item] = budgetMutationFixture();

    UpdateBudgetLineItem::run($s, $item, $owner, ['volume' => 5]);

    expect($item->fresh()->total)->toBe(250_000)
        ->and((int) $s->budgetLineItems()->sum('total'))->toBe(250_000);
});

it('[BUD-ITEM-06] penyuntingan lewat Action tercatat nilai sebelum dan sesudah', function () {
    ['submission' => $s, 'owner' => $owner, 'item' => $item] = budgetMutationFixture(NeedsRevision::class);

    UpdateBudgetLineItem::run($s, $item, $owner, ['volume' => 5]);

    $entri = Activity::inLog(AuditTrail::LOG_NAME)->latest('id')->first();

    expect($entri)->not->toBeNull()
        ->and($entri->properties['old']['volume'])->toBe(2)
        ->and($entri->properties['new']['volume'])->toBe(5)
        // Pelakunya harus terbaca: jejak anggaran tanpa nama tidak bisa
        // dipertanggungjawabkan saat dispute.
        ->and($entri->causer_id)->toBe($owner->id);
});

it('[BUD-ITEM-07] ketua pengaju menghapus line item', function () {
    ['submission' => $s, 'owner' => $owner, 'item' => $item] = budgetMutationFixture();

    DeleteBudgetLineItem::run($s, $item, $owner);

    expect((int) $s->budgetLineItems()->sum('total'))->toBe(0)
        // Soft delete — barisnya berhenti dihitung tapi tidak hilang, karena
        // angka yang pernah diajukan adalah bahan dispute (BR-BUD-08).
        ->and(BudgetLineItem::withTrashed()->whereKey($item->id)->exists())->toBeTrue();
});

it('[BUD-ITEM-08] menghapus item menurunkan total kembali ke bawah batas skema', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => Scheme::factory()->create(['max_budget' => 10_000_000])->id,
    ]);

    // Dua baris ini lahir sebelum batas skema dipasang — persis keadaan yang
    // membuat BR-BUD-01 jadi jalan buntu sebelum BR-BUD-10 ada.
    BudgetLineItem::factory()->for($submission)->create(['volume' => 1, 'unit_price' => 9_000_000, 'total' => 9_000_000]);
    $berlebih = BudgetLineItem::factory()->for($submission)->create(['volume' => 1, 'unit_price' => 3_000_000, 'total' => 3_000_000]);

    expect((int) $submission->budgetLineItems()->sum('total'))->toBe(12_000_000);

    DeleteBudgetLineItem::run($submission, $berlebih, $owner);

    expect((int) $submission->fresh()->budgetLineItems()->sum('total'))->toBe(9_000_000);

    // Dan ruang yang terbuka benar-benar bisa dipakai lagi: sebelumnya setiap
    // penambahan ditolak berapa pun nilainya.
    SaveBudgetLineItem::run($submission->fresh(), $owner, [
        'budget_component_id' => BudgetComponent::factory()->create(['is_active' => true])->id,
        'item_name' => 'Cetak laporan',
        'volume' => 1,
        'unit' => 'paket',
        'unit_price' => 900_000,
    ]);

    expect((int) $submission->fresh()->budgetLineItems()->sum('total'))->toBe(9_900_000);
});

it('[BUD-ITEM-09] line item terkunci pada status terminal', function (string $state) {
    ['submission' => $s, 'owner' => $owner, 'item' => $item] = budgetMutationFixture($state);

    expect(fn () => UpdateBudgetLineItem::run($s, $item, $owner, ['volume' => 5]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => DeleteBudgetLineItem::run($s, $item, $owner))
        ->toThrow(AuthorizationException::class)
        ->and($item->fresh()->volume)->toBe(2);
})->with([Approved::class, Rejected::class, Withdrawn::class]);

it('[BUD-ITEM-10] anggota biasa tidak bisa menyunting atau menghapus line item', function () {
    ['submission' => $s, 'item' => $item] = budgetMutationFixture();

    $sari = User::factory()->create();
    enrolResearchMember($s, $sari, 'member');

    expect(fn () => UpdateBudgetLineItem::run($s, $item, $sari->fresh(), ['volume' => 5]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => DeleteBudgetLineItem::run($s, $item, $sari->fresh()))
        ->toThrow(AuthorizationException::class);
});
