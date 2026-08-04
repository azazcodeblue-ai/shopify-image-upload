<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class UrlMappingService
{
    protected array $map = [];

    public function add(string $oldUrl, string $newUrl): void
    {
        $this->map[$oldUrl] = $newUrl;
    }

    public function all(): array
    {
        return $this->map;
    }

    public function save(): void
    {
        $directory = storage_path('app/output');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put(
            $directory . '/url-map.json',
            json_encode($this->map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}