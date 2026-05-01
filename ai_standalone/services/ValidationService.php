<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';

final class ValidationService
{
    private PDO $databaseConnection;
    private array $analyticsConfig;
    private Logger $logger;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
        $this->analyticsConfig = require __DIR__ . '/../config.php';
        $this->logger = getLogger();
    }

    public function validateQuestionSyllabusAlignment(int $questionId, int $importId): ?array
    {
        $queryStatement = $this->databaseConnection->prepare(
            "SELECT hq.*, s.keywords, s.title AS syllabus_title, s.topic_code,
                    AVG(r.received_score) as avg_score, COUNT(DISTINCT r.student_id) as attempts
              FROM hier_questions hq
              LEFT JOIN ai_syllabus_topics s ON s.syllabus_topic_id = hq.syllabus_topic_id
              INNER JOIN (
                  SELECT DISTINCT question_id
                  FROM raw_exam_results
                  WHERE import_id = :source_import_id
              ) source_r ON source_r.question_id = hq.question_id
              LEFT JOIN raw_exam_results r ON r.question_id = hq.question_id AND r.import_id = :stats_import_id
              WHERE hq.question_id = :id
              GROUP BY hq.question_id, s.syllabus_topic_id"
        );

        $queryStatement->execute([
            ':id' => $questionId,
            ':source_import_id' => $importId,
            ':stats_import_id' => $importId,
        ]);

        $questionData = $queryStatement->fetch(PDO::FETCH_ASSOC);
        if (!$questionData) {
            return null;
        }

        $averageScore = (float)($questionData['avg_score'] ?? 0);
        $maximumScore = (float)($questionData['max_score'] ?? 100);
        $studentAttempts = (int)($questionData['attempts'] ?? 0);
        $validationIssues = [];

        if ($studentAttempts > 0 && $averageScore < ($maximumScore * 0.3)) {
            $validationIssues[] = 'Низкий средний балл студентов - возможная проблема с формулировкой или сложностью';
        }
        if ($studentAttempts > 0 && $averageScore > ($maximumScore * 0.95)) {
            $validationIssues[] = 'Слишком высокий средний балл - вопрос может быть слишком легким';
        }
        if ($studentAttempts < 5) {
            $validationIssues[] = 'Недостаточно данных для надежной оценки (менее 5 попыток)';
        }
        if ($questionData['syllabus_topic_id'] === null) {
            $validationIssues[] = 'Вопрос не привязан к теме силлабуса - невозможно проверить соответствие программе';
        }

        $keywordAlignmentScore = $this->calculateKeywordCoverage(
            (string)($questionData['question_text'] ?? ''),
            (string)($questionData['syllabus_title'] ?? ''),
            (string)($questionData['keywords'] ?? '')
        );

        return [
            'question_id' => $questionId,
            'syllabus_title' => $questionData['syllabus_title'] ?? '—',
            'topic_code' => $questionData['topic_code'] ?? '—',
            'keyword_coverage' => round($keywordAlignmentScore * 100, 1),
            'avg_score' => round($averageScore, 2),
            'attempts' => $studentAttempts,
            'issues' => $validationIssues,
            'status' => count($validationIssues) ? 'warning' : 'ok',
        ];
    }

    public function getValidationOverview(int $importId): array
    {
        try {
            $sqlQuery = "
                SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id, hq.max_score,
                       s.title AS syllabus_title, s.keywords AS syllabus_keywords, s.topic_code,
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

            $questions = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $validationResults = [];

            foreach ($questions as $questionData) {
                $questionId = (int)$questionData['question_id'];
                $averageScore = (float)($questionData['avg_score'] ?? 0);
                $maximumScore = (float)($questionData['max_score'] ?? 100);
                $studentAttempts = (int)($questionData['attempts'] ?? 0);
                $validationIssues = [];

                if ($studentAttempts > 0 && $averageScore < ($maximumScore * 0.3)) {
                    $validationIssues[] = 'Низкий средний балл студентов - возможная проблема с формулировкой или сложностью';
                }
                if ($studentAttempts > 0 && $averageScore > ($maximumScore * 0.95)) {
                    $validationIssues[] = 'Слишком высокий средний балл - вопрос может быть слишком легким';
                }
                if ($studentAttempts < 5) {
                    $validationIssues[] = 'Недостаточно данных для надежной оценки (менее 5 попыток)';
                }

                $keywordAlignmentScore = $this->calculateKeywordCoverage(
                    (string)($questionData['question_text'] ?? ''),
                    (string)($questionData['syllabus_title'] ?? ''),
                    (string)($questionData['syllabus_keywords'] ?? '')
                );

                $validationResults[] = [
                    'question_id' => $questionId,
                    'syllabus_title' => $questionData['syllabus_title'] ?? '—',
                    'topic_code' => $questionData['topic_code'] ?? '—',
                    'keyword_coverage' => round($keywordAlignmentScore * 100, 1),
                    'avg_score' => round($averageScore, 2),
                    'attempts' => $studentAttempts,
                    'issues' => $validationIssues,
                    'status' => count($validationIssues) ? 'warning' : 'ok',
                ];
            }

            return $validationResults;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get validation overview', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    private function calculateKeywordCoverage(string $questionText, string $syllabusTitle, string $keywords): float
    {
        $referenceText = trim($syllabusTitle . ' ' . $keywords);
        $questionTokens = $this->tokenizeForAnalytics($questionText);
        $referenceTokens = $this->tokenizeForAnalytics($referenceText);

        if (!$questionTokens || !$referenceTokens) {
            return 0.0;
        }

        $overlapCount = count(array_intersect($referenceTokens, $questionTokens));
        return $overlapCount / count($referenceTokens);
    }

    private function tokenizeForAnalytics(string $text): array
    {
        $normalizedText = mb_strtolower($text, 'UTF-8');
        $cleanedText = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalizedText) ?? '';
        $tokens = preg_split('/\s+/', $cleanedText, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $minTokenLength = $this->analyticsConfig['coefficients']['keyword']['min_token_length'];
        $stopWords = ['и', 'в', 'на', 'с', 'для', 'по', 'из', 'к', 'а', 'the', 'and', 'of', 'to', 'in', 'for', 'with', 'on', 'at', 'from', 'by'];

        $filteredTokens = array_filter($tokens, static fn (string $token): bool =>
            mb_strlen($token, 'UTF-8') > $minTokenLength && !in_array($token, $stopWords, true)
        );

        return array_values(array_unique($filteredTokens));
    }
}
