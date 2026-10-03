<?php

namespace App\Http\Controllers;

use App\Domain\Marketplace\ProductFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uploads a product file ahead of the product form:
 *  - cloud storage on: a signed PUT URL the browser sends the file to;
 *  - otherwise: a chunked session on this server — the browser sends the
 *    file in pieces smaller than PHP's upload limit, so large files work
 *    on any host.
 * Either way the form then submits the returned token to attach the file.
 *
 * Open to admins and to sellers (users with a store) — the same people
 * who can reach a product form.
 */
class ProductUploadController extends Controller
{
    public function store(Request $request, ProductFileService $files): JsonResponse
    {
        $this->authorizeUploader($request);

        $max = $files->maxBytes();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$max],
            'type' => ['nullable', 'string', 'max:150'],
        ], [
            'size.max' => __('The file is too large. The maximum is :size.', ['size' => $this->human($max)]),
        ]);

        try {
            return response()->json($files->startUpload($request->user()->id, $data['name'], (int) $data['size'], $data['type'] ?? null));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => __('Could not prepare the upload: :error', ['error' => $e->getMessage()]),
            ], 502);
        }
    }

    /**
     * One piece of a chunked server upload.
     */
    public function chunk(Request $request, string $token, ProductFileService $files): JsonResponse
    {
        $this->authorizeUploader($request);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.intdiv(ProductFileService::chunkBytes(), 1024) + 1],
        ]);

        $received = $files->appendChunk($token, $request->user()->id, (int) $data['index'], $request->file('chunk'));

        return response()->json(['received' => $received]);
    }

    private function authorizeUploader(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->is_admin || $user->vendor()->exists(), 403);
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1024 ** 3 ? round($bytes / 1024 ** 3).' GB' : round($bytes / 1024 ** 2).' MB';
    }
}
