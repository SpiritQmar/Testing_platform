<?php

declare(strict_types=1);

require_once __DIR__ . '/ValidationService.php';
require_once __DIR__ . '/QualityMetricsService.php';
require_once __DIR__ . '/DiscriminationService.php';
require_once __DIR__ . '/StudentAnalysisService.php';
require_once __DIR__ . '/CriteriaAnalysisService.php';
require_once __DIR__ . '/DataService.php';
require_once __DIR__ . '/RuleClassifierService.php';
require_once __DIR__ . '/AnalysisCacheService.php';
require_once __DIR__ . '/TfIdfService.php';
require_once __DIR__ . '/EmbeddingsService.php';

final class AIAnalyticsService
{
    public const DEMO_IMPORT_FILENAME = 'ai_synthetic_demo.seed';

    private ?int $currentImportId = null;
    private PDO $databaseConnection;

    private ValidationService $validationService;
    private QualityMetricsService $qualityMetricsService;
    private DiscriminationService $discriminationService;
    private StudentAnalysisService $studentAnalysisService;
    private CriteriaAnalysisService $criteriaAnalysisService;
    private DataService $dataService;
    private RuleClassifierService $ruleClassifier;
    private AnalysisCacheService $cacheService;
    private TfIdfService $tfidfService;

    public function __construct(PDO $databaseConnection, ?int $importId = null)
    {
        $this->databaseConnection = $databaseConnection;
        $this->currentImportId = $importId;

        $embeddingsService = new EmbeddingsService();
        $this->qualityMetricsService = new QualityMetricsService($databaseConnection);
        $this->discriminationService = new DiscriminationService($databaseConnection);
        $this->studentAnalysisService = new StudentAnalysisService($databaseConnection);
        $this->criteriaAnalysisService = new CriteriaAnalysisService($databaseConnection);
        $this->dataService = new DataService($databaseConnection);
        $this->ruleClassifier = new RuleClassifierService($databaseConnection);

        $this->tfidfService = new TfIdfService($databaseConnection, $embeddingsService, $importId);
        $this->validationService = new ValidationService($databaseConnection, $embeddingsService, $this->tfidfService);
        $this->cacheService = new AnalysisCacheService($databaseConnection, $this->validationService, $this->tfidfService);
    }

    public function setMinStudentsForDiscrimination(int $value): void
    {
        $this->discriminationService->setMinStudentsForDiscrimination($value);
    }

    public function getCurrentImportId(): ?int
    {
        return $this->currentImportId;
    }

    public function getDemoImportId(): ?int
    {
        return $this->dataService->getDemoImportId();
    }

    public function getSyllabusTopics(): array
    {
        return $this->dataService->getSyllabusTopics();
    }

    public function getQuestionAnalysisList(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->dataService->getQuestionAnalysisList($this->currentImportId);
    }

    public function validateQuestionSyllabusAlignment(int $questionId): ?array
    {
        if ($this->currentImportId === null) {
            return null;
        }
        return $this->validationService->validateQuestionSyllabusAlignment($questionId, $this->currentImportId);
    }

    public function getValidationOverview(): array
    {
        if ($this->currentImportId === null) return [];
        try {
            $cached = $this->cacheService->getCachedValidation($this->currentImportId);
            if (!empty($cached)) return $cached;
            $this->cacheService->buildValidationCache($this->currentImportId);
            return $this->cacheService->getCachedValidation($this->currentImportId);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getSemanticAnalysis(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }

        if ($this->cacheService->isCacheValid($this->currentImportId)) {
            return $this->cacheService->getCachedSemantic($this->currentImportId);
        }

        $this->cacheService->buildSemanticCache($this->currentImportId);
        return $this->cacheService->getCachedSemantic($this->currentImportId);
    }

    public function rebuildAnalysisCache(): bool
    {
        if ($this->currentImportId === null) {
            return false;
        }

        $this->cacheService->invalidateCache($this->currentImportId);
        $validationSuccess = $this->cacheService->buildValidationCache($this->currentImportId);
        $semanticSuccess = $this->cacheService->buildSemanticCache($this->currentImportId);

        return $validationSuccess && $semanticSuccess;
    }

    public function getQuestionQualityMetrics(?string $sortColumn = null, ?string $sortDirection = null): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->qualityMetricsService->getQuestionQualityMetrics($this->currentImportId, $sortColumn, $sortDirection);
    }

    public function getDiscriminationIndex(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->discriminationService->getDiscriminationIndex($this->currentImportId);
    }

    public function getItemTotalCorrelation(?string $sortColumn = null, ?string $sortDirection = null): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->discriminationService->getItemTotalCorrelation($this->currentImportId, $sortColumn, $sortDirection);
    }

    public function getStudentRiskPatterns(int $page = 1, int $perPage = 50, ?string $sortColumn = null, ?string $sortDirection = null): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->studentAnalysisService->getStudentRiskPatterns($this->currentImportId, $page, $perPage, $sortColumn, $sortDirection);
    }

    public function getStudentRiskPatternsCount(): int
    {
        if ($this->currentImportId === null) {
            return 0;
        }
        return $this->studentAnalysisService->getStudentRiskPatternsCount($this->currentImportId);
    }

    public function getNationalityPerformanceBreakdown(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->studentAnalysisService->getNationalityPerformanceBreakdown($this->currentImportId);
    }

    public function analyzeRubricCompliance(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->dataService->analyzeRubricCompliance($this->currentImportId);
    }

    public function checkRejectedQuestionSimilarity(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->dataService->checkRejectedQuestionSimilarity($this->currentImportId);
    }

    public function getTeacherQualityRankings(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        $discriminationIndices = $this->discriminationService->getDiscriminationIndex($this->currentImportId);
        return $this->qualityMetricsService->getTeacherQualityRankings($this->currentImportId, $discriminationIndices);
    }

    public function suggestQuestionWeights(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->qualityMetricsService->suggestQuestionWeights($this->currentImportId);
    }

    public function getOpenAnswerAnalysis(): array
    {
        return $this->dataService->getOpenAnswerAnalysis();
    }

    public function getDataIntegrityFlags(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->studentAnalysisService->getDataIntegrityFlags($this->currentImportId);
    }

    public function getExamReliabilityMetrics(): array
    {
        if ($this->currentImportId === null) {
            return [
                'cronbach_alpha' => 0,
                'avg_inter_item_correlation' => 0,
                'question_count' => 0,
                'interpretation' => 'Нет данных'
            ];
        }
        return $this->dataService->getExamReliabilityMetrics($this->currentImportId, $this->ruleClassifier);
    }

    public function getCriteriaPerformanceAnalysis(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->criteriaAnalysisService->getCriteriaPerformanceAnalysis($this->currentImportId);
    }

    public function getStudentAnswerAnalysis(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->criteriaAnalysisService->getStudentAnswerAnalysis($this->currentImportId);
    }

    public function getCriteriaByQuestionAnalysis(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }
        return $this->criteriaAnalysisService->getCriteriaByQuestionAnalysis($this->currentImportId);
    }
}
