<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Stockage des images : disque local (dev) ou Cloudinary (UPLOAD_PROVIDER=cloudinary). */
class UploadService
{
    public static function store(UploadedFile $file, string $folder = 'projects'): string
    {
        return config('services.upload.provider') === 'cloudinary'
            ? self::cloudinary($file, $folder)
            : Storage::url($file->store($folder, 'public'));
    }

    private static function cloudinary(UploadedFile $file, string $folder): string
    {
        // CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
        $c = parse_url((string) config('services.upload.cloudinary_url'));
        abort_unless(! empty($c['host']) && ! empty($c['user']) && ! empty($c['pass']), 500, 'CLOUDINARY_URL invalide.');

        $params = ['folder' => $folder, 'timestamp' => time()];
        ksort($params);
        $toSign = urldecode(http_build_query($params));
        $signature = sha1($toSign.$c['pass']);

        $res = Http::asMultipart()
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$c['host']}/image/upload", [
                ...$params, 'api_key' => $c['user'], 'signature' => $signature,
            ])->throw()->json();

        return $res['secure_url'];
    }
}
