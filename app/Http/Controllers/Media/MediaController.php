<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
     * Serve the same public uploads as /storage, with CORS and byte ranges for
     * mobile audio/video. This also works when a storage symlink is unavailable.
     */
    public function show(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));

        abort_unless($root && $file && is_file($file)
            && str_starts_with($file, $root . DIRECTORY_SEPARATOR), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
