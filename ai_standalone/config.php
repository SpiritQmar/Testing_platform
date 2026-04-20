<?php
return [
    'db' => [
        'host' => 'localhost',
        'port' => '3307',
        'name' => 'exam_analyzer_2',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'AI Analytics Standalone',
        'debug' => true,
    ],
    'coefficients' => [
        'quality' => [
            'easy_threshold' => 0.8,
            'hard_threshold' => 0.3,
            'discrimination_strong' => 0.4,
        ],
        'risk' => [
            'systemic_avg' => 50,
            'spot_min' => 20,
        ],
        'weights' => [
            'score_weight' => 0.5,
            'penalty_easy_hard' => 0.2,
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
        'services' => [
            'ai_analytics' => [
                'port' => 8001,
                'image' => 'ai-analytics:latest',
            ],
            'tfidf_analysis' => [
                'port' => 8002,
                'image' => 'tfidf-analysis:latest',
            ],
        ],
    ],
];
