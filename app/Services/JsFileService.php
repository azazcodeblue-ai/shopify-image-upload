<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class JsFileService
{
   public function read(): string
{
    $path = config('image-upload.input_file');

    if (! File::exists($path)) {
        throw new \Exception("File not found: {$path}");
    }

    return File::get($path);
}

    public function extractImageUrls(string $content): array
    {
        preg_match_all(
            '/https?:\/\/[^\s"\']+\.(jpg|jpeg|png|webp)/i',
            $content,
            $matches
        );

        return array_unique($matches[0]);
    }
}