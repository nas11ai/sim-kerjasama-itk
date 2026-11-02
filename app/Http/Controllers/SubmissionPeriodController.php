<?php

namespace App\Http\Controllers;

use App\Actions\Period\DeleteSubmissionPeriod;
use App\Models\SubmissionPeriod;
use Illuminate\Support\Facades\Auth;

class SubmissionPeriodController extends Controller
{
    public function destroy(SubmissionPeriod $submissionPeriod)
    {
        $actor = Auth::user();

        abort_unless($actor !== null, 403);

        // BR-FE-21 — penjagaannya berjalan sebelum tanggal dan tahapnya
        // disentuh, bukan mengandalkan rollback transaksi.
        DeleteSubmissionPeriod::run($submissionPeriod, $actor);

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengajuan berhasil dihapus.');
    }
}
