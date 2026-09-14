<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ConvertImagesToWebP extends Command
{
    protected $signature = 'images:convert-webp
                            {--dry-run : Только показать, что будет сделано}
                            {--keep-originals : Не удалять исходные PNG/JPG (по умолчанию оставляем как fallback)}';

    protected $description = 'Конвертирует превью блога и аватары в WebP и пишет srcset-варианты';

    public function handle(ImageService $imageService): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $this->convertDbBlogs($imageService, $disk, $dry);
        $this->convertDbAvatars($imageService, $disk, $dry);
        $this->convertOrphanFiles($imageService, $disk, $dry, 'blog/previews', ImageService::COVER_QUALITY, ImageService::COVER_MAX_EDGE, ImageService::COVER_VARIANTS);
        $this->convertOrphanFiles($imageService, $disk, $dry, 'authors/avatars', ImageService::AVATAR_QUALITY, ImageService::AVATAR_MAX_EDGE, ImageService::AVATAR_VARIANTS);
        $this->writeMissingVariants($imageService, $disk, $dry);

        $this->info('Готово.');
        return self::SUCCESS;
    }

    private function convertDbBlogs(ImageService $imageService, $disk, bool $dry): void
    {
        $blogs = Blog::whereNotNull('preview_image')
            ->where('preview_image', '!=', '')
            ->get();

        $this->info("Блоги с preview_image: {$blogs->count()}");

        foreach ($blogs as $blog) {
            $path = $blog->preview_image;
            if (!is_string($path) || $path === '') {
                continue;
            }

            if (!$disk->exists($path)) {
                $this->warn("Файл не найден, путь в БД не трогаем: {$path}");
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'webp' || $ext === 'gif') {
                continue;
            }

            if ($dry) {
                $this->line("Блог {$blog->id}: {$path} → webp");
                continue;
            }

            try {
                $webpPath = $imageService->convertToWebP($path, ImageService::COVER_QUALITY, ImageService::COVER_MAX_EDGE);
                $imageService->writeSrcsetVariants($webpPath, ImageService::COVER_VARIANTS, ImageService::COVER_QUALITY);
                $blog->update(['preview_image' => $webpPath]);
                $this->info("✓ Блог {$blog->id}: {$path} → {$webpPath}");
            } catch (\Throwable $e) {
                $this->error("✗ Блог {$blog->id}: {$e->getMessage()}");
            }
        }
    }

    private function convertDbAvatars(ImageService $imageService, $disk, bool $dry): void
    {
        $users = User::whereNotNull('avatar')->where('avatar', '!=', '')->get();
        $this->info("Авторы с avatar: {$users->count()}");

        foreach ($users as $user) {
            $path = $user->avatar;
            if (!is_string($path) || $path === '' || !$disk->exists($path)) {
                if (is_string($path) && $path !== '' && !$disk->exists($path)) {
                    $this->warn("Аватар не найден, путь в БД не трогаем: {$path}");
                }
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'webp' || $ext === 'gif') {
                continue;
            }

            if ($dry) {
                $this->line("Автор {$user->id}: {$path} → webp");
                continue;
            }

            try {
                $webpPath = $imageService->convertToWebP($path, ImageService::AVATAR_QUALITY, ImageService::AVATAR_MAX_EDGE);
                $imageService->writeSrcsetVariants($webpPath, ImageService::AVATAR_VARIANTS, ImageService::AVATAR_QUALITY);
                $user->update(['avatar' => $webpPath]);
                $this->info("✓ Автор {$user->id}: {$path} → {$webpPath}");
            } catch (\Throwable $e) {
                $this->error("✗ Автор {$user->id}: {$e->getMessage()}");
            }
        }
    }

    private function convertOrphanFiles(ImageService $imageService, $disk, bool $dry, string $dir, int $quality, int $maxEdge, array $variants): void
    {
        if (!$disk->exists($dir)) {
            return;
        }

        foreach ($disk->files($dir) as $path) {
            if (preg_match('/-\d+w\.webp$/i', $path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
                continue;
            }

            $size = $disk->size($path);
            if ($dry) {
                $this->line("Файл {$path} ({$size} B) → webp");
                continue;
            }

            try {
                $webpPath = $imageService->convertToWebP($path, $quality, $maxEdge);
                $imageService->writeSrcsetVariants($webpPath, $variants, $quality);
                $this->info("✓ Файл {$path} ({$size} B) → {$webpPath} (" . $disk->size($webpPath) . " B)");
            } catch (\Throwable $e) {
                $this->error("✗ Файл {$path}: {$e->getMessage()}");
            }
        }
    }

    private function writeMissingVariants(ImageService $imageService, $disk, bool $dry): void
    {
        foreach (Blog::whereNotNull('preview_image')->where('preview_image', '!=', '')->get() as $blog) {
            $path = $blog->preview_image;
            if (!$disk->exists($path)) {
                continue;
            }
            if ($dry) {
                $this->line("Варианты обложки: {$path}");
                continue;
            }
            $imageService->writeSrcsetVariants($path, ImageService::COVER_VARIANTS, ImageService::COVER_QUALITY);
        }

        foreach (User::whereNotNull('avatar')->where('avatar', '!=', '')->get() as $user) {
            $path = $user->avatar;
            if (!$disk->exists($path)) {
                continue;
            }
            if ($dry) {
                $this->line("Варианты аватара: {$path}");
                continue;
            }
            $imageService->writeSrcsetVariants($path, ImageService::AVATAR_VARIANTS, ImageService::AVATAR_QUALITY);
        }

        $this->info('Srcset-варианты обновлены.');
    }
}
