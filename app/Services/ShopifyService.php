<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyService
{
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

    // Only include variables if they exist
    if (!empty($variables)) {
        $payload['variables'] = $variables;
    }

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => config('shopify.access_token'),
        'Content-Type' => 'application/json',
    ])->post($url, $payload);

    return $response->json();
}

    public function testConnection()
    {
        $query = <<<GRAPHQL
        {
            shop {
                name
            }
        }
        GRAPHQL;

        return $this->graphQL($query);
    }public function createStagedUpload(
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
        'input' => [
            [
                'filename' => $filename,
                'mimeType' => $mimeType,
                'resource' => 'FILE',
                'fileSize' => (string) $fileSize,
                'httpMethod' => 'POST',
            ]
        ]
    ];

    return $this->graphQL($query, $variables);
}
public function uploadBinary(array $stagedTarget, string $filePath): bool
{
    $request = Http::asMultipart();

    // Add all parameters returned by Shopify
    foreach ($stagedTarget['parameters'] as $parameter) {
        $request->attach(
            $parameter['name'],
            $parameter['value']
        );
    }

    // Attach the actual file
    $request->attach(
        'file',
        fopen($filePath, 'r'),
        basename($filePath)
    );

    $response = $request->post($stagedTarget['url']);

    return $response->successful() || $response->status() == 201;
}
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
        'files' => [
            [
                'originalSource' => $resourceUrl,
                'contentType' => 'IMAGE',
            ]
        ]
    ];

    return $this->graphQL($query, $variables);
}
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
}