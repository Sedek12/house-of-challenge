<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Images : redimensionnées et converties en WebP à l'envoi, pour des pages légères. */
class UploadService
{
    /** @return string chemin relatif sur le disque public */
    public static function storeProductPhoto(UploadedFile $file): string
    {
        return self::storeImage($file, 'products', 800, 78);
    }

    /** @return string chemin relatif sur le disque public */
    public static function storeImage(UploadedFile $file, string $folder, int $maxSide, int $quality = 80): string
    {
        $img = self::load($file);
        if ($img === null) {
            // Format illisible par GD : on garde le fichier tel quel plutôt que de perdre l'image.
            return $file->store($folder, 'public');
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $ratio = min(1, $maxSide / max($w, $h));
        if ($ratio < 1) {
            $img = imagescale($img, max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)));
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        ob_start();
        imagewebp($img, null, $quality);
        $path = $folder.'/'.bin2hex(random_bytes(12)).'.webp';
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }

    /** Copie un fichier du disque public sous un nouveau nom (chaque réservation garde ses propres photos). */
    public static function duplicate(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $new = dirname($path).'/'.bin2hex(random_bytes(12)).'.'.pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk('public')->copy($path, $new);

        return $new;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function load(UploadedFile $file): ?\GdImage
    {
        if (! function_exists('imagewebp')) {
            return null;
        }
        $data = @file_get_contents($file->getRealPath());
        $img = $data === false ? false : @imagecreatefromstring($data);

        return $img ?: null;
    }
}
