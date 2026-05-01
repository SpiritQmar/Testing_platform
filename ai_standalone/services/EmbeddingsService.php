<?php

declare(strict_types=1);

require_once __DIR__ . '/EmbeddingsApiClient.php';
require_once __DIR__ . '/../includes/logger.php';

final class EmbeddingsService
{
    private EmbeddingsApiClient $apiClient;
    private Logger $logger;

    public function __construct(?EmbeddingsApiClient $apiClient = null)
    {
        if ($apiClient === null) {
            $config = require __DIR__ . '/../config.php';
            $apiClient = new EmbeddingsApiClient($config);
        }
        $this->apiClient = $apiClient;
        $this->logger = getLogger();
    }

    public function isEmbeddingsServiceAvailable(): bool
    {
        return $this->apiClient->isAvailable();
    }

    public function getEmbedding(string $questionText): ?array
    {
        $embedding = $this->apiClient->getEmbedding($questionText);
        if ($embedding === null) {
            return null;
        }

        return [
            'embedding' => $embedding,
            'dimension' => count($embedding)
        ];
    }

    public function getBatchEmbeddings(array $questionTexts): ?array
    {
        $embeddings = $this->apiClient->getBatchEmbeddings($questionTexts);
        if ($embeddings === null) {
            return null;
        }

        return [
            'embeddings' => $embeddings,
            'dimension' => count($embeddings[0] ?? [])
        ];
    }

    public function getSimilarity(string $firstQuestionText, string $secondQuestionText): ?array
    {
        $similarity = $this->apiClient->calculateSimilarity($firstQuestionText, $secondQuestionText);
        if ($similarity === null) {
            return null;
        }

        $config = require __DIR__ . '/../config.php';
        $duplicateThreshold = $config['coefficients']['similarity']['duplicate_threshold'];

        return [
            'similarity' => $similarity,
            'is_duplicate' => $similarity > $duplicateThreshold
        ];
    }

    public function deduplicateTexts(array $examQuestions, float $similarityThreshold = 0.85): ?array
    {
        $duplicates = $this->apiClient->deduplicateTexts($examQuestions, $similarityThreshold);
        if ($duplicates === null) {
            return null;
        }

        return [
            'duplicates' => $duplicates,
            'count' => count($duplicates)
        ];
    }
}
