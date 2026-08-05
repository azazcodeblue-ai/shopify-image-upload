<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class ShopifyService
{
    /**
     * Send GraphQL request to Shopify
     */
    private function graphQL(string $query, array $variables = []): array
    {
        $url = sprintf(
            'https://%s/admin/api/%s/graphql.json',
            config('shopify.store'),
            config('shopify.api_version')
        );

        $payload = [
            'query' => $query,
        ];

        if (!empty($variables)) {
            $payload['variables'] = $variables;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => config('shopify.access_token'),
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $response->json();
    }

    /**
     * Test Shopify Connection
     */
    public function testConnection(): array
    {
        $query = <<<GRAPHQL
{
    shop {
        name
    }
}
GRAPHQL;

        return $this->graphQL($query);
    }

    /**
     * Create staged upload
     */
    public function createStagedUpload(
        string $filename,
        string $mimeType,
        int $fileSize
    ): array {

        $query = <<<'GRAPHQL'
mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
  stagedUploadsCreate(input: $input) {
    stagedTargets {
      url
      resourceUrl
      parameters {
        name
        value
      }
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [[
                'filename' => $filename,
                'mimeType' => $mimeType,
                'resource' => 'FILE',
                'fileSize' => (string) $fileSize,
                'httpMethod' => 'POST',
            ]]
        ];

        return $this->graphQL($query, $variables);
    }

    /**
     * Upload binary to Shopify
     */
    public function uploadBinary(array $stagedTarget, string $filePath): bool
    {
        $request = Http::asMultipart();

        foreach ($stagedTarget['parameters'] as $parameter) {
            $request->attach(
                $parameter['name'],
                $parameter['value']
            );
        }

        $request->attach(
            'file',
            fopen($filePath, 'r'),
            basename($filePath)
        );

        $response = $request->post($stagedTarget['url']);

        return $response->successful() || $response->status() == 201;
    }

    /**
     * Create Shopify File
     */
    public function createFile(string $resourceUrl): array
    {
        $query = <<<'GRAPHQL'
mutation fileCreate($files: [FileCreateInput!]!) {
  fileCreate(files: $files) {
    files {
      id
      fileStatus

      ... on MediaImage {
        image {
          url
        }
      }
    }

    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $variables = [
            'files' => [[
                'originalSource' => $resourceUrl,
                'contentType' => 'IMAGE',
            ]]
        ];

        return $this->graphQL($query, $variables);
    }

    /**
     * Get uploaded file
     */
    public function getFile(string $id): array
    {
        $query = <<<'GRAPHQL'
query getFile($id: ID!) {
  node(id: $id) {
    ... on MediaImage {
      id
      fileStatus

      image {
        url
        width
        height
      }
    }
  }
}
GRAPHQL;

        return $this->graphQL($query, [
            'id' => $id,
        ]);
    }

    /**
     * Download image from URL
     */
    public function downloadImage(string $url): string
    {
        $directory = config('image-upload.temp_directory');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $response = Http::timeout(60)->get($url);

        if (!$response->successful()) {
            throw new \Exception("Unable to download image: {$url}");
        }

        File::put($path, $response->body());

        return $path;
    }

    public function isShopifyUrl(string $url): bool
    {
        return str_contains($url, 'cdn.shopify.com');
    }

    public function uploadLocalImage(string $filePath): string
{
    // 1. Create staged upload
    $staged = $this->createStagedUpload(
        basename($filePath),
        mime_content_type($filePath),
        filesize($filePath)
    );

    $target = $staged['data']['stagedUploadsCreate']['stagedTargets'][0];

    // 2. Upload binary
if (! $this->uploadBinary($target, $filePath)) {
    throw new \Exception('Binary upload failed.');
}
    // 3. Register file in Shopify
    $file = $this->createFile($target['resourceUrl']);

    $id = $file['data']['fileCreate']['files'][0]['id'];

    // 4. Wait until Shopify processes it
   for ($i = 0; $i < 10; $i++) {

    sleep(2);

    $result = $this->getFile($id);

    if (
        isset($result['data']['node']['fileStatus']) &&
        $result['data']['node']['fileStatus'] === 'READY'
    ) {
        return $result['data']['node']['image']['url'];
    }
}

throw new \Exception('Shopify did not finish processing the image.');
}
};