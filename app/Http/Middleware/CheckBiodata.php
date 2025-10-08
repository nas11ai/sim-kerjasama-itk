<?php
// filepath: e:\ITK\sim-kerjasama-itk\app\Http\Middleware\CheckBiodata.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Log;

class CheckBiodata
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $userRole = $user->getRoleNames()->toArray();
        $needsBiodataCheck = collect($userRole)->intersect(['Mahasiswa', 'Tenaga Kependidikan'])->isNotEmpty();

        if (!$needsBiodataCheck) {
            session([
                'biodataStatus' => [
                    'required' => false,
                    'completed' => true,
                    'showAllMenus' => true,
                ]
            ]);

            return $next($request);
        }

        $biodata = Form::where('form_type_id', 1)->first();

        if (!$biodata) {
            session([
                'biodataStatus' => [
                    'required' => false,
                    'completed' => true,
                    'showAllMenus' => true,
                    'message' => 'Form biodata tidak ditemukan. Silakan hubungi administrator.',
                ]
            ]);

            return $next($request);
        }

        $submission = FormSubmission::where('form_id', $biodata->id)
            ->where('submitted_by', $user->id)
            ->first();

        if (!$submission) {
            session([
                'biodataStatus' => [
                    'required' => true,
                    'completed' => false,
                    'showAllMenus' => false,
                    'message' => 'Silakan lengkapi biodata Anda terlebih dahulu.',
                    'form_id' => $biodata->id,
                ]
            ]);

            if (!$request->routeIs('user.dashboard')) {
                return redirect()->route('user.dashboard')
                    ->with('warning', 'Silakan lengkapi biodata Anda terlebih dahulu.');
            }

            return $next($request);
        }

        if ($submission->status->value !== 'approved') {
            $statusMessage = match ($submission->status->value) {
                'pending' => 'Biodata Anda sedang menunggu persetujuan.',
                'under_review' => 'Biodata Anda sedang dalam proses review.',
                'rejected' => 'Biodata Anda ditolak. Silakan perbaiki dan kirim ulang.',
                'needs_revision' => 'Biodata Anda memerlukan revisi.',
                default => 'Biodata Anda belum disetujui.',
            };

            session([
                'biodataStatus' => [
                    'required' => true,
                    'completed' => false,
                    'showAllMenus' => false,
                    'status' => $submission->status->value,
                    'message' => $statusMessage,
                    'submission_id' => $submission->id,
                    'form_id' => $biodata->id,
                ]
            ]);

            if (!$request->routeIs('user.dashboard')) {
                return redirect()->route('user.dashboard')
                    ->with('info', $statusMessage);
            }

            return $next($request);
        }

        session([
            'biodataStatus' => [
                'required' => true,
                'completed' => true,
                'showAllMenus' => true,
                'status' => 'approved',
                'submission_id' => $submission->id,
            ]
        ]);

        return $next($request);
    }
}
