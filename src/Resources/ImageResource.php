<?php

namespace GoogleAgentPlatform\Resources;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Support\MimeTypes;

/**
 * Imagen image generation.
 *
 * Supported models:
 *  - 'imagen-3.0-generate-001'       (highest quality, default)
 *  - 'imagen-3.0-fast-generate-001'  (faster, lower cost)
 */
class ImageResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Generate images from a text prompt.
     *
     * The API returns base64-encoded PNG/JPEG bytes. This method decodes them
     * and optionally saves each image to disk.
     *
     * @param string      $prompt           Text prompt describing the image.
     * @param string      $modelId          Model identifier.
     * @param int         $sampleCount      Number of images to generate (1–4).
     * @param string      $aspectRatio      e.g. '1:1', '16:9', '9:16', '4:3', '3:4'.
     * @param string|null $outputDir        Directory to save images to. If null, images are
     *                                      returned as base64 strings in the result array.
     * @param array       $additionalParams Extra parameters (negativePrompt, personGeneration, etc.)
     *
     * @return array  Each element:
     *                ['mimeType' => 'image/png', 'base64' => '...', 'savedPath' => '...']
     *                'savedPath' is only present when $outputDir is provided.
     */
    public function generate(
        string  $prompt,
        string  $modelId          = 'imagen-3.0-generate-001',
        int     $sampleCount      = 1,
        string  $aspectRatio      = '1:1',
        ?string $outputDir        = null,
        array   $additionalParams = []
    ): array {
        $parameters = \array_merge([
            'sampleCount' => $sampleCount,
            'aspectRatio' => $aspectRatio,
        ], $additionalParams);

        $payload = [
            'instances'  => [['prompt' => $prompt]],
            'parameters' => $parameters,
        ];

        $response    = $this->http->request($modelId, 'predict', $payload);
        $predictions = $response['predictions'] ?? [];
        $results     = [];

        foreach ($predictions as $prediction) {
            $mimeType = $prediction['mimeType'] ?? 'image/png';
            $b64      = $prediction['bytesBase64Encoded'] ?? '';

            $entry = [
                'mimeType' => $mimeType,
                'base64'   => $b64,
            ];

            if ($outputDir !== null && $b64 !== '') {
                if (!\is_dir($outputDir)) {
                    \mkdir($outputDir, 0755, true);
                }

                $ext      = MimeTypes::toExtension($mimeType);
                $filename = $outputDir . \DIRECTORY_SEPARATOR . 'imagen_' . \uniqid() . '.' . $ext;

                \file_put_contents($filename, \base64_decode($b64));
                $entry['savedPath'] = $filename;
            }

            $results[] = $entry;
        }

        return $results;
    }
}
