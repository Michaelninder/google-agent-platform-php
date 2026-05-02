# Google Agent Platform PHP SDK

A lightweight, dependency-free PHP wrapper for the **Google Agent Platform** (formerly known as **Vertex AI**). Google rebranded Vertex AI to "Agent Platform" to reflect its expanded focus on agentic AI workflows. All existing Vertex AI endpoints remain fully compatible.

> **API Keys:** Manage your API keys at [https://console.cloud.google.com/agent-platform/studio/settings/api-keys](https://console.cloud.google.com/agent-platform/studio/settings/api-keys)

---

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
    'location'     => 'us-east5' // Required for Anthropic & Veo models
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

---

## Image Generation — Imagen 3

Generate images from text prompts using Imagen 3. The API returns base64-encoded PNG/JPEG bytes — the SDK decodes them automatically and can save directly to disk.

### Available Imagen Models

| Model ID (for SDK) | Notes |
|---|---|
| `imagen-3.0-generate-001` | Highest quality (default) |
| `imagen-3.0-fast-generate-001` | Faster, lower cost |

### Generate and Save Images

```php
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-central1'
]);

$images = $client->generateImage(
    prompt:      'A photorealistic red fox sitting in a snowy forest at dusk.',
    modelId:     'imagen-3.0-generate-001',
    sampleCount: 2,
    aspectRatio: '16:9',
    outputDir:   '/tmp/images'   // saves imagen_<uniqid>.png to this folder
);

foreach ($images as $img) {
    echo $img['savedPath'] . PHP_EOL; // e.g. /tmp/images/imagen_6634ab12.png
}
```

### Return as Base64 (no file saving)

```php
$images = $client->generateImage(
    prompt:      'An oil painting of a lighthouse at sunset.',
    sampleCount: 1
    // omit outputDir → base64 string returned instead
);

$base64png = $images[0]['base64'];
// e.g. embed in HTML: <img src="data:image/png;base64,{$base64png}">
```

### Advanced Imagen Parameters

```php
$images = $client->generateImage(
    prompt:           'A futuristic city skyline at night.',
    modelId:          'imagen-3.0-generate-001',
    sampleCount:      1,
    aspectRatio:      '9:16',
    outputDir:        '/tmp/images',
    additionalParams: [
        'negativePrompt'    => 'blurry, low quality, cartoon',
        'personGeneration'  => 'dont_allow',
    ]
);
```

---

## Text-to-Speech (TTS)

TTS models return binary audio data. The SDK handles both raw binary responses and base64-wrapped JSON envelopes automatically, and can save the result directly to a file.

### Available TTS Models

| Model ID (for SDK) | Description |
|---|---|
| `gemini-3.1-flash-tts-preview` | **Default** — low latency, style control via prompts |
| `gemini-2.5-pro-tts` | High-quality TTS |
| `gemini-2.5-flash-tts` | Fast TTS |
| `elevenlabs/elevenlabs-tts-v2-5` | Third-party ElevenLabs |

**Gemini 3.1 Flash TTS Preview** supports:
- Natural conversation with very low latency
- Style control via natural language prompts (accents, tone, whisper, emotions)
- Dynamic performance for poetry, newscasts, storytelling
- Enhanced pace and pronunciation control

### Synthesize and Save to File

```php
$client = new Client([
    'api_key' => 'YOUR_API_KEY'
]);

$audio = $client->synthesizeSpeech(
    text:        'Hello, welcome to the Agent Platform.',
    modelId:     'gemini-3.1-flash-tts-preview',
    voiceConfig: ['prebuiltVoiceConfig' => ['voiceName' => 'en-US-Standard-A']],
    stylePrompt: 'Speak in a calm, friendly tone with a slight British accent.',
    outputFile:  '/tmp/speech.mp3'
);

echo $audio['savedPath'];   // /tmp/speech.mp3
echo $audio['mimeType'];    // audio/mp3
```

### Return Raw Audio Bytes (no file saving)

```php
$audio = $client->synthesizeSpeech(
    text:    'The quick brown fox jumps over the lazy dog.',
    modelId: 'gemini-2.5-flash-tts'
    // omit outputFile → raw bytes returned in $audio['bytes']
);

// Stream directly to browser
header('Content-Type: ' . $audio['mimeType']);
echo $audio['bytes'];
```

### ElevenLabs via Agent Platform

```php
$audio = $client->synthesizeSpeech(
    text:    'Hello from ElevenLabs on Agent Platform.',
    modelId: 'elevenlabs/elevenlabs-tts-v2-5',
    extra:   ['voice_id' => 'YOUR_VOICE_ID']
);
```

---

## Anthropic Models (Claude on Agent Platform)

Google Agent Platform hosts Anthropic's Claude models. The API differs slightly from the direct Anthropic API:

- `model` is **not** a valid parameter — the model is specified in the endpoint URL.
- `anthropic_version` is **required** and must be set to `vertex-2023-10-16`.

Use `claudeMessages()` for a clean interface, or `predict()` with a raw payload.

### Available Claude Models

| Model ID (for SDK) | Full Publisher Path |
|---|---|
| `anthropic/claude-sonnet-4-6` | `publishers/anthropic/models/claude-sonnet-4-6` |
| `anthropic/claude-opus-4-6` | `publishers/anthropic/models/claude-opus-4-6` |

### Claude Sonnet 4.6 — Example

```php
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-east5'
]);

$response = $client->claudeMessages(
    messages: [
        ['role' => 'user', 'content' => 'Give me a banana bread recipe.']
    ],
    modelId: 'anthropic/claude-sonnet-4-6',
    maxTokens: 1024
);

// $response['content'][0]['text'] contains the reply
print_r($response);
```

Expected response shape:

```json
{
  "id": "msg_01AbCdEfGhIjKlMnOpQrStUv",
  "type": "message",
  "role": "assistant",
  "content": [
    {
      "type": "text",
      "text": "Here's a delicious banana bread recipe: ..."
    }
  ],
  "model": "claude-sonnet-4-6",
  "usage": {
    "input_tokens": 12,
    "output_tokens": 184
  }
}
```

### Claude Opus 4.6 — Example

```php
$response = $client->claudeMessages(
    messages: [
        ['role' => 'user', 'content' => 'Explain quantum entanglement simply.']
    ],
    modelId: 'anthropic/claude-opus-4-6',
    maxTokens: 2048
);
```

### Streaming Claude Responses

```php
$response = $client->claudeMessages(
    messages: [
        ['role' => 'user', 'content' => 'Write a short story.']
    ],
    modelId: 'anthropic/claude-sonnet-4-6',
    maxTokens: 1024,
    stream: true
);
```

---

## Video Generation — Veo 3.1

Veo 3.1 uses a **long-running operation** pattern: you submit a job and then poll until it completes.

### Available Veo Models

| Model ID (for SDK) | Full Publisher Path |
|---|---|
| `google/veo-3.1-generate-001` | `publishers/google/models/veo-3.1-generate-001` |

### Generate a Video

```php
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-central1'
]);

// Step 1: Submit the generation job
$operation = $client->generateVideo(
    prompt: 'A timelapse of a sunflower blooming in a garden.',
    modelId: 'google/veo-3.1-generate-001',
    sampleCount: 1,
    outputStorageUri: 'gs://your-bucket/output/' // optional
);

$operationName = $operation['name']; // e.g. "projects/.../operations/123"

// Step 2: Poll until done
do {
    sleep(5);
    $status = $client->getOperation($operationName);
} while (empty($status['done']));

print_r($status['response']);
```

### Advanced Veo Parameters

```php
$operation = $client->generateVideo(
    prompt: 'Aerial drone shot of a mountain range at sunrise.',
    modelId: 'google/veo-3.1-generate-001',
    sampleCount: 2,
    outputStorageUri: 'gs://your-bucket/output/',
    additionalParams: [
        'generateAudio' => true,
        'durationSeconds' => 8
    ]
);
```

---

## Text-to-Speech (TTS)

Google Agent Platform acts as a hub for multiple publishers. The SDK automatically routes to the correct publisher endpoint.

### Available TTS Models

| Model ID (for SDK) | Description |
|---|---|
| `gemini-3.1-flash-tts-preview` | Low-latency, controllable single- and multi-speaker TTS. Supports style control via natural language prompts (accents, tone, whisper, emotions). |
| `gemini-2.5-pro-tts` | High-quality TTS via Gemini 2.5 Pro |
| `gemini-2.5-flash-tts` | Fast TTS via Gemini 2.5 Flash |
| `elevenlabs/elevenlabs-tts-v2-5` | Third-party ElevenLabs TTS |

**Gemini 3.1 Flash TTS Preview** supports:
- Natural conversation with very low latency
- Style control via prompts (accents, tone, whisper, emotions)
- Dynamic performance for poetry, newscasts, storytelling
- Enhanced pace and pronunciation control

### TTS Example

```php
$audioResponse = $client->predict(
    payload: [
        'text'         => 'Hello, welcome to the Agent Platform.',
        'voiceConfig'  => ['prebuiltVoiceConfig' => ['voiceName' => 'en-US-Standard-A']],
        'stylePrompt'  => 'Speak in a calm, friendly tone with a slight British accent.'
    ],
    modelId: 'gemini-3.1-flash-tts-preview'
);
```

### ElevenLabs via Agent Platform

```php
$audioResponse = $client->predict(
    payload: [
        'text' => 'Hello, welcome to the Agent Platform.'
    ],
    modelId: 'elevenlabs/elevenlabs-tts-v2-5'
);
```

---

## Model Reference

| Model ID (for SDK) | Type | Method | Notes |
|---|---|---|---|
| `gemini-3.1-flash-lite-preview` | Text | `generateContent()` | **Default model** |
| `gemini-3.1-pro-preview` | Text | `generateContent()` | High capability |
| `imagen-3.0-generate-001` | Image | `generateImage()` | Highest quality |
| `imagen-3.0-fast-generate-001` | Image | `generateImage()` | Faster, lower cost |
| `gemini-3.1-flash-tts-preview` | TTS | `synthesizeSpeech()` | Low-latency, style control |
| `gemini-2.5-pro-tts` | TTS | `synthesizeSpeech()` | High quality |
| `gemini-2.5-flash-tts` | TTS | `synthesizeSpeech()` | Fast |
| `anthropic/claude-sonnet-4-6` | Text | `claudeMessages()` | Requires Cloud Mode |
| `anthropic/claude-opus-4-6` | Text | `claudeMessages()` | Requires Cloud Mode |
| `google/veo-3.1-generate-001` | Video | `generateVideo()` | Long-running operation |
| `elevenlabs/elevenlabs-tts-v2-5` | TTS | `synthesizeSpeech()` | Third-party |

---

## About Google Agent Platform (formerly Vertex AI)

Google Agent Platform was formerly known as **Vertex AI**. The rebrand reflects Google's strategic shift toward agentic AI — systems that can plan, reason, and act autonomously. The underlying infrastructure and all Vertex AI API endpoints remain fully compatible. If you have existing code using Vertex AI endpoints, it will continue to work without changes.
