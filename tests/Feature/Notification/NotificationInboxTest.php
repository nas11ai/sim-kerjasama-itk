<?php

// tests/Feature/Notification/NotificationInboxTest.php
//
// SHARED-02-05 — kontrak layar notifikasi dan chip filternya (W40).

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\FormChangedNotification;
use App\Notifications\RevisionResolvedNotification;
use App\Queries\Notification\NotificationInbox;
use App\Support\Notification\Deliver;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function inboxUserWithTwoKinds(): User
{
    $budi = User::factory()->create();
    Deliver::to($budi, new FormChangedNotification(Form::factory()->create()));
    Deliver::to($budi, new RevisionResolvedNotification(FormSubmission::factory()->create()));

    return $budi;
}

it('kontrak layar notifikasi', function () {
    $budi = inboxUserWithTwoKinds();

    $this->actingAs($budi)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notification/IndexPage')
            ->has('notifications.data', 2)
            ->has('notifications.data.0', fn (Assert $row) => $row
                ->whereType('id', 'string')
                ->whereType('title', 'string')
                ->whereType('message', 'string')
                ->whereType('category', 'string')
                ->whereType('categoryLabel', 'string')
                ->whereType('createdAt', 'string')
                ->where('isRead', false)
            )
            ->where('filter', 'all')
            ->where('unreadCount', 2)
        );
});

it('filter tak dikenal dianggap semua', function () {
    $budi = inboxUserWithTwoKinds();

    $this->actingAs($budi)
        ->get(route('notifications.index', ['filter' => 'apa-saja']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filter', 'all')->has('notifications.data', 2));
});

it('halaman di luar jangkauan memberi daftar kosong, bukan galat', function () {
    $budi = inboxUserWithTwoKinds();

    $this->actingAs($budi)
        ->get(route('notifications.index', ['page' => 99]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0));
});

it('filter review hanya kategori Review', function () {
    $rows = NotificationInbox::run(inboxUserWithTwoKinds(), 'review')['notifications']->items();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->category)->toBe('review')
        ->and($rows[0]->categoryLabel)->toBe('Penilaian');
});

it('filter unread hanya yang belum dibaca', function () {
    $budi = inboxUserWithTwoKinds();
    $budi->notifications()->latest()->first()?->markAsRead();

    $rows = NotificationInbox::run($budi, 'unread')['notifications']->items();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->isRead)->toBeFalse();
});

it('baris dengan kelas yang sudah tidak ada tetap tampil', function () {
    // Baris lama bisa memuat kelas yang sudah dihapus; layar tidak boleh 500 karenanya.
    $budi = User::factory()->create();
    $budi->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\SudahDihapusNotification',
        'data' => ['title' => 'Lama', 'message' => 'Isi lama'],
    ]);

    $rows = NotificationInbox::run($budi, 'all')['notifications']->items();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->title)->toBe('Lama')
        ->and($rows[0]->categoryLabel)->toBe('Lainnya');
});

it('notifikasi orang lain tidak ikut', function () {
    inboxUserWithTwoKinds();

    expect(NotificationInbox::run(User::factory()->create(), 'all')['notifications']->items())->toBe([]);
});
