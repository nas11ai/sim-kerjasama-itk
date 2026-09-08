<?php

namespace App\Http\Controllers\Submission;

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Support\PhaseAccessGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BR-RO-01 s/d BR-RO-09 — luaran terealisasi.
 *
 * Tiga sumbu harus lolos bersamaan (ADR 0014): **siapa** lewat Policy,
 * **kapan** lewat batas FormPhaseDetail, dan **keadaan** lewat status induk.
 * Ketiganya diperiksa di sini karena masing-masing dijaga lapis yang berbeda,
 * dan melewatkan salah satunya tidak akan gagal mencolok.
 */
class ResearchOutputController extends Controller
{
    public function store(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = $this->authorizeManage($submission);

        $validated = $this->validated($request);

        SaveResearchOutput::run($submission, $user, $validated);

        return back()->with('success', 'Luaran tersimpan.');
    }

    public function update(
        Request $request,
        FormSubmission $submission,
        ResearchOutput $output
    ): RedirectResponse {
        $user = $this->authorizeManage($submission);

        abort_unless($output->form_submission_id === $submission->id, 404);

        SaveResearchOutput::run($submission, $user, $this->validated($request), $output);

        return back()->with('success', 'Luaran diperbarui.');
    }

    /**
     * @return array{output_type: string, metadata?: array<string, mixed>, files?: list<array{file_path: string, file_url?: string|null}>}
     */
    private function validated(Request $request): array
    {
        /** @var array{output_type: string, metadata?: array<string, mixed>, files?: list<array{file_path: string, file_url?: string|null}>} $data */
        $data = $request->validate([
            'output_type' => 'required|string|max:64',
            'metadata' => 'nullable|array',
            'files' => 'nullable|array',
            'files.*.file_path' => 'required|string|max:2048',
            'files.*.file_url' => 'nullable|string|max:2048',
        ]);

        return $data;
    }

    private function authorizeManage(FormSubmission $submission): User
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('manageResearchOutput', $submission), 403);

        // BR-RO-08 — sumbu **kapan**. Batasnya milik FormPhaseDetail tempat
        // luaran bergantung, bukan mekanisme tersendiri; membaca tetap terbuka
        // setelah tenggat, hanya menulis yang tertutup (BR-FE-15).
        PhaseAccessGate::assertOpenFor($submission, $submission->formPhaseDetail);

        return $user;
    }
}
