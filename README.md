# Google Agent Platform PHP SDK

A lightweight, dependency-free PHP wrapper for the Google Gemini Enterprise Agent Platform (formerly Vertex AI). 

## Quick Start

This SDK supports both **API Key** routing and **Google Cloud OAuth (Bearer Token)** routing. It automatically formats the correct endpoint based on your configuration.

### 1. Using an API Key

```php
require 'vendor/autoload.php';

use GoogleAgentPlatform\Client;

$client = new Client([
    'api_key' => 'YOUR_API_KEY'
]);

$response = $client->generateContent('gemini-2.5-flash', [
    [
        'role' => 'user',
        'parts' => [
            ['text' => 'How does AI work?']
        ]
    ]
]);

print_r($response);
```

### 2. Using Google Cloud Project ID & Access Token

```php
require 'vendor/autoload.php';

use GoogleAgentPlatform\Client;

// Tip: You can get your token locally by running `gcloud auth print-access-token`
$client = new Client([
    'project_id'   => 'YOUR_PROJECT_ID',
    'access_token' => 'YOUR_ACCESS_TOKEN', 
    'location'     => 'global' // Optional: defaults to 'global'
]);

$response = $client->generateContent('gemini-2.5-flash', [
    [
        'role' => 'user',
        'parts' => [
            ['text' => 'How does AI work?']
        ]
    ]
]);
```

### 3. Multi-Modal Requests & Streaming Enpoints

You can easily pass image URIs (from Google Cloud Storage) and target the streaming endpoints using the exact same structure.

```php
$response = $client->streamGenerateContent('gemini-3.1-flash-lite-preview', [
    [
        'role' => 'user',
        'parts' => [
            [
                'fileData' => [
                    'mimeType' => 'image/png',
                    'fileUri'  => 'gs://generativeai-downloads/images/scones.jpg'
                ]
            ],
            [
                'text' => 'Describe this picture.'
            ]
        ]
    ]
]);
```
