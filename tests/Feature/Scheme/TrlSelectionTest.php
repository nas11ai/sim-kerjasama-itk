<?php

// tests/Feature/Scheme/TrlSelectionTest.php
//
// BR-SCH-05, BR-SCH-09 — pilihan `trl_selector` di langkah 3 pindah ke kolom `trl_id`,
// dan hanya TRL yang diizinkan skema terpilih yang diterima.

use App\Actions\FormSubmission\StartSubmission;
use App\Models\AuditEntry;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\TechnologyReadinessLevel;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/** @return array{owner: User, submission: FormSubmission, scheme: FormField, trl: FormField, a: Scheme, b: Scheme, levels: list<TechnologyReadinessLevel>} */
function trlFixture(): array
{
    $fx = wizardFixture();
    $field = fn (string $type, int $order) => FormField::create([
        'form_id' => $fx['form']->id,
        'field_type_id' => FieldType::firstOrCreate(['name' => $type])->id,
        'label' => $type,
        'is_required' => false,
        'order' => $order,
    ]);
    $levels = array_map(fn (int $level) => TechnologyReadinessLevel::create(['level' => $level, 'name' => "Tingkat {$level}"]), [1, 2, 3]);
    $a = Scheme::factory()->create();
    $a->allowedTrls()->attach([$levels[0]->id, $levels[1]->id]);
    $b = Scheme::factory()->create();
    $b->allowedTrls()->attach([$levels[2]->id]);

    return [
        'owner' => $fx['user'],
        'submission' => StartSubmission::run($fx['form']->id, $fx['period']->id, $fx['detail'], $fx['user']),
        'scheme' => $field('scheme_selector', 1),
        'trl' => $field('trl_selector', 2),
        'a' => $a,
        'b' => $b,
        'levels' => $levels,
    ];
}

function saveSchemeStep(array $fx, Scheme $scheme, ?TechnologyReadinessLevel $trl): TestResponse
{
    return test()->actingAs($fx['owner'])->post(route('user.submissions.wizard.save', [$fx['submission'], 3]), [
        'goto' => 4,
        'last_updated_at' => $fx['submission']->fresh()->updated_at->toISOString(),
        'responses' => [
            ['form_field_id' => $fx['scheme']->id, 'value' => (string) $scheme->id],
            ['form_field_id' => $fx['trl']->id, 'value' => $trl === null ? '' : (string) $trl->id],
        ],
    ]);
}

it('[SM-WIZARD-05] TRL di langkah 3 menulis trl_id', function () {
    $fx = trlFixture();

    saveSchemeStep($fx, $fx['a'], $fx['levels'][1])->assertSessionHasNoErrors();

    expect($fx['submission']->fresh())
        ->scheme_id->toBe($fx['a']->id)
        ->trl_id->toBe($fx['levels'][1]->id);
});

it('TRL di luar izin skema ditolak', function () {
    $fx = trlFixture();
    saveSchemeStep($fx, $fx['a'], $fx['levels'][0])->assertSessionHasNoErrors();

    // Skema diganti, TRL lama yang tidak diizinkan skema baru ikut terkirim.
    saveSchemeStep($fx, $fx['b'], $fx['levels'][0])
        ->assertSessionHasErrors(['field_'.$fx['trl']->id => 'TKT ini tidak diizinkan skema terpilih.']);

    expect($fx['submission']->fresh())
        ->scheme_id->toBe($fx['a']->id)
        ->trl_id->toBe($fx['levels'][0]->id);
});

it('mengosongkan TRL mengosongkan trl_id', function () {
    $fx = trlFixture();
    saveSchemeStep($fx, $fx['a'], $fx['levels'][0]);
    expect($fx['submission']->fresh()->trl_id)->toBe($fx['levels'][0]->id);

    saveSchemeStep($fx, $fx['a'], null)->assertSessionHasNoErrors();

    expect($fx['submission']->fresh()->trl_id)->toBeNull();
});

it('perubahan TRL tercatat di audit', function () {
    $fx = trlFixture();

    saveSchemeStep($fx, $fx['a'], $fx['levels'][1]);
    saveSchemeStep($fx, $fx['a'], $fx['levels'][1]);

    $entries = AuditEntry::where('subject_id', $fx['submission']->id)->where('description', 'TKT pengajuan dipilih')->get();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->properties['new'])->toBe(['trl_id' => $fx['levels'][1]->id]);
});
