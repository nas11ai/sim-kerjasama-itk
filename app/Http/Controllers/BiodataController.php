<?php

namespace App\Http\Controllers;

use App\Actions\Identity\SaveBiodata;
use App\Http\Requests\Identity\BiodataRequest;
use App\Models\User;
use App\Queries\Identity\BiodataFormView;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BiodataController extends Controller
{
    public function showBiodataForm(Request $request)
    {
        $view = BiodataFormView::run($this->actor($request));

        if ($view === null) {
            return redirect()->route('user.dashboard')
                ->with('error', 'Form biodata tidak ditemukan atau tidak aktif.');
        }

        return Inertia::render('User/Biodata/IndexPage', [
            ...$view,
            'biodataStatus' => session('biodataStatus'),
        ]);
    }

    public function submitForm(BiodataRequest $request)
    {
        $input = $request->toInput();

        SaveBiodata::run($this->actor($request), $input);

        return redirect()->route('user.dashboard')->with(
            'success',
            $input->isSubmitted ? 'Biodata berhasil diserahkan dan menunggu persetujuan.' : null,
        );
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
