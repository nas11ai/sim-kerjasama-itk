<?php

// tests/Feature/ModelSchemaDriftTest.php
//
// M0 review berulang kali menemukan bug berbentuk sama: model atau
// controller yang membaca/menulis kolom atau tabel yang tidak pernah ada
// di migration manapun (AnnouncementUser::$table = 'announcement_user'
// padahal tabelnya `announcement_users`, FormFieldOption::$order yang
// tidak pernah dimigrasikan, dst — lihat
// .superpowers/sdd/m0-bugfix/report.md). Guard ini menjalankan setiap
// model Eloquent konkret di app/Models/ terhadap skema database
// sungguhan: tabelnya harus ada, dan setiap entri $fillable maupun
// $casts harus jadi kolom sungguhan di tabel itu. Test ini mekanis, bukan
// unit test satu model — tujuannya supaya kelas bug ini tidak bisa lolos
// review lagi tanpa ketahuan.

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Semua kelas model Eloquent konkret (bukan abstract, memang turunan
 * Illuminate\Database\Eloquent\Model) di app/Models/.
 *
 * @return list<class-string<Model>>
 */
function concreteModelClasses(): array
{
    $files = glob(app_path('Models').'/*.php') ?: [];

    $classes = [];

    foreach ($files as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (!class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || !$reflection->isSubclassOf(Model::class)) {
            continue;
        }

        /** @var class-string<Model> $class */
        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}

it('keeps every Eloquent model fillable/cast key in sync with the real database schema', function () {
    $drift = [];

    foreach (concreteModelClasses() as $class) {
        $model = new $class;
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            $drift[] = "{$class}: table `{$table}` does not exist.";

            continue;
        }

        foreach ($model->getFillable() as $column) {
            if (!Schema::hasColumn($table, $column)) {
                $drift[] = "{$class}: \$fillable entry `{$column}` is not a column of table `{$table}`.";
            }
        }

        foreach (array_keys($model->getCasts()) as $column) {
            if (!Schema::hasColumn($table, $column)) {
                $drift[] = "{$class}: \$casts key `{$column}` is not a column of table `{$table}`.";
            }
        }
    }

    if ($drift !== []) {
        test()->fail("Schema drift ditemukan pada model berikut:\n".implode("\n", $drift));
    }

    expect($drift)->toBe([]);
});
