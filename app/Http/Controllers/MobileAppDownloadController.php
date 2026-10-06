<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileAppDownloadController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $path = config('app.mobile_apk');
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404, 'The mobile app file has not been uploaded yet.');

        return $disk->download($path, basename($path), [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
