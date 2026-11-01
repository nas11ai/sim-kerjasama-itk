<?php

namespace App\Http\Controllers;

use App\Actions\Announcement\UploadAnnouncementImage;
use App\Http\Requests\Announcement\AnnouncementImageRequest;
use Illuminate\Http\JsonResponse;

/** fe-v2-design §4.7 K6a — editor pengumuman mengunggah gambar sisipan lewat `useHttp`. */
class AnnouncementImageController extends Controller
{
    public function __invoke(AnnouncementImageRequest $request): JsonResponse
    {
        $url = UploadAnnouncementImage::run($request->image(), $request->user());

        return response()->json(['url' => $url], 201);
    }
}
