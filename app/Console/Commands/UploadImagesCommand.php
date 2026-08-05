<?php

namespace App\Console\Commands;

use App\Services\JsFileService;
use App\Services\ShopifyService;
use App\Services\UrlMappingService;
use Illuminate\Console\Command;

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
        $startTime = microtime(true);

        $js = app(JsFileService::class);
        $shopify = app(ShopifyService::class);
        $mapping = app(UrlMappingService::class);

        $content = $js->read();
        $urls = $js->extractImageUrls($content);

        $total = count($urls);

        $shopifyAlready = 0;
        $uploaded = 0;
        $failed = 0;
        $skipped = 0;

        $failedImages = [];

        /*
        |--------------------------------------------------------------------------
        | Count Images
        |--------------------------------------------------------------------------
        */

        foreach ($urls as $url) {

            if ($shopify->isShopifyUrl($url)) {
                $shopifyAlready++;
                $skipped++;
            }

        }

        $needUpload = $total - $skipped;

        $this->info("==================================================");
        $this->info("Shopify Image Upload");
        $this->info("==================================================");

        $this->line("Total Images      : {$total}");
        $this->line("Already Shopify   : {$shopifyAlready}");
        $this->line("Need Upload       : {$needUpload}");

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Upload Images
        |--------------------------------------------------------------------------
        */

        $current = 1;

        foreach ($urls as $url) {

            if ($shopify->isShopifyUrl($url)) {
                continue;
            }

            $this->info("==================================================");
            $this->info("[{$current}/{$needUpload}]");
            $this->info("==================================================");

            try {

                $this->info("Processing:");
                $this->line($url);

                /*
                |--------------------------------------------------------------------------
                | Download
                |--------------------------------------------------------------------------
                */

                $localPath = $shopify->downloadImage($url);

                $this->info("Downloaded:");
                $this->line($localPath);

                /*
                |--------------------------------------------------------------------------
                | Upload
                |--------------------------------------------------------------------------
                */

                $this->info("Uploading to Shopify...");

                $cdnUrl = $shopify->uploadLocalImage($localPath);

                $uploaded++;

                /*
                |--------------------------------------------------------------------------
                | Save Mapping
                |--------------------------------------------------------------------------
                */

                $mapping->add($url, $cdnUrl);

                $this->info("Shopify URL:");
                $this->line($cdnUrl);

                /*
                |--------------------------------------------------------------------------
                | Delete Temp File
                |--------------------------------------------------------------------------
                */

                if (file_exists($localPath)) {
                    unlink($localPath);
                }

                $this->info("✔ Upload Complete");

            } catch (\Throwable $e) {

                $failed++;

                $failedImages[] = [
                    'url' => $url,
                    'reason' => $e->getMessage(),
                ];

                $this->error("Upload Failed");
                $this->error($url);
                $this->error($e->getMessage());
            }

            $this->newLine();

            $current++;
        }

        /*
        |--------------------------------------------------------------------------
        | Save Files
        |--------------------------------------------------------------------------
        */

        $mapping->save();

        $updatedContent = $js->replaceUrls(
            $content,
            $mapping->all()
        );

        $js->save($updatedContent);

        /*
        |--------------------------------------------------------------------------
        | Execution Time
        |--------------------------------------------------------------------------
        */

        $executionTime = round(microtime(true) - $startTime, 2);

        /*
        |--------------------------------------------------------------------------
        | Final Summary
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info("==================================================");
        $this->info("Shopify Image Upload Summary");
        $this->info("==================================================");

        $this->line("Total Images          : {$total}");
        $this->line("Already Shopify       : {$shopifyAlready}");
        $this->line("Uploaded Successfully : {$uploaded}");
        $this->line("Failed                : {$failed}");
        $this->line("Skipped               : {$skipped}");

        $this->newLine();

        $this->line("Execution Time        : {$executionTime} sec");

        $this->newLine();

        $this->info("Output Files");

        $this->line("✔ " . storage_path('app/output/url-map.json'));
        $this->line("✔ " . storage_path('app/output/updated-nt-sign-data.js'));

        $this->newLine();

        $this->info("==================================================");

        if ($failed === 0) {
            $this->info("✔ Process Completed Successfully");
        } else {
            $this->warn("⚠ Process Completed With Errors");
        }

        $this->info("==================================================");

        /*
        |--------------------------------------------------------------------------
        | Failed Images
        |--------------------------------------------------------------------------
        */

        if (!empty($failedImages)) {

            $this->newLine();

            $this->error("Failed Images");

            foreach ($failedImages as $index => $image) {

                $this->line(($index + 1) . ".");

                $this->line($image['url']);

                $this->line("Reason:");

                $this->error($image['reason']);

                $this->line("--------------------------------------------");
            }
        }

        return self::SUCCESS;
    }
}