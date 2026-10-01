<?php

namespace App\Domain\Marketplace;

use App\Models\Download;
use App\Models\Product;
use App\Services\FileStorageSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The file a buyer downloads after paying for a product.
 *
 * Two storages, chosen in Admin → File storage:
 *  - cloud (S3 / Google Cloud Storage / R2 …): the browser uploads straight
 *    to the bucket through a signed URL ({@see self::presignUpload()}), so
 *    PHP's upload limit doesn't apply; buyers download through a signed
 *    URL valid for a few minutes;
 *  - server (default): uploaded through PHP onto the private `local` disk
 *    (storage/app/private) and streamed by Laravel.
 *
 * Either way files are private and the only way out is
 * {@see self::deliver()}, behind the buyer's Download grant. Each product
 * remembers its disk, so changing the storage later keeps old files working.
 */
class ProductFileService
{
    /** The server disk (private). */
    public const DISK = 'local';

    /** Max server upload in kilobytes (200 MB) — PHP's own limits apply on top. */
    public const MAX_KB = 204800;

    /** Max direct-to-cloud upload (5 GB, S3's single-upload limit). */
    public const CLOUD_MAX_BYTES = 5 * 1024 ** 3;

    /** How long a signed upload / download URL stays valid. */
    private const UPLOAD_TTL_MINUTES = 60;

    private const DOWNLOAD_TTL_MINUTES = 10;

    public function __construct(private readonly FileStorageSettings $storage) {}

    /**
     * How the product form uploads: straight to the cloud, or to this
     * server in small chunks — each under PHP's upload limit, so files up
     * to MAX_KB work whatever php.ini says.
     *
     * @return array{mode: 'direct'|'chunked', max_bytes: int}
     */
    public function uploadOptions(): array
    {
        return $this->storage->enabled()
            ? ['mode' => 'direct', 'max_bytes' => self::CLOUD_MAX_BYTES]
            : ['mode' => 'chunked', 'max_bytes' => self::MAX_KB * 1024];
    }

    /** Largest file for the current storage, in bytes. */
    public function maxBytes(): int
    {
        return $this->uploadOptions()['max_bytes'];
    }

    /**
     * Chunk size for server uploads: half of what PHP accepts per request
     * (headroom for the rest of the form data), between 256 KB and 8 MB.
     */
    public static function chunkBytes(): int
    {
        return (int) max(256 * 1024, min(8 * 1024 * 1024, intdiv(self::maxUploadBytes(), 2)));
    }

    /**
     * Start an upload for the product form: a signed URL for the cloud
     * bucket, or a chunked session on this server.
     *
     * @return array<string, mixed>
     */
    public function startUpload(int $userId, string $name, int $size, ?string $contentType): array
    {
        if ($this->storage->enabled()) {
            return ['mode' => 'direct', ...$this->presignUpload($userId, $name, $size, $contentType)];
        }

        $this->pruneStaleChunks();

        $token = Str::random(48);
        Cache::put($this->tokenKey($token), [
            'user_id' => $userId,
            'disk' => self::DISK,
            'key' => 'upload-chunks/'.$token.'.part',
            'name' => $this->cleanName($name, $this->extension($name)),
            'size' => $size,
            'received' => 0,
            'next' => 0,
        ], now()->addMinutes(self::UPLOAD_TTL_MINUTES * 2));

        Storage::disk(self::DISK)->put('upload-chunks/'.$token.'.part', '');

        return ['mode' => 'chunked', 'token' => $token, 'chunk_size' => self::chunkBytes()];
    }

    /**
     * Append the next chunk of a server upload. Chunks must arrive in
     * order and may not grow the file past the size announced at start.
     */
    public function appendChunk(string $token, int $userId, int $index, UploadedFile $chunk): int
    {
        $upload = Cache::get($this->tokenKey($token));

        if (! is_array($upload) || $upload['user_id'] !== $userId || ($upload['disk'] ?? null) !== self::DISK) {
            throw ValidationException::withMessages(['chunk' => __('The upload expired or was not found. Please choose the file again.')]);
        }
        if ($index !== $upload['next']) {
            throw ValidationException::withMessages(['chunk' => __('Upload chunks arrived out of order. Please try again.')]);
        }
        if ($upload['size'] < $upload['received'] + $chunk->getSize()) {
            throw ValidationException::withMessages(['chunk' => __('The file is larger than announced. Please choose it again.')]);
        }

        $target = fopen(Storage::disk(self::DISK)->path($upload['key']), 'ab');
        $source = fopen($chunk->getRealPath(), 'rb');
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        $upload['received'] += $chunk->getSize();
        $upload['next']++;
        Cache::put($this->tokenKey($token), $upload, now()->addMinutes(self::UPLOAD_TTL_MINUTES * 2));

        return $upload['received'];
    }

