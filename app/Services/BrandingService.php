<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Branding settings + logo storage.
 *
 * The logo lives under the "public" disk (storage/app/public/branding).
 * The Setting model holds the relative path, never the full URL, so the
 * APP_URL can change without breaking links.
 */
class BrandingService
{
    public const DEFAULT_TITLE = 'StoreProject';

    public const LOGO_DIRECTORY = 'branding';

    public const TITLE_KEY = 'site.title';

    public const LOGO_KEY = 'site.logo_path';

    /**
     * Maximum dimension (long side) we resize uploaded raster logos down
     * to. Smaller files, identical visual quality at logo sizes.
     */
    public const MAX_RASTER_DIMENSION = 512;

    /**
     * @return array{title: string, logo_url: string|null, has_custom_logo: bool}
     */
    public function summary(): array
    {
        $title = (string) Setting::get(self::TITLE_KEY, self::DEFAULT_TITLE);
        $logoPath = Setting::get(self::LOGO_KEY);
        $hasCustom = is_string($logoPath) && $logoPath !== '' && Storage::disk('public')->exists($logoPath);

        return [
            'title' => $title !== '' ? $title : self::DEFAULT_TITLE,
            'logo_url' => $hasCustom ? Storage::disk('public')->url($logoPath) : null,
            'has_custom_logo' => $hasCustom,
        ];
    }

    public function updateTitle(string $title): void
    {
        $title = trim($title);
        Setting::put(self::TITLE_KEY, $title === '' ? self::DEFAULT_TITLE : $title);
    }

    /**
     * Replace the current logo. Removes the previous file on success.
     */
    public function replaceLogo(UploadedFile $file): string
    {
        $this->deleteLogo();

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = self::LOGO_DIRECTORY . '/logo-' . Str::random(16) . '.' . $extension;

        if ($extension === 'svg') {
            // Sanitize SVG contents on disk to strip <script>, on* attrs,
            // javascript:/data: URIs, and any DOCTYPE.
            $clean = $this->sanitizeSvg(file_get_contents($file->getRealPath()) ?: '');
            Storage::disk('public')->put($filename, $clean);
        } else {
            // Resize + re-encode raster uploads to bound the on-disk size.
            $optimized = $this->optimizeRaster($file->getRealPath(), $extension);
            if ($optimized !== null) {
                Storage::disk('public')->put($filename, $optimized);
            } else {
                // Fallback: write the original bytes if GD failed for any reason.
                $stream = fopen($file->getRealPath(), 'rb');
                Storage::disk('public')->put($filename, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        Setting::put(self::LOGO_KEY, $filename, 'file');

        return $filename;
    }

    /**
     * Remove the stored logo and revert to the default mark.
     */
    public function deleteLogo(): void
    {
        $path = Setting::get(self::LOGO_KEY);
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        Setting::forget(self::LOGO_KEY);
    }

    /**
     * Minimal SVG sanitizer.
     *
     * Note: for production-grade sanitization we recommend installing
     * enshrined/svg-sanitize. This routine handles the most common
     * vectors (inline scripts, event handlers, javascript: URLs,
     * DOCTYPE / external entity references), which is enough for an
     * admin-only upload surface.
     */
    public function sanitizeSvg(string $contents): string
    {
        // Strip BOM / leading whitespace
        $contents = ltrim($contents, "\xEF\xBB\xBF \t\n\r\0\x0B");

        // Remove DOCTYPE + XML processing instructions (XXE vector)
        $contents = preg_replace('/<\?xml[^>]*\?>/i', '', $contents) ?? $contents;
        $contents = preg_replace('/<!DOCTYPE[^>]*>/i', '', $contents) ?? $contents;
        $contents = preg_replace('/<!ENTITY[^>]*>/i', '', $contents) ?? $contents;

        // Remove <script> blocks
        $contents = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $contents) ?? $contents;
        $contents = preg_replace('#<script\b[^>]*/?>#i', '', $contents) ?? $contents;

        // Remove <foreignObject> blocks (can host arbitrary HTML)
        $contents = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $contents) ?? $contents;

        // Strip event handler attributes (on*=)
        $contents = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $contents) ?? $contents;

        // Neutralize javascript: / data: hrefs in href/xlink:href. Each quote
        // style is matched separately so the value runs to its own closing
        // quote — `href="javascript:alert('x')"` must not stop at the inner '.
        $contents = preg_replace(
            '/(href|xlink:href)\s*=\s*(?:"\s*(?:javascript|data|vbscript):[^"]*"|\'\s*(?:javascript|data|vbscript):[^\']*\'|(?:javascript|data|vbscript):[^\s>]*)/i',
            '$1="#"',
            $contents,
        ) ?? $contents;

        return $contents;
    }

    /**
     * Resize + re-encode an uploaded raster image so it never exceeds
     * MAX_RASTER_DIMENSION on its long side. Returns the encoded binary
     * blob, or null if GD can't handle the file (caller falls back to
     * writing the original bytes).
     */
    private function optimizeRaster(string $sourcePath, string $extension): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if (! $src instanceof \GdImage) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $maxSide = max($srcW, $srcH);

        if ($maxSide > self::MAX_RASTER_DIMENSION) {
            $scale = self::MAX_RASTER_DIMENSION / $maxSide;
            $dstW = max(1, (int) round($srcW * $scale));
            $dstH = max(1, (int) round($srcH * $scale));
            $dst = imagecreatetruecolor($dstW, $dstH);
            if (! $dst instanceof \GdImage) {
                imagedestroy($src);
                return null;
            }
            // Preserve transparency for PNG/WebP.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        $ok = match ($extension) {
            'png' => imagepng($src, null, 6),               // 0–9, 6 is a good speed/size tradeoff
            'jpg', 'jpeg' => imagejpeg($src, null, 82),    // visually transparent at 82
            'webp' => function_exists('imagewebp') ? imagewebp($src, null, 82) : false,
            default => false,
        };
        $blob = ob_get_clean();
        imagedestroy($src);

        return $ok && is_string($blob) && $blob !== '' ? $blob : null;
    }
}
