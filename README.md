# Google Agent Platform PHP SDK

A lightweight, dependency-free PHP wrapper for the **Google Agent Platform** (formerly known as **Vertex AI**). Google rebranded Vertex AI to "Agent Platform" to reflect its expanded focus on agentic AI workflows. All existing Vertex AI endpoints remain fully compatible.

> **API Keys:** Manage your API keys at [https://console.cloud.google.com/agent-platform/studio/settings/api-keys](https://console.cloud.google.com/agent-platform/studio/settings/api-keys)

---

## Installation

```bash
composer require fabianternis/google-agent-platform-php
```

Requires PHP 8.1+, `ext-curl`, `ext-json`, `ext-fileinfo`.

---

## Quick Start

```php
require 'vendor/autoload.php';

use GoogleAgentPlatform\Client;

// Express Mode — API key
$client = new Client(['api_key' => 'YOUR_API_KEY']);

// Google Cloud Mode — OAuth token (required for Claude, Veo, Imagen, File API)
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-central1',
]);
```

> **Tip:** Run `gcloud auth application-default print-access-token` locally to get a token.

---

## Architecture

As of v0.5.0 the SDK uses a resource-based structure. Each capability lives in its own class, accessible as a property on `Client`. The legacy flat methods are still fully supported.

```
src/
├── Client.php                  ← main entry point (facade)
├── Http/
│   └── HttpClient.php          ← all cURL logic
├── Resources/
│   ├── TextResource.php        ← Gemini text generation
│   ├── ImageResource.php       ← Imagen image generation
│   ├── AudioResource.php       ← Text-to-Speech
│   ├── VideoResource.php       ← Veo video generation
│   ├── ClaudeResource.php      ← Anthropic Claude
│   └── FileResource.php        ← local file embed + File API upload
└── Support/
    └── MimeTypes.php           ← MIME detection and extension mapping
```

### Resource API (recommended)

```php
$client->text->generate([...]);
$client->images->generate('A red fox in the snow');
$client->audio->synthesize('Hello world');
$client->video->generate('A timelapse of a blooming flower');
$client->claude->messages([...]);
$client->files->withFile('/tmp/photo.jpg', 'What is in this image?');
$client->files->uploadFile('/tmp/large-video.mp4');
```

### Legacy flat API (fully backward-compatible)

```php
$client->generateContent([...]);
$client->generateImage('A red fox...');
$client->synthesizeSpeech('Hello world');
$client->generateVideo('A timelapse...');
$client->claudeMessages([...]);
```

---

## Sending Files to Models

Two strategies depending on file size and reuse needs.

### Strategy 1 — inlineData (local file, < ~20 MB)

Reads the file from disk, base64-encodes it, and embeds it directly in the request. Best for images, short audio clips, and small PDFs.

```php
// Single file
$contents = $client->files->withFile(
    filePath: '/tmp/photo.jpg',
    text:     'What is in this image?'
);
$response = $client->generateContent($contents);
echo $response['candidates'][0]['content']['parts'][0]['text'];
```

```php
// Multiple files in one request
$contents = $client->files->withFiles(
    files: [
        '/tmp/chart.png',
        ['path' => '/tmp/report.pdf', 'mimeType' => 'application/pdf'],
    ],
    text: 'Summarize the report and explain the chart.'
);
$response = $client->generateContent($contents);
```

MIME type is auto-detected via `finfo` (magic bytes). You can override it:

```php
$contents = $client->files->withFile('/tmp/data.bin', 'Analyze this.', 'application/octet-stream');
```

### Strategy 2 — File API upload (any size, reusable for 48 h)

Uploads the file to Google's File API via a resumable upload. Returns a URI you can reference in any subsequent request. Best for large files (> 20 MB), videos, and files used across multiple requests.

```php
// Step 1: Upload once
$file = $client->files->uploadFile('/tmp/large-video.mp4');
// $file['uri']      → 'https://generativelanguage.googleapis.com/v1beta/files/abc123'
// $file['mimeType'] → 'video/mp4'
// $file['name']     → 'files/abc123'

// Step 2: Reference in any request
$contents = $client->files->fromUri(
    fileUri:  $file['uri'],
    mimeType: $file['mimeType'],
    text:     'Summarize this video.'
);
$response = $client->generateContent($contents);
```

```php
// Or build the fileData part manually (GCS URI also works)
$response = $client->generateContent([[
    'role'  => 'user',
    'parts' => [
        ['fileData' => ['mimeType' => 'video/mp4', 'fileUri' => $file['uri']]],
        ['text'     => 'What happens in this video?'],
    ],
]]);
```

```php
// List and inspect uploaded files
$list = $client->files->listFiles(pageSize: 20);
$meta = $client->files->getFile('files/abc123');
```

---

## Text Generation — Gemini

```php
$response = $client->text->generate([
    ['role' => 'user', 'parts' => [['text' => 'How does AI work?']]]
]);
echo $response['candidates'][0]['content']['parts'][0]['text'];

// Specific model
$response = $client->text->generate($contents, 'gemini-3.1-pro-preview');

// Streaming
$response = $client->text->stream($contents, 'gemini-3.1-pro-preview');
```

---

## Image Generation — Imagen 3

### Available Models

| Model ID | Notes |
|---|---|
| `imagen-3.0-generate-001` | Highest quality (default) |
| `imagen-3.0-fast-generate-001` | Faster, lower cost |

```php
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-central1',
]);

// Generate and save to disk
$images = $client->images->generate(
    prompt:      'A photorealistic red fox sitting in a snowy forest at dusk.',
    sampleCount: 2,
    aspectRatio: '16:9',
    outputDir:   '/tmp/images'
);
foreach ($images as $img) {
    echo $img['savedPath'] . PHP_EOL;
}

// Return as base64 (no file saving)
$images = $client->images->generate('An oil painting of a lighthouse at sunset.');
$base64 = $images[0]['base64'];
// <img src="data:image/png;base64,{$base64}">

// Advanced parameters
$images = $client->images->generate(
    prompt:           'A futuristic city skyline at night.',
    aspectRatio:      '9:16',
    outputDir:        '/tmp/images',
    additionalParams: [
        'negativePrompt'   => 'blurry, low quality, cartoon',
        'personGeneration' => 'dont_allow',
    ]
);
```

---

## Text-to-Speech (TTS)

### Available Models

| Model ID | Description |
|---|---|
| `gemini-3.1-flash-tts-preview` | **Default** — low latency, style control via prompts |
| `gemini-2.5-pro-tts` | High-quality TTS |
| `gemini-2.5-flash-tts` | Fast TTS |
| `elevenlabs/elevenlabs-tts-v2-5` | Third-party ElevenLabs |

**Gemini 3.1 Flash TTS Preview** supports style control via natural language prompts (accents, tone, whisper, emotions), dynamic performance for poetry/newscasts/storytelling, and enhanced pace and pronunciation control.

```php
// Synthesize and save to file
$audio = $client->audio->synthesize(
    text:        'Hello, welcome to the Agent Platform.',
    voiceConfig: ['prebuiltVoiceConfig' => ['voiceName' => 'en-US-Standard-A']],
    stylePrompt: 'Speak in a calm, friendly tone with a slight British accent.',
    outputFile:  '/tmp/speech.mp3'
);
echo $audio['savedPath'];  // /tmp/speech.mp3

// Return raw bytes (stream to browser)
$audio = $client->audio->synthesize('The quick brown fox.', 'gemini-2.5-flash-tts');
header('Content-Type: ' . $audio['mimeType']);
echo $audio['bytes'];

// ElevenLabs via Agent Platform
$audio = $client->audio->synthesize(
    text:    'Hello from ElevenLabs.',
    modelId: 'elevenlabs/elevenlabs-tts-v2-5',
    extra:   ['voice_id' => 'YOUR_VOICE_ID']
);
```

---

## Anthropic Claude

### Available Models

| Model ID | Full Publisher Path |
|---|---|
| `anthropic/claude-sonnet-4-6` | `publishers/anthropic/models/claude-sonnet-4-6` |
| `anthropic/claude-opus-4-6` | `publishers/anthropic/models/claude-opus-4-6` |

Requires Cloud Mode. Recommended location: `us-east5`.

Key differences from the direct Anthropic API:
- `model` is **not** a valid body parameter — it's part of the endpoint URL.
- `anthropic_version` is **required** and automatically set to `vertex-2023-10-16`.

```php
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-east5',
]);

$response = $client->claude->messages(
    messages:  [['role' => 'user', 'content' => 'Give me a banana bread recipe.']],
    modelId:   'anthropic/claude-sonnet-4-6',
    maxTokens: 1024
);
echo $response['content'][0]['text'];

// Streaming
$response = $client->claude->messages(
    messages:  [['role' => 'user', 'content' => 'Write a short story.']],
    maxTokens: 1024,
    stream:    true
);
```

---

## Video Generation — Veo 3.1

Veo uses a long-running operation pattern: submit a job, then poll until complete.

### Available Models

| Model ID | Full Publisher Path |
|---|---|
| `google/veo-3.1-generate-001` | `publishers/google/models/veo-3.1-generate-001` |

```php
$client = new Client([
    'project_id'   => 'your-gcp-project-id',
    'access_token' => 'YOUR_ACCESS_TOKEN',
    'location'     => 'us-central1',
]);

// Step 1: Submit
$operation = $client->video->generate(
    prompt:           'A timelapse of a sunflower blooming in a garden.',
    sampleCount:      1,
    outputStorageUri: 'gs://your-bucket/output/'  // optional
);

// Step 2: Poll
do {
    sleep(5);
    $status = $client->video->getOperation($operation['name']);
} while (empty($status['done']));

print_r($status['response']);

// Advanced parameters
$operation = $client->video->generate(
    prompt:           'Aerial drone shot of a mountain range at sunrise.',
    sampleCount:      2,
    outputStorageUri: 'gs://your-bucket/output/',
    additionalParams: ['generateAudio' => true, 'durationSeconds' => 8]
);
```

---

## Model Reference

| Model ID | Type | Resource | Notes |
|---|---|---|---|
| `gemini-3.1-flash-lite-preview` | Text | `$client->text` | **Default model** |
| `gemini-3.1-pro-preview` | Text | `$client->text` | High capability |
| `imagen-3.0-generate-001` | Image | `$client->images` | Highest quality |
| `imagen-3.0-fast-generate-001` | Image | `$client->images` | Faster, lower cost |
| `gemini-3.1-flash-tts-preview` | TTS | `$client->audio` | Low-latency, style control |
| `gemini-2.5-pro-tts` | TTS | `$client->audio` | High quality |
| `gemini-2.5-flash-tts` | TTS | `$client->audio` | Fast |
| `anthropic/claude-sonnet-4-6` | Text | `$client->claude` | Requires Cloud Mode |
| `anthropic/claude-opus-4-6` | Text | `$client->claude` | Requires Cloud Mode |
| `google/veo-3.1-generate-001` | Video | `$client->video` | Long-running operation |
| `elevenlabs/elevenlabs-tts-v2-5` | TTS | `$client->audio` | Third-party |

---

## About Google Agent Platform (formerly Vertex AI)

Google Agent Platform was formerly known as **Vertex AI**. The rebrand reflects Google's strategic shift toward agentic AI — systems that can plan, reason, and act autonomously. The underlying infrastructure and all Vertex AI API endpoints remain fully compatible. Existing code using Vertex AI endpoints will continue to work without changes.
