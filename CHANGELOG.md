# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.6.0] — 2026-05-02

### Fixed
- **Streaming was broken** — `streamGenerateContent()` and `claude->messages(stream: true)` both
  previously went through the same `json_decode` path as non-streaming requests, producing garbled
  or errored responses. `HttpClient::stream()` now uses `CURLOPT_WRITEFUNCTION` to process
  newline-delimited JSON chunks (SSE) incrementally without buffering the full response. Handles
  `data:` prefix and `[DONE]` sentinel correctly.
- **`getOperation()` ignored API key in Express Mode** — `HttpClient::get()` now appends
  `?key=` when `$apiKey` is set, so polling long-running Veo operations works in Express Mode.
- **`FileResource::getFile()` fragile URL construction** — replaced `str_replace('files/', '')`
  with `str_starts_with()` + `substr()`. Now correctly handles `'files/abc123'`,
  `'/files/abc123'`, and bare `'abc123'`.
- **`finfo_close()` deprecated in PHP 8.5** — removed the call from `MimeTypes::detect()`;
  the `finfo` object is freed automatically.

### Added
- **`TextResource::generate()` / `stream()`** now accept `$systemInstruction` (system prompt)
  and `$generationConfig` (temperature, maxOutputTokens, topP, responseMimeType, etc.).
  Both are also wired into the legacy `generateContent()` / `streamGenerateContent()` on `Client`.
- **`FileResource::deleteFile()`** — delete an uploaded file from the File API by resource name
  (`'files/abc123'`, `'/files/abc123'`, or bare `'abc123'`).
- **`HttpClient::delete()`** — new DELETE method used by `deleteFile()`, appends API key in
  Express Mode.
