<?php

use Illuminate\Support\Facades\Route;
use App\Services\ShopifyService;

Route::get('/shopify/upload-test', function (ShopifyService $shopify) {

    $filePath = storage_path('app/test/sample.webp');

    // Step 1: Create staged upload
    $staged = $shopify->createStagedUpload(
        'sample.webp',
        'image/webp',
        filesize($filePath)
    );

    $target = $staged['data']['stagedUploadsCreate']['stagedTargets'][0];

    // Step 2: Upload binary to Shopify storage
    $uploaded = $shopify->uploadBinary($target, $filePath);

    if (!$uploaded) {
        return response()->json([
            'success' => false,
            'message' => 'Binary upload failed.'
        ]);
    }

    // Step 3: Create the Shopify File
    $file = $shopify->createFile($target['resourceUrl']);

    // Check for user errors
    if (!empty($file['data']['fileCreate']['userErrors'])) {
        return response()->json($file);
    }

    // Get the file ID
    $id = $file['data']['fileCreate']['files'][0]['id'];

    // Give Shopify a moment to process the image
    sleep(2);

    // Step 4: Fetch the final file details
    return response()->json(
        $shopify->getFile($id)
    );
});