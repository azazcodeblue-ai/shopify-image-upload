<?php

namespace App\Console\Commands;

use App\Services\JsFileService;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use App\Services\UrlMappingService;

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
    $js = app(JsFileService::class);
    $shopify = app(ShopifyService::class);
    $mapping = app(UrlMappingService::class);

    $content = $js->read();
    $urls = $js->extractImageUrls($content);

    $total = count($urls);

    $shopifyAlready = 0;
    $external = 0;

    // Count images
    foreach ($urls as $url) {
        if ($shopify->isShopifyUrl($url)) {
            $shopifyAlready++;
        } else {
            $external++;
        }
    }

    $this->info("=================================");
    $this->info("Total Images      : {$total}");
    $this->info("Already Shopify   : {$shopifyAlready}");
    $this->info("Need Upload       : {$external}");
    $this->info("=================================");
    $this->newLine();

    // Upload only external images
    foreach ($urls as $url) {

        if ($shopify->isShopifyUrl($url)) {
            continue;
        }

        try {

            $this->info("Processing:");
            $this->line($url);

            // Download
            $localPath = $shopify->downloadImage($url);

            $this->info("Downloaded:");
            $this->line($localPath);

            // Upload
            $this->info("Uploading to Shopify...");

            $cdnUrl = $shopify->uploadLocalImage($localPath);

            // Save mapping
            $mapping->add($url, $cdnUrl);

            $this->info("Shopify URL:");
            $this->line($cdnUrl);

            // Delete temp file
            if (file_exists($localPath)) {
                unlink($localPath);
            }

            $this->info("✔ Upload Complete");
            $this->newLine();

        } catch (\Throwable $e) {

            $this->error("Upload Failed");
            $this->error($url);
            $this->error($e->getMessage());
            $this->newLine();
        }
    }

    // Save mapping JSON
    $mapping->save();

    // Replace URLs inside JS
    $updatedContent = $js->replaceUrls(
        $content,
        $mapping->all()
    );

    // Save updated JS file
    $js->save($updatedContent);

    $this->info("=================================");
    $this->info("URL mapping saved:");
    $this->line(storage_path('app/output/url-map.json'));
    $this->newLine();

    $this->info("Updated JavaScript saved.");
    $this->line(storage_path('app/output/updated-nt-sign-data.js'));

    $this->info("=================================");
    $this->info("Process Completed Successfully.");
    $this->info("=================================");

    return self::SUCCESS;
}

}
