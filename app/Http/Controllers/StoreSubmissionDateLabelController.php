<?php

namespace App\Http\Controllers;

use App\Models\SubmissionDateLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Label tanggal baru dari Create/EditPage periode; dijawab JSON, bukan redirect. */
class StoreSubmissionDateLabelController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:submission_date_labels,name',
        ]);

        return response()->json(SubmissionDateLabel::create(['name' => $request->string('name')->value()]));
    }
}
