<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    public const COVER_QUALITY = 82;
    public const COVER_MAX_EDGE = 1600;
    public const COVER_VARIANTS = [640, 960];

    public const AVATAR_QUALITY = 82;
    public const AVATAR_MAX_EDGE = 400;
    public const AVATAR_VARIANTS = [96];

    public const CONTENT_QUALITY = 82;
    public const CONTENT_MAX_EDGE = 1600;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::gd();
    }

    /**
     * Convert jpeg/png/webp to WebP, optionally downscale.
     * GIF is kept as GIF (animation preserved) — no first-frame WebP.
     */
    public function convertToWebP(string $path, int $quality = self::COVER_QUALITY, ?int $maxEdge = self::COVER_MAX_EDGE): string
    {
        if ($this->isGifPath($path)) {
            return $path;
        }

        $fullPath = Storage::disk('public')->path($path);

        if (!file_exists($fullPath)) {
            throw new \Exception("Файл не найден: {$path}");
        }

        $image = $this->manager->read($fullPath);
        $this->scaleToMaxEdge($image, $maxEdge);

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);
        $webpFullPath = Storage::disk('public')->path($webpPath);

        $directory = dirname($webpFullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $image->toWebp($quality)->save($webpFullPath);

        return $webpPath;
    }

    /**
     * Store an uploaded image: jpeg/png → WebP (+ srcset); webp re-encoded;
     * GIF kept as .gif (no WebP convert, no srcset variants).
     * Deletes the original temp file after a successful convert (non-GIF).
     */
    public function storeUploadedImage(
        UploadedFile $file,
        string $directory,
        int $quality = self::COVER_QUALITY,
        int $maxEdge = self::COVER_MAX_EDGE,
        array $variants = []
    ): string {
        $originalPath = $file->store($directory, 'public');

        try {
            if ($this->isGifUpload($file, $originalPath)) {
                return $originalPath;
            }

            $webpPath = $this->convertToWebP($originalPath, $quality, $maxEdge);
            if ($webpPath !== $originalPath && Storage::disk('public')->exists($originalPath)) {
                Storage::disk('public')->delete($originalPath);
            }
            if ($variants) {
                $this->writeSrcsetVariants($webpPath, $variants, $quality);
            }
            return $webpPath;
        } catch (\Throwable $e) {
            Log::error('WebP conversion failed: ' . $e->getMessage(), [
                'path' => $originalPath,
            ]);
            return $originalPath;
        }
    }

    public function writeSrcsetVariants(string $webpPath, array $widths, int $quality = self::COVER_QUALITY): void
    {
        if ($this->isGifPath($webpPath)) {
            return;
        }

        $fullPath = Storage::disk('public')->path($webpPath);
        if (!file_exists($fullPath)) {
            return;
        }

        $base = preg_replace('/\.[^.]+$/', '', $webpPath);

        foreach ($widths as $width) {
            $width = (int) $width;
            if ($width < 16) {
                continue;
            }
            $variantRel = "{$base}-{$width}w.webp";
            $variantFull = Storage::disk('public')->path($variantRel);
            $image = $this->manager->read($fullPath);
            if ($image->width() > $width) {
                $image->scale(width: $width);
            }
            $image->toWebp($quality)->save($variantFull);
        }
    }

    public function deleteWithVariants(string $path): void
    {
        if ($path === '') {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        $base = preg_replace('/\.[^.]+$/', '', $path);
        foreach ([96, 640, 960, 1200] as $width) {
            $variant = "{$base}-{$width}w.webp";
            if ($disk->exists($variant)) {
                $disk->delete($variant);
            }
        }
    }

    public function isImage(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
        ], true);
    }

    /**
     * Detect GIF by upload mime and/or extension (client + stored path).
     */
    public function isGifUpload(UploadedFile $file, ?string $storedPath = null): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        if ($mime === 'image/gif') {
            return true;
        }

        $clientExt = strtolower((string) $file->getClientOriginalExtension());
        if ($clientExt === 'gif') {
            return true;
        }

        if ($storedPath !== null && $this->isGifPath($storedPath)) {
            return true;
        }

        return false;
    }

    public function isGifPath(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'gif';
    }

    private function scaleToMaxEdge($image, ?int $maxEdge): void
    {
        if (!$maxEdge || $maxEdge < 1) {
            return;
        }

        $width = $image->width();
        $height = $image->height();
        if ($width <= $maxEdge && $height <= $maxEdge) {
            return;
        }

        if ($width >= $height) {
            $image->scale(width: $maxEdge);
        } else {
            $image->scale(height: $maxEdge);
        }
    }
}
