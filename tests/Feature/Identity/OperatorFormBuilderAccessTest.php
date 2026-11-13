<?php

// Keputusan pemilik 2026-11-13 — Operator mengelola Pembuat Formulir, Formulir Evaluasi, Fase, dan Skema
// lewat gerbang yang sama dengan admin (`schemes.manage`, W88).

use App\Models\Form;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('memberi peran operator schemes.manage', function () {
    expect(Role::findByName('operator', PermissionSeeder::GUARD)->hasPermissionTo('schemes.manage'))->toBeTrue();
});

it('membuka Pembuat Formulir, Formulir Evaluasi, Fase, Skema, dan Konfigurasi Umum untuk operator', function () {
    $form = Form::factory()->create();
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator);
    $this->get(route('admin.forms.index'))->assertOk();
    $this->get(route('admin.forms.edit', $form))->assertOk();
    $this->get(route('admin.forms.access.index', $form))->assertOk();
    $this->get(route('admin.evaluation-forms.index'))->assertOk();
    $this->get(route('admin.phases.index'))->assertOk();
    $this->get(route('admin.schemes.index'))->assertOk();
    $this->get(route('admin.reference.index'))->assertOk();
});
