<?php

// ADMIN-08-05 — kontrak daftar Form, hitungan, filter, dan wewenang `schemes.manage` (W88).

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormType;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function formListActor(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return list<int> */
function listedFormIds(array $query): array
{
    $ids = [];
    test()->actingAs(formListActor())
        ->get(route('admin.forms.index', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['forms']['data'], 'id');
        });
    sort($ids);

    return $ids;
}

it('kontrak layar daftar form', function () {
    $form = Form::factory()->create();

    $this->actingAs(formListActor())
        ->get(route('admin.forms.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('FormEngine/FormIndexPage')
            ->has('forms.data', 1)
            ->has('forms.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $form->id)
                ->where('title', $form->title)
                ->where('typeName', $form->formType?->name)
                ->where('fieldCount', 0)
                ->where('activeSubmissionCount', 0)
                ->where('submissionCount', 0)
                ->where('isActive', true)
                ->where('isPublished', true))
            ->where('filters', ['search' => '', 'type' => '', 'status' => ''])
            ->has('typeOptions', 1)
            ->where('statusOptions', [
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ]));
});

it('wewenang: admin 200, operator 403, tamu ke login', function () {
    $this->actingAs(formListActor())->get(route('admin.forms.index'))->assertOk();
    $this->actingAs(formListActor('operator'))->get(route('admin.forms.index'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.forms.index'))->assertRedirect(route('login'));
});

it('pengajuan aktif tidak menghitung yang ditolak atau ditarik, penghalang hapus menghitung semua', function () {
    $form = Form::factory()->create();
    foreach ([Draft::class, Approved::class, Rejected::class, Withdrawn::class] as $status) {
        FormSubmission::factory()->create(['form_id' => $form->id, 'status' => $status]);
    }

    $this->actingAs(formListActor())
        ->get(route('admin.forms.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('forms.data.0.activeSubmissionCount', 2)
            ->where('forms.data.0.submissionCount', 4));
});

it('field aktif tidak menghitung field terarsip', function () {
    $form = Form::factory()->create();
    $type = FieldType::create(['name' => 'Teks '.fake()->unique()->numerify('####')]);
    foreach (['A', 'B', 'C'] as $i => $label) {
        FormField::create([
            'form_id' => $form->id, 'field_type_id' => $type->id,
            'label' => $label, 'is_required' => false, 'order' => $i,
        ]);
    }
    $form->formFields()->where('label', 'C')->first()?->delete();

    $this->actingAs(formListActor())
        ->get(route('admin.forms.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('forms.data.0.fieldCount', 2));
});

it('filter tipe, status, dan pencarian tidak peka huruf', function () {
    $type = FormType::factory()->create();
    $a = Form::factory()->create(['title' => 'Form Proposal LPPM', 'form_type_id' => $type->id]);
    $b = Form::factory()->create(['title' => 'Laporan Monev', 'is_active' => false]);

    expect(listedFormIds([]))->toBe([$a->id, $b->id])
        ->and(listedFormIds(['search' => 'proposal lppm']))->toBe([$a->id])
        ->and(listedFormIds(['search' => '%']))->toBe([])
        ->and(listedFormIds(['type' => (string) $type->id]))->toBe([$a->id])
        ->and(listedFormIds(['type' => 'all']))->toBe([$a->id, $b->id])
        ->and(listedFormIds(['status' => 'inactive']))->toBe([$b->id])
        ->and(listedFormIds(['status' => 'active']))->toBe([$a->id])
        ->and(listedFormIds(['status' => 'nope']))->toBe([$a->id, $b->id]);
});

it('filter tipe di luar rentang integer diabaikan, bukan 500', function () {
    $form = Form::factory()->create();

    expect(listedFormIds(['type' => '99999999999999999999']))->toBe([$form->id]);
});
