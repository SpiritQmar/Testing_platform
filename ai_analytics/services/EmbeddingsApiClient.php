<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';

final class EmbeddingsApiClient
{
    private string $apiBaseUrl;
    private int $timeout;
    private Logger $logger;
    private bool $isHealthy = false;
    private ?float $lastHealthCheck = null;
    private const HEALTH_CHECK_INTERVAL = 60;

    public function __construct(array $config)
    {
        $this->apiBaseUrl = rtrim($config['embeddings']['api_url'], '/');
        $this->timeout = $config['embeddings']['timeout'];
        $this->logger = getLogger();
    }

    public function isAvailable(): bool
    {
        $currentTime = microtime(true);

        if ($this->lastHealthCheck !== null && ($currentTime - $this->lastHealthCheck) < self::HEALTH_CHECK_INTERVAL) {
            return $this->isHealthy;
        }

        try {
            $healthUrl = $this->apiBaseUrl . '/health';
            $curlHandle = curl_init($healthUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 3);

            $response = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            $this->isHealthy = ($httpStatusCode === 200);
            $this->lastHealthCheck = $currentTime;

            if (!$this->isHealthy) {
                $this->logger->warning('Embeddings API health check failed', [
                    'url' => $healthUrl,
                    'status_code' => $httpStatusCode,
                ]);
            }

            return $this->isHealthy;
        } catch (Throwable $exception) {
            $this->logger->error('Embeddings API health check error', [
                'error' => $exception->getMessage(),
            ]);
            $this->isHealthy = false;
            $this->lastHealthCheck = $currentTime;
            return false;
        }
    }

    public function getEmbedding(string $text): ?array
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('Embeddings API unavailable, skipping embedding request');
            return null;
        }

        try {
            $embedUrl = $this->apiBaseUrl . '/embed';
            $payload = json_encode(['text' => $text]);

            $curlHandle = curl_init($embedUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            if ($httpStatusCode !== 200) {
                $this->logger->error('Embeddings API request failed', [
                    'url' => $embedUrl,
                    'status_code' => $httpStatusCode,
                    'response' => $response,
                ]);
                return null;
            }

            $decodedResponse = json_decode($response, true);
            return $decodedResponse['embedding'] ?? null;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get embedding', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function getBatchEmbeddings(array $texts): ?array
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('Embeddings API unavailable, skipping batch embedding request');
            return null;
        }

        try {
            $embedUrl = $this->apiBaseUrl . '/embed-batch';
            $payload = json_encode(['texts' => $texts]);

            $curlHandle = curl_init($embedUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            if ($httpStatusCode !== 200) {
                $this->logger->error('Embeddings API batch request failed', [
                    'url' => $embedUrl,
                    'status_code' => $httpStatusCode,
                    'response' => $response,
                ]);
                return null;
            }

            $decodedResponse = json_decode($response, true);
            return $decodedResponse['embeddings'] ?? null;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get batch embeddings', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function calculateSimilarity(string $text1, string $text2): ?float
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('Embeddings API unavailable, skipping similarity calculation');
            return null;
        }

        try {
            $similarityUrl = $this->apiBaseUrl . '/similarity';
            $payload = json_encode(['text1' => $text1, 'text2' => $text2]);

            $curlHandle = curl_init($similarityUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            if ($httpStatusCode !== 200) {
                $this->logger->error('Embeddings API similarity request failed', [
                    'url' => $similarityUrl,
                    'status_code' => $httpStatusCode,
                    'response' => $response,
                ]);
                return null;
            }

            $decodedResponse = json_decode($response, true);
            return $decodedResponse['similarity'] ?? null;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to calculate similarity', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function deduplicateTexts(array $texts, ?float $threshold = null): ?array
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('Embeddings API unavailable, skipping deduplication');
            return null;
        }

        try {
            $deduplicateUrl = $this->apiBaseUrl . '/deduplicate';
            $payload = ['texts' => $texts];
            if ($threshold !== null) {
                $payload['threshold'] = $threshold;
            }

            $curlHandle = curl_init($deduplicateUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            if ($httpStatusCode !== 200) {
                $this->logger->error('Embeddings API deduplication request failed', [
                    'url' => $deduplicateUrl,
                    'status_code' => $httpStatusCode,
                    'response' => $response,
                ]);
                return null;
            }

            $decodedResponse = json_decode($response, true);
            return $decodedResponse['duplicates'] ?? null;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to deduplicate texts', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }
}
