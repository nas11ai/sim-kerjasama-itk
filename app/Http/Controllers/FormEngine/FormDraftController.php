<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Form\DiscardFormDraft;
use App\Actions\Form\NotifyFormChange;
use App\Actions\Form\PublishFormDraft;
use App\Actions\Form\SaveFormDraft;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\FormDraftRequest;
use App\Models\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** BR-FE-34 — draf field Form: simpan, buang, terbitkan ke field hidup. */
class FormDraftController extends Controller
{
    use ResolvesActor;

    public function update(FormDraftRequest $request, Form $form): RedirectResponse
    {
        SaveFormDraft::run($form, $request->toFields(), $this->actor($request));

        return back()->with('success', 'Draf disimpan.');
    }

    public function destroy(Request $request, Form $form): RedirectResponse
    {
        DiscardFormDraft::run($form, $this->actor($request));

        return back()->with('success', 'Draf dibuang.');
    }

    public function publish(Request $request, Form $form): RedirectResponse
    {
        PublishFormDraft::run($form, $this->actor($request));

        // BR-FE-13 — pemberitahuan ke peneliti ditawarkan di dialog Terbitkan, bukan dikirim diam-diam.
        $notify = $request->boolean('notify_affected');
        $affected = NotifyFormChange::run($form, $notify);

        return back()->with('success', $notify && $affected > 0
            ? "Formulir diterbitkan. {$affected} peneliti diberi tahu."
            : 'Formulir diterbitkan.');
    }
}
