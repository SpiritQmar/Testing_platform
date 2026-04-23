<?php

declare(strict_types=1);

final class EmbeddingsService
{
    private string $embeddingsApiUrl;
    private bool $embeddingsFeatureEnabled;

    public function __construct(string $embeddingsApiUrl = 'http://localhost:8000', bool $embeddingsFeatureEnabled = true)
    {
        $this->embeddingsApiUrl = rtrim($embeddingsApiUrl, '/');
        $this->embeddingsFeatureEnabled = $embeddingsFeatureEnabled;
    }

    public function isEmbeddingsServiceAvailable(): bool
    {
        if (!$this->embeddingsFeatureEnabled) {
            return false;
        }

        try {
            $apiResponse = $this->sendApiRequest('/health');
            return isset($apiResponse['status']) && $apiResponse['status'] === 'healthy';
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function getEmbedding(string $questionText): ?array
    {
        if (!$this->isEmbeddingsServiceAvailable()) {
            error_log('Функция семантического анализа отключена или недоступна');
            return null;
        }

        try {
            $apiResponse = $this->sendApiRequest('/embed', 'POST', ['text' => $questionText]);
            return [
                'embedding' => $apiResponse['embedding'] ?? [],
                'dimension' => $apiResponse['dimension'] ?? 0
            ];
        } catch (Throwable $exception) {
            error_log('Ошибка при генерации эмбеддинга для текста: ' . $exception->getMessage());
            return null;
        }
    }

    public function getBatchEmbeddings(array $questionTexts): ?array
    {
        if (!$this->isEmbeddingsServiceAvailable()) {
            error_log('Функция семантического анализа отключена или недоступна');
            return null;
        }

        try {
            $apiResponse = $this->sendApiRequest('/embed-batch', 'POST', ['texts' => $questionTexts]);
            return [
                'embeddings' => $apiResponse['embeddings'] ?? [],
                'dimension' => $apiResponse['dimension'] ?? 0
            ];
        } catch (Throwable $exception) {
            error_log('Ошибка при пакетной генерации эмбеддингов: ' . $exception->getMessage());
            return null;
        }
    }

    public function getSimilarity(string $firstQuestionText, string $secondQuestionText): ?array
    {
        if (!$this->isEmbeddingsServiceAvailable()) {
            error_log('Функция семантического анализа отключена или недоступна');
            return null;
        }

        try {
            $apiResponse = $this->sendApiRequest('/similarity', 'POST', [
                'text1' => $firstQuestionText,
                'text2' => $secondQuestionText
            ]);
            return [
                'similarity' => $apiResponse['similarity'] ?? 0,
                'is_duplicate' => $apiResponse['is_duplicate'] ?? false
            ];
        } catch (Throwable $exception) {
            error_log('Ошибка при расчете семантического сходства вопросов: ' . $exception->getMessage());
            return null;
        }
    }

    public function deduplicateTexts(array $examQuestions, float $similarityThreshold = 0.85): ?array
    {
        if (!$this->isEmbeddingsServiceAvailable()) {
            error_log('Функция семантического анализа отключена или недоступна');
            return null;
        }

        try {
            $apiResponse = $this->sendApiRequest('/deduplicate?threshold=' . $similarityThreshold, 'POST', ['texts' => $examQuestions]);
            return [
                'duplicates' => $apiResponse['duplicates'] ?? [],
                'count' => $apiResponse['count'] ?? 0
            ];
        } catch (Throwable $exception) {
            error_log('Ошибка при обнаружении дубликатов в вопросах: ' . $exception->getMessage());
            return null;
        }
    }

    private function sendApiRequest(string $apiEndpoint, string $httpMethod = 'GET', array $requestData = null): array
    {
        $fullApiUrl = $this->embeddingsApiUrl . $apiEndpoint;

        $httpContextOptions = [
            'http' => [
                'method' => $httpMethod,
                'header' => 'Content-Type: application/json',
                'timeout' => 30
            ]
        ];

        if ($requestData !== null && $httpMethod === 'POST') {
            $httpContextOptions['http']['content'] = json_encode($requestData);
        }

        $streamContext = stream_context_create($httpContextOptions);
        $apiResponse = file_get_contents($fullApiUrl, false, $streamContext);

        if ($apiResponse === false) {
            throw new Exception('Не удалось получить ответ от API эмбеддингов. Убедитесь, что Python-сервис запущен на ' . $this->embeddingsApiUrl);
        }

        $decodedResponse = json_decode($apiResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ответ API содержит некорректный JSON: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }
}
