<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileBase64Service
{
    /**
     * Encode file dari storage ke string Base64 data URI
     *
     * @param  string $path Path di storage (mis. 'public/foto/123.jpg')
     * @return string|null 'data:{mime};base64,{content}' atau null jika tidak ada
     */
    public static function encode(string $path): ?string
    {
        if (!Storage::exists($path)) {
            return null;
        }

        $raw       = Storage::get($path);
        $mimeType  = Storage::mimeType($path);
        $base64    = base64_encode($raw);

        return "data:{$mimeType};base64,{$base64}";
    }
}