    /**
     * Chunk files of uploads that were never finished (tab closed …).
     */
    private function pruneStaleChunks(): void
    {
        $disk = Storage::disk(self::DISK);
        foreach ($disk->files('upload-chunks') as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    /**
     * The largest file this server accepts right now, in bytes: our own cap,
     * lowered by PHP's upload_max_filesize / post_max_size (php.ini).
     */
    public static function maxUploadBytes(): int
    {
        $limits = [self::MAX_KB * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return min($limits);
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Apply the product form's file fields: a direct-to-cloud upload
     * (token) or a server upload replaces the current file; `remove`
     * (without either) deletes it.
     */
    public function sync(Product $product, ?UploadedFile $file, bool $remove = false, ?string $token = null, ?int $userId = null): void
    {
        if (filled($token)) {
            $this->attachUploaded($product, (string) $token, (int) $userId);
        } elseif ($file) {
            $this->replace($product, $file);
        } elseif ($remove) {
            $this->remove($product);
        }
    }

    /**
     * Server upload onto the private local disk.
     */
    public function replace(Product $product, UploadedFile $file): void
    {
        $extension = $this->extension($file->getClientOriginalName(), $file->extension());
        $path = $file->storeAs("product-files/{$product->id}", Str::random(40).'.'.$extension, self::DISK);

        $this->point($product, self::DISK, $path, $this->cleanName($file->getClientOriginalName(), $extension), $file->getSize());
    }

    /**
     * Start a direct-to-cloud upload: a signed PUT URL for a fresh object
     * key, plus a one-time token the product form submits afterwards.
     *
     * @return array{token: string, url: string, headers: array<string, string>}
     */
    public function presignUpload(int $userId, string $name, int $size, ?string $contentType): array
    {
        $extension = $this->extension($name);
        $key = 'product-files/'.now()->format('Y/m').'/'.Str::uuid().'/'.Str::random(24).'.'.$extension;
        $contentType = $contentType ?: 'application/octet-stream';

        $signed = $this->cloud()->temporaryUploadUrl($key, now()->addMinutes(self::UPLOAD_TTL_MINUTES), [
            'ContentType' => $contentType,
        ]);

        $token = Str::random(48);
        Cache::put($this->tokenKey($token), [
            'user_id' => $userId,
            'key' => $key,
            'name' => $this->cleanName($name, $extension),
            'size' => $size,
        ], now()->addMinutes(self::UPLOAD_TTL_MINUTES * 2));

        // Browsers set Host (and refuse to be told); keep the rest, which
        // the signature covers (e.g. Content-Type).
        $headers = collect($signed['headers'] ?? [])
            ->map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v)
            ->reject(fn ($v, $k) => strtolower((string) $k) === 'host')
            ->put('Content-Type', $contentType)
            ->all();

        return ['token' => $token, 'url' => $signed['url'], 'headers' => $headers];
    }

    /**
     * Point the product at an object the same user just uploaded to the
     * cloud, after checking it really arrived.
     */
    public function attachUploaded(Product $product, string $token, int $userId): void
    {
        $upload = Cache::get($this->tokenKey($token));

        if (! is_array($upload) || $upload['user_id'] !== $userId) {
            throw ValidationException::withMessages([
                'download_file' => __('The upload expired or was not found. Please choose the file again.'),
            ]);
        }

        // Chunked server upload: complete → move the assembled file into place.
        if (($upload['disk'] ?? null) === self::DISK) {
            if ($upload['received'] !== $upload['size']) {
                throw ValidationException::withMessages([
                    'download_file' => __('The file did not finish uploading. Please upload it again.'),
                ]);
            }

            $path = "product-files/{$product->id}/".Str::random(40).'.'.pathinfo($upload['name'], PATHINFO_EXTENSION);
            Storage::disk(self::DISK)->move($upload['key'], $path);

            $this->point($product, self::DISK, $path, $upload['name'], $upload['size']);
            Cache::forget($this->tokenKey($token));

            return;
        }

        $disk = $this->cloud();
        if (! $disk->exists($upload['key'])) {
            throw ValidationException::withMessages([
                'download_file' => __('The file did not reach the storage bucket. Please upload it again.'),
            ]);
        }

        $this->point($product, FileStorageSettings::DISK, $upload['key'], $upload['name'], $disk->size($upload['key']));
        Cache::forget($this->tokenKey($token));
    }

    public function remove(Product $product): void
    {
        $this->deleteStored($product);

        $product->forceFill([
            'download_file_path' => null,
            'download_file_disk' => null,
            'download_file_name' => null,
            'download_file_size' => null,
        ])->save();
    }

    public function exists(Product $product): bool
    {
        if (! $product->hasDownloadFile()) {
            return false;
        }

        try {
            return $this->diskFor($product)?->exists($product->download_file_path) ?? false;
        } catch (\Throwable) {
            return false; // cloud unreachable / misconfigured
        }
    }

    /**
     * Send the product file to the buyer and count the download. The
     * caller has already checked ownership and the download limit.
     */
    public function deliver(Download $download, ?string $ip = null): StreamedResponse|RedirectResponse
    {
        $product = $download->product;
        $name = $product->download_file_name ?: Str::slug($product->title).'.'.pathinfo($product->download_file_path, PATHINFO_EXTENSION);

        $download->forceFill([
            'downloads_count' => $download->downloads_count + 1,
            'last_downloaded_at' => now(),
            'last_ip' => $ip,
        ])->save();

        if ($this->isCloud($product)) {
            // Straight from the bucket, through a link that expires in minutes.
            return redirect()->away($this->cloud()->temporaryUrl(
                $product->download_file_path,
                now()->addMinutes(self::DOWNLOAD_TTL_MINUTES),
                ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($name).'"'],
            ));
        }

        return Storage::disk(self::DISK)->download($product->download_file_path, $name);
    }

    /**
     * The cloud disk, built from Admin → File storage.
     */
    public function cloud(): Filesystem
    {
        if (! $this->storage->apply()) {
            throw ValidationException::withMessages([
                'download_file' => __('Cloud storage is not set up. Configure it in Admin → File storage.'),
            ]);
        }

        return Storage::disk(FileStorageSettings::DISK);
    }

    private function isCloud(Product $product): bool
    {
        return $product->download_file_disk === FileStorageSettings::DISK;
    }

    private function diskFor(Product $product): ?Filesystem
    {
        if ($this->isCloud($product)) {
            return $this->storage->apply() ? Storage::disk(FileStorageSettings::DISK) : null;
        }

        return Storage::disk(self::DISK);
    }

    private function point(Product $product, string $disk, string $path, string $name, ?int $size): void
    {
        // Just enough of the old state to delete the file it pointed at.
        $previous = (new Product)->forceFill([
            'download_file_path' => $product->download_file_path,
            'download_file_disk' => $product->download_file_disk,
        ]);

        $product->forceFill([
            'download_file_path' => $path,
            'download_file_disk' => $disk,
            'download_file_name' => $name,
            'download_file_size' => $size,
        ])->save();

        if ($previous->download_file_path && ($previous->download_file_path !== $path || $previous->download_file_disk !== $disk)) {
            $this->deleteStored($previous);
        }
    }

    private function deleteStored(Product $product): void
    {
        if (! $product->download_file_path) {
            return;
        }

        try {
            $this->diskFor($product)?->delete($product->download_file_path);
        } catch (\Throwable) {
            // An orphaned object is better than a failed save.
        }
    }

    private function tokenKey(string $token): string
    {
        return 'product-upload:'.$token;
    }

    private function extension(string $name, ?string $guessed = null): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: ($guessed ?: 'bin'));

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) ? $extension : 'bin';
    }

    /**
     * Keep the uploader's file name (it is what the buyer sees) but strip
     * anything that could break a Content-Disposition header or a path.
     */
    private function cleanName(string $name, string $extension): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\pL\pN._ -]+/u', '-', $base) ?? '', ' .-');

        return Str::limit($base !== '' ? $base : 'download', 150, '').'.'.$extension;
    }
}
