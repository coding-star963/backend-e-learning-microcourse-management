<?php

namespace App\Support;

class MediaUrl
{
    public static function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = preg_replace('#^/?(?:storage|api/media)/#', '', $path);

        return url('api/media/' . ltrim($path, '/'));
    }
}
