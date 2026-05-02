# Google Agent Platform PHP SDK

A lightweight, dependency-free PHP wrapper for the Google Gemini Enterprise Agent Platform. 

## Quick Start

This SDK supports **Express Mode** (API Keys) and **Google Cloud Mode** (OAuth Bearer Tokens). It automatically defaults to the state-of-the-art `gemini-3.1-flash-lite-preview` model if no model is specified.

### 1. Using an API Key (Express Mode)

```php
require 'vendor/autoload.php';

use GoogleAgentPlatform\Client;

$client = new Client([
    'api_key' => 'YOUR_API_KEY'
]);

// Uses the default 'gemini-3.1-flash-lite-preview' model
$response = $client->generateContent([
    [
        'role' => 'user',
        'parts' => [
            ['text' => 'How does AI work?']
        ]
    ]
]);

print_r($response);
```

### 2. Using Project ID & Access Token (Google Cloud Mode)

```php
// Tip: Run `gcloud auth application-default print-access-token` locally to test
$client = new Client([
    'project_id'   => 'YOUR_PROJECT_ID',
    'access_token' => 'YOUR_ACCESS_TOKEN', 
    'location'     => 'global' // Defaults to 'global'
]);
```

### 3. Streaming Responses (e.g., Gemini 3.1 Pro Preview)

To stream a response or use a specific model like `gemini-3.1-pro-preview`, pass the model ID as the second parameter:

```php
$response = $client->streamGenerateContent(
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
    ],
    'gemini-3.1-pro-preview' // Override default model here
);
```

### 4. Text-to-Speech (TTS) Integration

Google Agent Platform now acts as a hub for multiple publishers. The SDK automatically resolves third-party providers (like ElevenLabs) if you prefix the publisher name.

**Available TTS Models:**
* `gemini-3.1-flash-tts-preview`
* `gemini-2.5-pro-tts`
* `gemini-2.5-flash-tts`
* `elevenlabs/elevenlabs-tts-v2-5` *(Third-Party)*

**Example (Using ElevenLabs via Google Agent Platform):**

```php
$ttsPayload = [
    // Consult the specific model documentation for the exact payload schema
    "text" => "Hello, welcome to the Agent Platform." 
];

// Provide the publisher prefix (elevenlabs/) to route correctly
$audioResponse = $client->predict(
    $ttsPayload, 
    'elevenlabs/elevenlabs-tts-v2-5'
);
```
