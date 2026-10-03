<?php

// tests/Unit/Architecture/RouteActionsExistTest.php
//
// Route ke method controller yang tidak ada lolos route:list, lolos test,
// dan baru menjadi 500 di tangan pengguna. Test ini menolaknya di sini.

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('setiap route controller menunjuk method yang ada', function () {
    $missing = [];

    /** @var LaravelRoute $route */
    foreach (Route::getRoutes() as $route) {
        $uses = $route->getAction('uses');

        if (!is_string($uses)) {
            continue;
        }

        [$class, $method] = array_pad(explode('@', $uses, 2), 2, '__invoke');

        if (!class_exists($class) || !method_exists($class, $method)) {
            $missing[] = ($route->getName() ?? $route->uri()).' -> '.$class.'@'.$method;
        }
    }

    expect($missing)->toBe([], "Route menunjuk method yang tidak ada:\n".implode("\n", $missing));
});