- **Typed exception hierarchy** under `GoogleAgentPlatform\Exceptions\`:
  - `AgentPlatformException` — base class (extends `\RuntimeException`)
  - `ApiException` — API 4xx/5xx errors, exposes `getHttpCode()`
  - `AuthException` — 401/403 errors (extends `ApiException`)
  - `FileNotFoundException` — local file not found (extends `AgentPlatformException`)
  - `HttpClient` now throws `ApiException` / `AuthException` instead of `\RuntimeException`.
  - `FileResource` now throws `FileNotFoundException` instead of `\InvalidArgumentException`.
- **Chunked file upload** — `FileResource::uploadBytes()` now uses `CURLOPT_PUT` +
  `CURLOPT_INFILE` to stream the file from disk in chunks, avoiding loading the entire file
  into memory with `file_get_contents()`.
- **`TextResource::stream()` callback API** — accepts an optional `$onChunk` callable;
  collects and returns all chunks when no callback is provided.
- **`ClaudeResource::messages()` streaming** — accepts optional `$onChunk` callable;
  routes `stream: true` through `HttpClient::stream()` instead of the JSON-decode path.
- **PHPUnit test suite** — 59 tests, 178 assertions, all passing:
  - `tests/Unit/ClientTest.php`
  - `tests/Unit/HttpClientTest.php`
  - `tests/Unit/TextResourceTest.php`
  - `tests/Unit/ImageResourceTest.php`
  - `tests/Unit/AudioResourceTest.php`
  - `tests/Unit/VideoResourceTest.php`
  - `tests/Unit/ClaudeResourceTest.php`
  - `tests/Unit/FileResourceTest.php`
  - `tests/Unit/MimeTypesTest.php`
  - `tests/Unit/ExceptionsTest.php`

### Changed
- `Client::predict()` no longer uses `ReflectionProperty` to access `HttpClient` — a direct
  `$this->http` reference is stored instead.
- `Client::generateContent()` and `Client::streamGenerateContent()` signatures extended with
  optional `$systemInstruction` and `$generationConfig` parameters (fully backward-compatible).
- `phpunit/phpunit ^11.0` added to `require-dev`; `phpunit.xml` config added.

---

## [0.5.0] — 2026-05-02

### Added
- **Multi-file source structure** — `src/Client.php` is now a thin facade. Logic is split into:
  - `src/Http/HttpClient.php` — all cURL communication, URL building, auth headers
  - `src/Resources/TextResource.php` — Gemini text generation
  - `src/Resources/ImageResource.php` — Imagen image generation
  - `src/Resources/AudioResource.php` — Text-to-Speech synthesis
  - `src/Resources/VideoResource.php` — Veo video generation and operation polling
  - `src/Resources/ClaudeResource.php` — Anthropic Claude messages
  - `src/Resources/FileResource.php` — file handling (see below)
  - `src/Support/MimeTypes.php` — MIME type detection and extension mapping
- **Resource API** — all capabilities accessible as typed properties on `Client`:
  `$client->text`, `$client->images`, `$client->audio`, `$client->video`, `$client->claude`, `$client->files`
- **`FileResource::withFile()`** — reads a local file, base64-encodes it as `inlineData`, and returns a ready-to-use `contents` array for `generateContent()`. MIME type is auto-detected via `finfo`.
- **`FileResource::withFiles()`** — same as `withFile()` but accepts multiple files in a single request.
- **`FileResource::uploadFile()`** — uploads a local file to the Gemini File API via a two-step resumable upload. Returns a file URI valid for 48 hours, usable across multiple requests.
- **`FileResource::fromUri()`** — convenience builder that wraps an existing File API URI into a `contents` array.
- **`FileResource::listFiles()`** — list files previously uploaded to the File API.
- **`FileResource::getFile()`** — get metadata for a specific uploaded file.
- **`MimeTypes::detect()`** — auto-detects MIME type from file magic bytes via `finfo`, with extension-based fallback.
- `ext-fileinfo` added to `composer.json` requirements (used for MIME detection).
- PHP minimum version bumped to `8.1` (required for `readonly` properties).

### Changed
- All legacy flat methods on `Client` (`generateContent`, `generateImage`, `synthesizeSpeech`, `generateVideo`, `getOperation`, `claudeMessages`, `predict`) are fully preserved and delegate to the new resource classes — **no breaking changes**.
- `composer.json` description and keywords updated to reflect file upload and multimodal support.

---

## [0.4.0] — 2026-05-02

### Added
- `generateImage()` — Imagen 3 image generation (`imagen-3.0-generate-001`, `imagen-3.0-fast-generate-001`). Decodes base64 PNG/JPEG bytes automatically. Supports optional `outputDir` to save files to disk, `aspectRatio`, `negativePrompt`, `personGeneration`, and any additional Imagen parameters.
- `synthesizeSpeech()` — Text-to-Speech for all TTS models (`gemini-3.1-flash-tts-preview`, `gemini-2.5-pro-tts`, `gemini-2.5-flash-tts`, `elevenlabs/elevenlabs-tts-v2-5`). Handles both raw binary and base64-wrapped JSON responses. Supports optional `outputFile` to save audio to disk, `voiceConfig`, and `stylePrompt`.
- `requestRaw()` (private) — Internal HTTP method that returns the raw response string without JSON decoding. Used as the foundation for both `request()` and binary response handling.
- `mimeToExtension()` (private) — Helper mapping MIME types to file extensions for image and audio output.

### Changed
- `request()` now delegates to `requestRaw()` internally, removing duplicated cURL logic.
- Improved error handling: HTTP 4xx/5xx errors are surfaced with structured messages even for binary endpoints.
- Fixed `sprintf` calls to use global namespace (`\sprintf`) for PHP compiler optimization.
- Replaced string concatenation with string interpolation throughout.

---

## [0.3.0] — 2026-05-02

### Added
- `claudeMessages()` — Support for Anthropic Claude models hosted on Agent Platform (`anthropic/claude-sonnet-4-6`, `anthropic/claude-opus-4-6`). Automatically injects the required `anthropic_version: vertex-2023-10-16` header and routes to the `rawPredict` endpoint. Supports streaming and arbitrary extra parameters.
- `generateVideo()` — Veo 3.1 video generation (`google/veo-3.1-generate-001`) via the `predictLongRunning` endpoint. Supports `sampleCount`, optional `outputStorageUri` (GCS bucket), and additional parameters such as `generateAudio` and `durationSeconds`.
- `getOperation()` — Poll the status of a long-running operation by its full operation name (returned by `generateVideo()`).

### Changed
- README updated to note that Google Agent Platform was formerly known as **Vertex AI**, with a link to the API key management page.
- README expanded with Claude usage examples, expected response shapes, Veo two-step submit/poll pattern, and a full model reference table.
- `composer.json` updated with `keywords`, explicit `php >=8.0`, `ext-curl`, and `ext-json` requirements.

---

## [0.2.4] — 2026-05-02

### Changed
- Added `composer.json` to the repository.
- Removed explicit `curl_close()` call (deprecated in PHP 8.0; the `CurlHandle` object closes automatically on destruction).
- Refactored `Client` methods for improved clarity and consistent parameter defaults.
- Revised README for clearer SDK usage instructions and quick-start examples.

---

## [0.2.1] — 2026-05-02

### Added
- Initial `Client` class implementation with `generateContent()`, `streamGenerateContent()`, and `predict()`.
- Express Mode (API key) and Google Cloud Mode (OAuth Bearer token + project ID) authentication.
- Dynamic publisher resolution: prefix a model ID with `publisher/` (e.g. `elevenlabs/elevenlabs-tts-v2-5`) to route to third-party models.
- README with quick-start instructions for both authentication modes.

---

## [0.1.0] — 2026-05-02

### Added
- Initial project scaffold.
