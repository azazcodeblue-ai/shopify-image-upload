<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class UrlMappingService
{
    /**
     * Store old URL => new Shopify URL.
     */
    protected array $map = [];

    /**
     * Add a new mapping.
     */
    public function add(string $oldUrl, string $newUrl): void
    {
        $this->map[$oldUrl] = $newUrl;
    }

    /**
     * Get all mappings.
     */
    public function all(): array
    {
        return $this->map;
    }

    /**
     * Check if a mapping exists.
     */
    public function has(string $oldUrl): bool
    {
        return array_key_exists($oldUrl, $this->map);
    }

    /**
     * Get the Shopify URL for an old URL.
     */
    public function get(string $oldUrl): ?string
    {
        return $this->map[$oldUrl] ?? null;
    }

    /**
     * Total mappings.
     */
    public function count(): int
    {
        return count($this->map);
    }

    /**
     * Save mappings as JSON.
     */
    public function save(): void
    {
        $directory = storage_path('app/output');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put(
            $directory . '/url-map.json',
            json_encode(
                $this->map,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /**
     * Clear all mappings.
     */
    public function clear(): void
    {
        $this->map = [];
    }
}