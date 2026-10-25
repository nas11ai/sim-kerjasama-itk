<?php

use App\Models\User;

it('meneruskan flash sesi sebagai flash native Inertia', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Tersimpan', 'error' => null])
        ->get(route('profile.edit'));

    expect($response->viewData('page')['flash'])->toBe(['success' => 'Tersimpan']);
});

it('meneruskan ketiga kunci flash sesi yang terisi', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withSession(['message' => 'Info', 'error' => 'Gagal', 'success' => 'Tersimpan'])
        ->get(route('profile.edit'));

    expect($response->viewData('page')['flash'])
        ->toBe(['message' => 'Info', 'error' => 'Gagal', 'success' => 'Tersimpan']);
});

it('tidak mengirim flash native saat sesi tidak membawa flash', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withSession(['success' => ''])
        ->get(route('profile.edit'));

    expect($response->viewData('page'))->not->toHaveKey('flash');
});

it('peringatan beban reviewer tetap prop, bukan flash native', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withSession(['workloadWarnings' => ['x']])
        ->get(route('profile.edit'));

    expect($response->viewData('page'))->not->toHaveKey('flash')
        ->and($response->viewData('page')['props']['flash']['workloadWarnings'])->toBe(['x']);
});
