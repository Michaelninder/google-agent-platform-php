# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
