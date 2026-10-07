<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class MediaUrl
{
    public static function for(Request $request, string $disk, ?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $url = $storage->url($path);
        $config = config("filesystems.disks.{$disk}");

        if (($config['driver'] ?? null) === 'local') {
            return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        }

        return $url;
    }
}
