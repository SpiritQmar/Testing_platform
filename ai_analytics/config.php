<?php
return array (
  'db' => 
  array (
    'host' => 'localhost',
    'port' => '3307',
    'name' => 'exam_analyzer_3',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ),
  'app' => 
  array (
    'name' => 'AI Analytics Standalone',
    'debug' => false,
  ),
  'embeddings' => 
  array (
    'api_url' => 'http://127.0.0.1:8000',
    'timeout' => 30,
  ),
  'coefficients' => 
  array (
    'quality' => 
    array (
      'easy_threshold' => 0.8,
      'hard_threshold' => 0.3,
      'discrimination_strong' => 0.4,
    ),
    'risk' => 
    array (
      'systemic_avg' => 50.0,
      'spot_min' => 20.0,
    ),
    'weights' => 
    array (
      'score_weight' => 0.5,
      'penalty_easy_hard' => 0.2,
    ),
    'discrimination' => 
    array (
      'min_students' => 9,
    ),
    'similarity' => 
    array (
      'duplicate_threshold' => 0.35,
    ),
    'keyword' => 
    array (
      'min_token_length' => 2,
    ),
  ),
  'local' => 
  array (
    'services' => 
    array (
      'ai_analytics' => true,
      'tfidf_analysis' => true,
      'rule_classifier' => true,
      'semantic_analysis' => true,
    ),
  ),
  'containers' => 
  array (
    'enabled' => false,
    'base_url' => 'http://localhost',
    'services' => 
    array (
      'ai_analytics' => 
      array (
        'enabled' => false,
        'port' => 8001,
        'image' => 'ai-analytics:latest',
        'endpoint' => '/api',
      ),
      'tfidf_analysis' => 
      array (
        'enabled' => false,
        'port' => 8002,
        'image' => 'tfidf-analysis:latest',
        'endpoint' => '/api',
      ),
    ),
  ),
  'api' => 
  array (
    'timeout' => 30,
  ),
);
