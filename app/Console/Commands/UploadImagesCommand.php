<?php

namespace App\Console\Commands;

use App\Services\JsFileService;
use App\Services\ShopifyService;
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
    $js = app(JsFileService::class);

    $shopify = app(\App\Services\ShopifyService::class);

    $content = $js->read();

    $urls = $js->extractImageUrls($content);

    $total = count($urls);
    $shopifyAlready = 0;
    $external = 0;

    foreach ($urls as $url) {

        if ($shopify->isShopifyUrl($url)) {
            $shopifyAlready++;
        } else {
            $external++;
        }

    }

    $this->info("Total Images      : {$total}");
    $this->info("Already Shopify   : {$shopifyAlready}");
    $this->info("Need Upload       : {$external}");

    foreach ($urls as $url) {

    // Skip Shopify URLs
    if ($shopify->isShopifyUrl($url)) {
        continue;
    }

    $this->info("Processing:");
    $this->line($url);

    // Download image
   $localPath = $shopify->downloadImage($url);

$this->info("Downloaded:");
$this->line($localPath);

$this->info("Uploading to Shopify...");

$cdnUrl = $shopify->uploadLocalImage($localPath);

$this->info("Shopify CDN URL:");

$this->line($cdnUrl);

    }

    return self::SUCCESS;
}
}
