<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UploadImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'shopify:upload-images';

    /**
     * The console command description.
     */
    protected $description = 'Upload all images to Shopify Files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $folder = storage_path('app/images');

        // Check if folder exists
        if (!File::exists($folder)) {
            $this->error("Folder not found:");
            $this->line($folder);

            return self::FAILURE;
        }

        // Get all files
        $files = File::files($folder);

        if (empty($files)) {
            $this->warn("No images found.");

            return self::SUCCESS;
        }

        $this->info("Images Found:");
        $this->newLine();

        foreach ($files as $file) {

            $this->line(
                $file->getFilename()
            );

        }

        return self::SUCCESS;
    }
}