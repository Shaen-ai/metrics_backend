<?php

namespace App\Http\Controllers;

use App\Support\VistaFilePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VistaFilesController extends Controller
{
    /**
     * GET /vista-files/{path}
     *
     * Serves files from the vista_files disk (public previews for saved projects).
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $disk = Storage::disk('vista_files');
        $resolvedPath = VistaFilePath::resolveExistingPath($disk, $path);
        if ($resolvedPath === null) {
            abort(404);
        }

        $mime = $disk->mimeType($resolvedPath) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $resolvedPath) {
            $stream = $disk->readStream($resolvedPath);
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
