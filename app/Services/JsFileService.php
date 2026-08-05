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

    public function replaceUrls(string $content, array $mapping): string
{
    return str_replace(
        array_keys($mapping),
        array_values($mapping),
        $content
    );
}

public function save(string $content): void
{
    $output = config('image-upload.output_file');

    File::put($output, $content);
}

/**
 * Save the updated JavaScript file.
 */
public function saveOutput(string $content): string
{
    $directory = storage_path('app/output');

    if (! File::exists($directory)) {
        File::makeDirectory($directory, 0755, true);
    }

    $path = $directory . DIRECTORY_SEPARATOR . 'nt-sign-data.js';

    File::put($path, $content);

    return $path;
}
}