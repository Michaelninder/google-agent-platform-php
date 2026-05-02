<?php

namespace GoogleAgentPlatform\Resources;

use GoogleAgentPlatform\Http\HttpClient;

/**
 * Text-to-Speech synthesis.
 *
 * Supported models:
 *  - 'gemini-3.1-flash-tts-preview'   (default — low latency, style control)
 *  - 'gemini-2.5-pro-tts'
 *  - 'gemini-2.5-flash-tts'
 *  - 'elevenlabs/elevenlabs-tts-v2-5' (third-party via Agent Platform)
 *
 * The API may return either:
 *  a) A JSON envelope with base64-encoded audio (Gemini TTS / predict endpoint)
 *  b) Raw binary audio bytes (some rawPredict endpoints)
 *
 * Both cases are handled transparently.
 */
class AudioResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Synthesize speech from text.
     *
     * @param string      $text         The text to synthesize.
     * @param string      $modelId      Model identifier.
     * @param array       $voiceConfig  Voice configuration, e.g.:
     *                                  ['prebuiltVoiceConfig' => ['voiceName' => 'en-US-Standard-A']]
     * @param string|null $stylePrompt  Natural language style instruction, e.g.
     *                                  'Speak in a calm, friendly tone with a slight British accent.'
     * @param string|null $outputFile   Full path to save the audio file (e.g. '/tmp/speech.mp3').
     *                                  If null, raw audio bytes are returned in the result.
     * @param array       $extra        Any additional payload parameters.
     *
     * @return array {
     *   'mimeType'  => 'audio/mp3',
     *   'bytes'     => '<raw binary string>',
     *   'savedPath' => '/tmp/speech.mp3',   // only when $outputFile is provided
     * }
     */
    public function synthesize(
        string  $text,
        string  $modelId     = 'gemini-3.1-flash-tts-preview',
        array   $voiceConfig = [],
        ?string $stylePrompt = null,
        ?string $outputFile  = null,
        array   $extra       = []
    ): array {
        $payload = \array_merge(['text' => $text], $extra);

        if (!empty($voiceConfig)) {
            $payload['voiceConfig'] = $voiceConfig;
        }

        if ($stylePrompt !== null) {
            $payload['stylePrompt'] = $stylePrompt;
        }

        // Fetch raw bytes — TTS responses are binary or base64-wrapped JSON
        $raw      = $this->http->requestRaw($modelId, 'predict', $payload);
        $mimeType = 'audio/mp3';

        // Try JSON first — some endpoints wrap audio in a predictions envelope
        $decoded = \json_decode($raw, true);

        if (\json_last_error() === JSON_ERROR_NONE && isset($decoded['predictions'])) {
            $prediction = $decoded['predictions'][0] ?? [];
            $b64        = $prediction['bytesBase64Encoded'] ?? ($prediction['audioContent'] ?? '');
            $mimeType   = $prediction['mimeType'] ?? 'audio/mp3';
            $audioBytes = \base64_decode($b64);
        } else {
            // Raw binary response
            $audioBytes = $raw;
        }

        $result = [
            'mimeType' => $mimeType,
            'bytes'    => $audioBytes,
        ];

        if ($outputFile !== null) {
            $dir = \dirname($outputFile);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0755, true);
            }
            \file_put_contents($outputFile, $audioBytes);
            $result['savedPath'] = $outputFile;
        }

        return $result;
    }
}
