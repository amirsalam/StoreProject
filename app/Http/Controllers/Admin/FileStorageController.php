<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFileStorageRequest;
use App\Models\ActivityLog;
use App\Services\FileStorageSettings;
use Aws\Exception\AwsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → File storage: where product files live. Cloud storage (S3,
 * Google Cloud Storage, R2 …) lifts PHP's upload limit — browsers upload
 * straight to the bucket — and serves downloads from the bucket.
 */
class FileStorageController extends Controller
{
    public function __construct(private readonly FileStorageSettings $settings) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('admin/storage/edit', [
            'settings' => $this->settings->forForm(),
            'providers' => collect(FileStorageSettings::PROVIDERS)
                ->map(fn (array $p, string $value) => ['value' => $value, 'label' => $p[0], 'endpoint' => $p[1], 'region' => $p[2]])
                ->values(),
            'cors' => FileStorageSettings::corsRules($request->getSchemeAndHttpHost()),
            'origin' => $request->getSchemeAndHttpHost(),
        ]);
    }

    public function update(UpdateFileStorageRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        ActivityLog::record('file_storage.updated', $request->user(), [
            'enabled' => $request->boolean('enabled'),
            'provider' => $request->validated('provider'),
            'bucket' => $request->validated('bucket'),
        ]);

        return back()->with('success', __('Storage settings saved. Use “Test connection” to check them.'));
    }

    /**
     * Write, read and delete a small object, and sign an upload URL — the
     * same operations product uploads and downloads use.
     */
    public function test(): RedirectResponse
    {
        if (! $this->settings->apply()) {
            return back()->with('error', __('Turn on cloud storage and fill in the bucket and keys, then save first.'));
        }

        $disk = Storage::disk(FileStorageSettings::DISK);
        $key = 'connection-test/'.Str::random(16).'.txt';

        try {
            $disk->put($key, 'StoreProject storage test');
            $ok = $disk->get($key) === 'StoreProject storage test';
            $disk->delete($key);
            $disk->temporaryUploadUrl('connection-test/signing.txt', now()->addMinute());
        } catch (\Throwable $e) {
            $reason = $e instanceof AwsException ? ($e->getAwsErrorMessage() ?: $e->getMessage()) : ($e->getPrevious()?->getMessage() ?: $e->getMessage());

            return back()->with('error', __('The storage could not be reached: :error', ['error' => Str::limit((string) $reason, 300)]));
        }

        if (! $ok) {
            return back()->with('error', __('The storage answered, but the test file came back different.'));
        }

        return back()->with('success', __('Connected — files can be stored in this bucket. Make sure the bucket’s CORS rules below are set, so browsers can upload to it.'));
    }
}
