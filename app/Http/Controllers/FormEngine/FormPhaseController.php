<?php

namespace App\Http\Controllers\FormEngine;

use App\Data\View\FormEngine\FormHeaderData;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Queries\Form\FormPhaseCards;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab Phases Form Builder (ADMIN-08-08). Route dijaga `can:schemes.manage` (W88).
 */
class FormPhaseController extends Controller
{
    public function index(Request $request, Form $form): Response
    {
        return Inertia::render('FormEngine/FormEditPage', [
            'tab' => 'phases',
            'form' => FormHeaderData::fromModel($form),
            'phases' => FormPhaseCards::run($form),
            'can' => ['linkPeriod' => $request->user()?->can('periods.manage') === true],
        ]);
    }
}
