<?php

namespace App\Http\Controllers\FormEngine;

use App\Http\Controllers\Controller;
use App\Queries\Form\FormDirectory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Form Builder (ADMIN-08). Route dijaga `can:schemes.manage` (W88).
 */
class FormController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'type' => $request->string('type')->value(),
            'status' => $request->string('status')->value(),
        ];

        return Inertia::render('FormEngine/FormIndexPage', [
            'filters' => $filters,
            ...FormDirectory::run($filters),
        ]);
    }
}
