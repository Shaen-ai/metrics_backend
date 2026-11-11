<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Provides detailed diagnostics when PHP drops an upload before Laravel
 * validation can run (e.g. upload_max_filesize / post_max_size exceeded).
 */
trait DetectsUploadFailures
{
    protected function invalidUploadResponse(string $field, ?UploadedFile $file): ?JsonResponse
    {
        if ($file === null) {
            if (empty(request()->allFiles())) {
                $contentLength = (int) (request()->server('CONTENT_LENGTH') ?? 0);
                $postMax = $this->detectIniBytes(ini_get('post_max_size'));
                if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
                    return response()->json([
                        'message' => 'The request is larger than PHP post_max_size ('.ini_get('post_max_size').'). Increase post_max_size to at least 22M in php.ini and restart the PHP server.',
                        'errors' => [$field => ['Request body was rejected: increase PHP post_max_size.']],
                    ], 422);
                }
            }

            return null; // Field simply wasn't sent — let normal validation handle 'required'
        }

        if (! $file->isValid()) {
            $code = (int) $file->getError();
            $hint = $this->detectPhpUploadErrorMessage($code);

            return response()->json([
                'message' => $hint,
                'errors' => [$field => [$hint]],
                'php_upload_error' => $code,
                'php_upload_max' => ini_get('upload_max_filesize'),
                'php_post_max' => ini_get('post_max_size'),
            ], 422);
        }

        return null;
    }

    private function detectIniBytes(string|false $val): int
    {
        if ($val === false || $val === '') {
            return 0;
        }
        $num = (int) $val;
        $unit = strtolower(substr(trim($val), -1));

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function detectPhpUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini ('.ini_get('upload_max_filesize').'). Increase it to at least 20M.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing PHP temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => "Upload failed with error code $code.",
        };
    }
}
