<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/env_loader.php';

$envFilePath = __DIR__ . '/../.env';
if (!file_exists($envFilePath)) {
    $envFilePath = __DIR__ . '/.env';
}
loadEnvironmentVariables($envFilePath);

return [
    'db' => [
        'host' => getEnvironmentVariable('DB_HOST', 'localhost'),
        'port' => getEnvironmentVariable('DB_PORT', '3306'),
        'name' => getEnvironmentVariable('DB_NAME', 'exam_analyzer_2'),
        'user' => getEnvironmentVariable('DB_USER', 'root'),
        'pass' => getEnvironmentVariable('DB_PASS', ''),
        'charset' => getEnvironmentVariable('DB_CHARSET', 'utf8mb4'),
    ],
    'app' => [
        'name' => getEnvironmentVariable('APP_NAME', 'AI Analytics Standalone'),
        'debug' => filter_var(getEnvironmentVariable('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],
    'embeddings' => [
        'api_url' => getEnvironmentVariable('EMBEDDINGS_API_URL', 'http://127.0.0.1:8000'),
        'timeout' => (int)getEnvironmentVariable('EMBEDDINGS_API_TIMEOUT', '30'),
    ],
    'coefficients' => [
        'quality' => [
            'easy_threshold' => (float)getEnvironmentVariable('QUALITY_EASY_THRESHOLD', '0.8'),
            'hard_threshold' => (float)getEnvironmentVariable('QUALITY_HARD_THRESHOLD', '0.3'),
            'discrimination_strong' => (float)getEnvironmentVariable('QUALITY_DISCRIMINATION_STRONG', '0.4'),
        ],
        'risk' => [
            'systemic_avg' => (float)getEnvironmentVariable('RISK_SYSTEMIC_AVG', '50'),
            'spot_min' => (float)getEnvironmentVariable('RISK_SPOT_MIN', '20'),
        ],
        'weights' => [
            'score_weight' => (float)getEnvironmentVariable('WEIGHT_SCORE', '0.5'),
            'penalty_easy_hard' => (float)getEnvironmentVariable('WEIGHT_PENALTY_EASY_HARD', '0.2'),
        ],
        'discrimination' => [
            'min_students' => (int)getEnvironmentVariable('MIN_STUDENTS_FOR_DISCRIMINATION', '10'),
        ],
        'similarity' => [
            'duplicate_threshold' => (float)getEnvironmentVariable('DUPLICATE_SIMILARITY_THRESHOLD', '0.35'),
        ],
        'keyword' => [
            'min_token_length' => (int)getEnvironmentVariable('KEYWORD_COVERAGE_MIN_LENGTH', '2'),
        ],
    ],
    'local' => [
        'services' => [
            'ai_analytics' => true,
            'tfidf_analysis' => true,
            'rule_classifier' => true,
            'semantic_analysis' => true,
        ],
    ],
    'containers' => [
        'enabled' => false,
        'base_url' => 'http://localhost',
        'services' => [
            'ai_analytics' => [
                'enabled' => false,
                'port' => 8001,
                'image' => 'ai-analytics:latest',
                'endpoint' => '/api',
            ],
            'tfidf_analysis' => [
                'enabled' => false,
                'port' => 8002,
                'image' => 'tfidf-analysis:latest',
                'endpoint' => '/api',
            ],
        ],
    ],
    'api' => [
        'timeout' => 30,
    ],
];
