<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $file = $request->file('image');
        $invalid = $this->invalidUploadResponse('image', $file);
        if ($invalid !== null) {
            return $invalid;
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        return response()->json([
            'url' => $this->storeImageFile($request->file('image'), 'images', (string) $request->user()->id),
        ]);
    }

    /**
     * Stores a laminate/wood/worktop sheet texture image.
     * Lives in its own folder so production and support can find all per-user
     * uploads grouped by purpose.
     */
    public function storeMaterialImage(Request $request): JsonResponse
    {
        $file = $request->file('image');
        $invalid = $this->invalidUploadResponse('image', $file);
        if ($invalid !== null) {
            return $invalid;
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $file = $request->file('image');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = uniqid('mat_', true) . '.' . $ext;

        return response()->json([
            'url' => $this->storeImageFile($file, 'materials', (string) $request->user()->id, $filename),
        ]);
    }

    public function storeModel(Request $request): JsonResponse
    {
        $file = $request->file('model');
        $invalid = $this->invalidUploadResponse('model', $file);
        if ($invalid !== null) {
            return $invalid;
        }

        $request->validate([
            'model' => ['required', 'file', 'max:10240'],
            'filename' => ['sometimes', 'string', 'max:255'],
        ]);

        $file = $request->file('model');
        $ext = $file->getClientOriginalExtension();

        if (!in_array(strtolower($ext), ['glb', 'gltf'])) {
            return response()->json(
                ['message' => 'Only .glb and .gltf files are accepted'],
                422
            );
        }

        $filename = $this->safeModelFilename($request->input('filename', $file->getClientOriginalName()));
        if (!str_ends_with(strtolower($filename), '.glb') && !str_ends_with(strtolower($filename), '.gltf')) {
            $filename .= '.' . $ext;
        }

        return response()->json([
            'url' => $this->storeModelFile($file, $filename, (string) $request->user()->id),
        ]);
    }

    public function downloadRemoteModel(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url'],
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $remoteUrl = $request->input('url');
        $filename = $this->safeModelFilename($request->input('filename'));

        if (!str_ends_with(strtolower($filename), '.glb') && !str_ends_with(strtolower($filename), '.gltf')) {
            $filename .= '.glb';
        }

        try {
            $response = Http::timeout(120)->withOptions(['stream' => true])->get($remoteUrl);

            if (!$response->successful()) {
                return response()->json(
                    ['message' => 'Failed to download remote file: ' . $response->status()],
                    422
                );
            }

            return response()->json([
                'url' => $this->storeModelContents($response->body(), $filename, (string) $request->user()->id),
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Failed to download remote model: ' . $e->getMessage()],
                500
            );
        }
    }

    /**
     * Laravel’s generic "The image failed to upload" means UploadedFile::isValid() is false
     * (most often PHP’s upload_max_filesize / post_max_size exceeded before the file reached the app).
     */
    private function invalidUploadResponse(string $field, ?UploadedFile $file): ?JsonResponse
    {
        if ($file === null) {
            if (empty(request()->allFiles())) {
                $contentLength = (int) (request()->server('CONTENT_LENGTH') ?? 0);
                $postMax = $this->iniBytes(ini_get('post_max_size'));
                if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
                    return response()->json([
                        'message' => 'The request is larger than PHP post_max_size ('.ini_get('post_max_size').'). Increase post_max_size to at least 12M in php.ini and restart the PHP server.',
                        'errors' => [
                            $field => ['Request body was rejected: increase PHP post_max_size.'],
                        ],
                    ], 422);
                }
            }

            return response()->json([
                'message' => 'No file was received. Use multipart/form-data, field name "'.$field.'".',
                'errors' => [
                    $field => ['No file was received.'],
                ],
            ], 422);
        }

        if (! $file->isValid()) {
            $code = (int) $file->getError();
            $hint = $this->phpUploadErrorMessage($code);

            return response()->json([
                'message' => $hint,
                'errors' => [
                    $field => [$hint],
                ],
                'php_upload_error' => $code,
                'php_upload_max' => ini_get('upload_max_filesize'),
                'php_post_max' => ini_get('post_max_size'),
            ], 422);
        }

        return null;
    }

    private function safeModelFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', trim($filename)));

        return $basename !== '' ? $basename : uniqid('model_', true).'.glb';
    }

    private function storeImageFile(
        UploadedFile $file,
        string $purpose,
        string $userId,
        ?string $filename = null
    ): string {
        $filename ??= $file->hashName();
        $externalDir = config('services.image_upload_path');
        if (is_string($externalDir) && trim($externalDir) !== '') {
            $dir = rtrim($externalDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $purpose;
            File::ensureDirectoryExists($dir, 0775, true);
            $file->move($dir, $filename);

            return $this->externalImageUrl($userId, $purpose, $filename);
        }

        $dir = 'files/' . $userId . '/' . $purpose;
        $path = $file->storeAs($dir, $filename, 'public');

        return url('/storage/' . $path);
    }

    private function storeModelFile(UploadedFile $file, string $filename, string $userId): string
    {
        $externalDir = config('services.model_upload_path');
        if (is_string($externalDir) && trim($externalDir) !== '') {
            $dir = rtrim($externalDir, DIRECTORY_SEPARATOR);
            File::ensureDirectoryExists($dir, 0775, true);
            $file->move($dir, $filename);

            return $this->externalModelUrl($filename);
        }

        $dir = 'files/' . $userId . '/models';
        $path = $file->storeAs($dir, $filename, 'public');

        return url('/storage/' . $path);
    }

    private function storeModelContents(string $contents, string $filename, string $userId): string
    {
        $externalDir = config('services.model_upload_path');
        if (is_string($externalDir) && trim($externalDir) !== '') {
            $dir = rtrim($externalDir, DIRECTORY_SEPARATOR);
            File::ensureDirectoryExists($dir, 0775, true);
            File::put($dir . DIRECTORY_SEPARATOR . $filename, $contents);

            return $this->externalModelUrl($filename);
        }

        $dir = 'files/' . $userId . '/models';
        Storage::disk('public')->put($dir . '/' . $filename, $contents);

        return url('/storage/' . $dir . '/' . $filename);
    }

    private function externalModelUrl(string $filename): string
    {
        $urlPath = '/' . trim((string) config('services.model_upload_url_path', '/files/models'), '/');

        return url($urlPath . '/' . rawurlencode($filename));
    }

    private function externalImageUrl(string $userId, string $purpose, string $filename): string
    {
        $urlPath = '/' . trim((string) config('services.image_upload_url_path', '/files/images'), '/');

        return url($urlPath . '/' . rawurlencode($userId) . '/' . rawurlencode($purpose) . '/' . rawurlencode($filename));
    }

    private function phpUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is larger than the server allows (PHP limit). Increase upload_max_filesize and post_max_size in php.ini (e.g. 10M and 12M) and restart PHP. Current limit: upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size').'.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Try again with a smaller file or a stable connection.',
            UPLOAD_ERR_NO_FILE => 'No file was received.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder (upload_tmp_dir).',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk on the server.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'The image failed to upload (error code '.$code.').',
        };
    }

    private function iniBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '0') {
            return 0;
        }
        $last = strtoupper(substr($val, -1));
        if (in_array($last, ['G', 'M', 'K'], true)) {
            $n = (float) substr($val, 0, -1);
        } else {
            $n = (float) $val;
            $last = 'B';
        }
        $mult = match ($last) {
            'G' => 1024 * 1024 * 1024,
            'M' => 1024 * 1024,
            'K' => 1024,
            default => 1,
        };

        return (int) ($n * $mult);
    }
}
