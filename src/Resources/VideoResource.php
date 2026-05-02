<?php

namespace GoogleAgentPlatform\Resources;

use GoogleAgentPlatform\Http\HttpClient;

/**
 * Veo video generation (long-running operations).
 *
 * Supported models:
 *  - 'google/veo-3.1-generate-001'
 *
 * Video generation is asynchronous. generateVideo() submits the job and returns
 * an operation name. Use getOperation() to poll until 'done' is true.
 */
class VideoResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Submit a video generation job.
     *
     * @param string      $prompt            Text prompt describing the video.
     * @param string      $modelId           Model identifier.
     * @param int         $sampleCount       Number of videos to generate (1–2).
     * @param string|null $outputStorageUri  GCS bucket URI, e.g. 'gs://bucket/output/'.
     *                                       If null, base64-encoded video bytes are returned.
     * @param array       $additionalParams  Extra parameters, e.g. ['generateAudio' => true].
     *
     * @return array  Contains 'name' — the full operation name to pass to getOperation().
     */
    public function generate(
        string  $prompt,
        string  $modelId          = 'google/veo-3.1-generate-001',
        int     $sampleCount      = 1,
        ?string $outputStorageUri = null,
        array   $additionalParams = []
    ): array {
        $parameters = \array_merge(['sampleCount' => $sampleCount], $additionalParams);

        if ($outputStorageUri !== null) {
            $parameters['storageUri'] = $outputStorageUri;
        }

        return $this->http->request($modelId, 'predictLongRunning', [
            'instances'  => [['prompt' => $prompt]],
            'parameters' => $parameters,
        ]);
    }

    /**
     * Poll the status of a long-running operation.
     *
     * @param string $operationName  Full operation name returned by generate(),
     *                               e.g. "projects/my-project/locations/us-central1/operations/123"
     *
     * @return array  When 'done' is true, 'response' contains the video output.
     */
    public function getOperation(string $operationName): array
    {
        $base = $this->http->getBaseUrl();
        $path = \ltrim($operationName, '/');

        if (\str_starts_with($path, 'projects/')) {
            // Already a full resource path — use as-is
            $url = "{$base}/{$path}";
        } else {
            $url = "{$base}/{$path}";
        }

        // Append API key if in Express Mode
        // (HttpClient::get() handles auth headers for Cloud Mode)
        return $this->http->get($url);
    }
}
