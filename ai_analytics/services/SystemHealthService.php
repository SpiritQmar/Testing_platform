<?php

declare(strict_types=1);

require_once __DIR__ . '/EmbeddingsService.php';

final class SystemHealthService
{
    private EmbeddingsService $embeddingsService;

    public function __construct()
    {
        $this->embeddingsService = new EmbeddingsService();
    }

    public function checkSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'services' => [],
            'warnings' => [],
            'errors' => []
        ];

        
        $embeddingsStatus = $this->checkEmbeddingsService();
        $health['services']['embeddings'] = $embeddingsStatus;

        if (!$embeddingsStatus['available']) {
            $health['status'] = 'degraded';
            $health['warnings'][] = 'Python сервис эмбеддингов не запущен';
            $health['errors'][] = 'Запустите: python embeddings_api.py в папке python_services';
        }

        return $health;
    }

    private function checkEmbeddingsService(): array
    {
        $status = [
            'available' => false,
            'response_time' => null,
            'error' => null
        ];

        try {
            $startTime = microtime(true);
            $available = $this->embeddingsService->isEmbeddingsServiceAvailable();
            $endTime = microtime(true);

            $status['available'] = $available;
            $status['response_time'] = round(($endTime - $startTime) * 1000, 2); 

            if (!$available) {
                $status['error'] = 'Сервис недоступен или не отвечает';
            }
        } catch (Exception $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    public function getHealthStatusBadge(): string
    {
        $health = $this->checkSystemHealth();
        
        if ($health['status'] === 'healthy') {
            return '<span class="badge bg-success">✅ Все сервисы работают</span>';
        } else {
            return '<span class="badge bg-warning">⚠️ Python сервис не запущен</span>';
        }
    }

    public function getHealthAlerts(): array
    {
        $health = $this->checkSystemHealth();
        $alerts = [];

        if (!$health['services']['embeddings']['available']) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Python сервис эмбеддингов не запущен',
                'message' => 'Для полноценной валидации запустите сервис: </code>',
                'action' => 'Запустить сервис'
            ];
        }

        return $alerts;
    }
}
