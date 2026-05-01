<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';

final class DataService
{
    private PDO $databaseConnection;
    private Logger $logger;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
        $this->logger = getLogger();
    }

    public function getSyllabusTopics(): array
    {
        try {
            return $this->databaseConnection->query("SELECT * FROM ai_syllabus_topics ORDER BY syllabus_topic_id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get syllabus topics', [
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getQuestionAnalysisList(int $importId): array
    {
        try {
            $sqlQuery = "
                SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id, hq.max_score,
                       s.title AS syllabus_title, s.keywords AS syllabus_keywords,
                       AVG(r.received_score) as avg_score, COUNT(DISTINCT r.student_id) as attempts
                FROM hier_questions hq
                INNER JOIN ai_syllabus_topics s ON s.syllabus_topic_id = hq.syllabus_topic_id
                INNER JOIN (
                    SELECT DISTINCT question_id
                    FROM raw_exam_results
                    WHERE import_id = :source_import_id
                ) source_r ON source_r.question_id = hq.question_id
                LEFT JOIN raw_exam_results r ON r.question_id = hq.question_id AND r.import_id = :stats_import_id
                WHERE hq.syllabus_topic_id IS NOT NULL
                GROUP BY hq.question_id, s.syllabus_topic_id
                ORDER BY hq.question_id
            ";
            $queryStatement = $this->databaseConnection->prepare($sqlQuery);
            $queryStatement->execute([
                ':source_import_id' => $importId,
                ':stats_import_id' => $importId,
            ]);
            return $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get question analysis list', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getDemoImportId(): ?int
    {
        try {
            $queryStatement = $this->databaseConnection->prepare("SELECT import_id FROM imports_log WHERE source_filename = :f ORDER BY import_id DESC LIMIT 1");
            $queryStatement->execute([':f' => 'ai_synthetic_demo.seed']);
            $importIdValue = $queryStatement->fetchColumn();
            return $importIdValue !== false ? (int)$importIdValue : null;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get demo import ID', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function getExamReliabilityMetrics(int $importId, $ruleClassifier): array
    {
        try {
            $examDataQuery = $this->databaseConnection->prepare("
                SELECT DISTINCT exam_id
                FROM raw_exam_results
                WHERE import_id = :import_id AND exam_id IS NOT NULL
                LIMIT 1
            ");
            $examDataQuery->execute([':import_id' => $importId]);
            $examRow = $examDataQuery->fetch(PDO::FETCH_ASSOC);

            if (!$examRow || !$examRow['exam_id']) {
                return [
                    'cronbach_alpha' => 0,
                    'avg_inter_item_correlation' => 0,
                    'question_count' => 0,
                    'interpretation' => 'Нет данных об экзамене'
                ];
            }

            $reliabilityData = $ruleClassifier->calculateExamReliability((int)$examRow['exam_id']);

            $alpha = $reliabilityData['cronbach_alpha'];
            $interpretation = 'Недостаточно данных';
            if ($alpha >= 0.9) {
                $interpretation = 'Отличная надежность';
            } elseif ($alpha >= 0.8) {
                $interpretation = 'Хорошая надежность';
            } elseif ($alpha >= 0.7) {
                $interpretation = 'Приемлемая надежность';
            } elseif ($alpha >= 0.6) {
                $interpretation = 'Слабая надежность';
            } else {
                $interpretation = 'Недостаточная надежность';
            }

            return [
                'cronbach_alpha' => round($alpha, 3),
                'avg_inter_item_correlation' => round($reliabilityData['avg_inter_item_correlation'] ?? 0, 3),
                'question_count' => (int)($reliabilityData['question_count'] ?? 0),
                'interpretation' => $interpretation
            ];
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get exam reliability metrics', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [
                'cronbach_alpha' => 0,
                'avg_inter_item_correlation' => 0,
                'question_count' => 0,
                'interpretation' => 'Нет данных'
            ];
        }
    }

    public function getOpenAnswerAnalysis(): array
    {
        try {
            $queryStatement = $this->databaseConnection->query("SELECT o.*, q.expected_keywords, q.question_text FROM ai_open_answers o JOIN ai_question_ai q ON q.question_id = o.question_id");
            $answerRows = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get open answer analysis', [
                'error' => $exception->getMessage(),
            ]);
            return [];
        }

        $analysisResults = [];
        foreach ($answerRows as $answerRow) {
            $studentAnswer = mb_strtolower((string)$answerRow['answer_text'], 'UTF-8');
            $expectedKeywords = (string)($answerRow['expected_keywords'] ?? '');
            $keywordList = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', $expectedKeywords) ?: [])));
            $matchedKeywordCount = 0;

            foreach ($keywordList as $keyword) {
                if ($keyword !== '' && mb_strpos($studentAnswer, mb_strtolower($keyword, 'UTF-8'), 0, 'UTF-8') !== false) {
                    $matchedKeywordCount++;
                }
            }

            $keywordCoverage = count($keywordList) ? round($matchedKeywordCount / count($keywordList), 3) : null;
            $answerLength = mb_strlen((string)$answerRow['answer_text'], 'UTF-8');
            $answerQuality = $answerLength < 15 ? 'низкая развёрнутость ответа' : ($keywordCoverage !== null && $keywordCoverage < 0.2 ? 'слабая релевантность ожидаемым ключевым словам' : 'приемлемое качество ответа');

            $analysisResults[] = [
                'id' => (int)$answerRow['id'],
                'question_id' => (int)$answerRow['question_id'],
                'student_id' => (int)$answerRow['student_id'],
                'preview' => mb_substr((string)$answerRow['answer_text'], 0, 120, 'UTF-8'),
                'keyword_coverage' => $keywordCoverage,
                'quality_hint' => $answerQuality,
            ];
        }

        return $analysisResults;
    }

    public function checkRejectedQuestionSimilarity(int $importId): array
    {
        try {
            $rejectedQuestions = $this->databaseConnection->query("SELECT id, question_text_snapshot, exam_year FROM ai_rejected_questions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get rejected questions', [
                'error' => $exception->getMessage(),
            ]);
            return [];
        }

        $currentQuestions = $this->getQuestionAnalysisList($importId);
        $similarityWarnings = [];
        $duplicateThreshold = 0.35;

        foreach ($currentQuestions as $currentQuestion) {
            $currentQuestionId = (int)$currentQuestion['question_id'];
            $currentQuestionText = (string)$currentQuestion['question_text'];

            foreach ($rejectedQuestions as $rejectedQuestion) {
                $similarityPercentage = $this->calculateTextSimilarity($currentQuestionText, (string)$rejectedQuestion['question_text_snapshot']);
                if ($similarityPercentage >= ($duplicateThreshold * 100)) {
                    $similarityWarnings[] = [
                        'question_id' => $currentQuestionId,
                        'rejected_id' => (int)$rejectedQuestion['id'],
                        'reject_year' => (int)$rejectedQuestion['exam_year'],
                        'similarity' => $similarityPercentage,
                        'alert' => $similarityPercentage >= 55 ? 'Высокая похожесть - вопрос может быть дубликатом' : 'Умеренная похожесть - рекомендуется проверить',
                    ];
                }
            }
        }

        return $similarityWarnings;
    }

    public function analyzeRubricCompliance(int $importId): array
    {
        $questionList = $this->getQuestionAnalysisList($importId);
        $rubricAnalysisResults = [];

        foreach ($questionList as $questionItem) {
            $rubricId = $questionItem['rubric_id'] ?? null;
            if (!$rubricId) continue;

            $questionText = (string)$questionItem['question_text'];
            $criteriaText = (string)($questionItem['criteria_text'] ?? '');
            $dosageNote = (string)($questionItem['dose_theme_note'] ?? '');
            $isClearFormulation = mb_strlen($questionText, 'UTF-8') > 40;
            $dosageFocusAcceptable = $dosageNote === '' || preg_match('/\b(мг|мкг|доз|дозиров|препарат)/ui', $questionText);
            $complianceIssues = [];

            if (!$isClearFormulation) {
                $complianceIssues[] = 'Короткая формулировка вопроса - недостаточно контекста для ответа';
            }
            if ($dosageNote !== '' && !$dosageFocusAcceptable) {
                $complianceIssues[] = 'В рубрике избыточный акцент на дозировку лекарственных препаратов';
            }

            $criteriaMatchCount = 0;
            foreach (preg_split('/\R+/u', $criteriaText) ?: [] as $criteriaLine) {
                $criteriaLine = trim($criteriaLine);
                if ($criteriaLine !== '' && mb_stripos($questionText, mb_substr($criteriaLine, 0, 20, 'UTF-8'), 0, 'UTF-8') !== false) {
                    $criteriaMatchCount++;
                }
            }

            $rubricAnalysisResults[] = [
                'question_id' => (int)$questionItem['question_id'],
                'rubric_title' => $questionItem['rubric_title'],
                'criteria_overlap_hint' => 'Частичных совпадений с критериями: ' . $criteriaMatchCount,
                'issues' => $complianceIssues,
            ];
        }

        return $rubricAnalysisResults;
    }

    private function calculateTextSimilarity(string $firstText, string $secondText): float
    {
        $normalizedFirstText = preg_replace('/\s+/u', ' ', trim(mb_strtolower($firstText, 'UTF-8'))) ?? '';
        $normalizedSecondText = preg_replace('/\s+/u', ' ', trim(mb_strtolower($secondText, 'UTF-8'))) ?? '';
        if ($normalizedFirstText === '' || $normalizedSecondText === '') return 0.0;
        similar_text($normalizedFirstText, $normalizedSecondText, $similarityPercentage);
        return round($similarityPercentage, 1);
    }
}
