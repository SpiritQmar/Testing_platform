<?php

declare(strict_types=1);

require_once __DIR__ . '/TfIdfService.php';
require_once __DIR__ . '/EmbeddingsService.php';

final class QuestionDuplicateAnalyzer
{
    private PDO $databaseConnection;
    private TfIdfService $textSimilarityAnalyzer;
    private ?EmbeddingsService $semanticAnalyzer;

    public function __construct(PDO $databaseConnection, TfIdfService $textSimilarityAnalyzer, ?EmbeddingsService $semanticAnalyzer = null)
    {
        $this->databaseConnection = $databaseConnection;
        $this->textSimilarityAnalyzer = $textSimilarityAnalyzer;
        $this->semanticAnalyzer = $semanticAnalyzer;
    }

    public function findSimilarQuestions(int $examImportId, float $similarityThreshold = 0.85): array
    {
        $queryStatement = $this->databaseConnection->prepare("
            SELECT DISTINCT q.question_id, q.question_text, s.title as syllabus_title
            FROM hier_questions q
            LEFT JOIN ai_syllabus_topics s ON s.syllabus_topic_id = q.syllabus_topic_id
            WHERE q.question_text IS NOT NULL AND q.question_text != ''
            ORDER BY q.question_id
            LIMIT 500
        ");
        $queryStatement->execute();
        $examQuestions = $queryStatement->fetchAll(PDO::FETCH_ASSOC);

        if (count($examQuestions) < 2) {
            return ['duplicates' => [], 'total' => 0];
        }

        $similarQuestionPairs = [];
        $questionTexts = array_column($examQuestions, 'question_text');

        if ($this->semanticAnalyzer && $this->semanticAnalyzer->isEmbeddingsServiceAvailable()) {
            $semanticAnalysisResult = $this->semanticAnalyzer->deduplicateTexts($questionTexts, $similarityThreshold);
            if ($semanticAnalysisResult) {
                foreach ($semanticAnalysisResult['duplicates'] as $duplicatePair) {
                    $similarQuestionPairs[] = [
                        'question_id_1' => $examQuestions[$duplicatePair['index1']]['question_id'],
                        'question_id_2' => $examQuestions[$duplicatePair['index2']]['question_id'],
                        'text_1' => $examQuestions[$duplicatePair['index1']]['question_text'],
                        'text_2' => $examQuestions[$duplicatePair['index2']]['question_text'],
                        'similarity' => $duplicatePair['similarity'],
                        'method' => 'semantic_embeddings'
                    ];
                }
            }
        } else {
            $similarQuestionPairs = $this->analyzeTextSimilarity($examQuestions, $similarityThreshold);
        }

        return [
            'duplicates' => $similarQuestionPairs,
            'total' => count($similarQuestionPairs),
            'method' => $this->semanticAnalyzer && $this->semanticAnalyzer->isEmbeddingsServiceAvailable() ? 'semantic_embeddings' : 'tfidf_text_analysis'
        ];
    }

    private function analyzeTextSimilarity(array $examQuestions, float $similarityThreshold): array
    {
        $similarQuestionPairs = [];

        for ($firstIndex = 0; $firstIndex < count($examQuestions); $firstIndex++) {
            for ($secondIndex = $firstIndex + 1; $secondIndex < count($examQuestions); $secondIndex++) {
                $textSimilarityScore = $this->textSimilarityAnalyzer->calculateTfIdfSimilarity(
                    $examQuestions[$firstIndex]['question_text'] ?? '',
                    $examQuestions[$secondIndex]['question_text'] ?? '',
                    ''
                );

                if ($textSimilarityScore > $similarityThreshold) {
                    $similarQuestionPairs[] = [
                        'question_id_1' => $examQuestions[$firstIndex]['question_id'],
                        'question_id_2' => $examQuestions[$secondIndex]['question_id'],
                        'text_1' => $examQuestions[$firstIndex]['question_text'],
                        'text_2' => $examQuestions[$secondIndex]['question_text'],
                        'similarity' => round($textSimilarityScore, 3),
                        'method' => 'tfidf_text_analysis'
                    ];
                }
            }
        }

        return $similarQuestionPairs;
    }

    public function detectPreviouslyRejectedDuplicates(int $examImportId): array
    {
        $rejectedQuery = $this->databaseConnection->prepare("
            SELECT q.question_id, q.question_text, q.is_rejected, q.rejection_reason
            FROM hier_questions q
            WHERE q.is_rejected = 1
            ORDER BY q.question_id
        ");
        $rejectedQuery->execute();
        $rejectedExamQuestions = $rejectedQuery->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rejectedExamQuestions)) {
            return ['potential_duplicates' => [], 'total' => 0];
        }

        $currentQuery = $this->databaseConnection->prepare("
            SELECT q.question_id, q.question_text
            FROM hier_questions q
            WHERE q.is_rejected = 0 AND q.question_text IS NOT NULL
            ORDER BY q.question_id
        ");
        $currentQuery->execute();
        $activeExamQuestions = $currentQuery->fetchAll(PDO::FETCH_ASSOC);

        $potentialDuplicateWarnings = [];

        foreach ($rejectedExamQuestions as $rejectedQuestion) {
            foreach ($activeExamQuestions as $activeQuestion) {
                $similarityScore = $this->textSimilarityAnalyzer->calculateTfIdfSimilarity(
                    $rejectedQuestion['question_text'] ?? '',
                    $activeQuestion['question_text'] ?? '',
                    ''
                );

                if ($similarityScore > 0.7) {
                    $potentialDuplicateWarnings[] = [
                        'rejected_question_id' => $rejectedQuestion['question_id'],
                        'rejected_text' => $rejectedQuestion['question_text'],
                        'rejected_reason' => $rejectedQuestion['rejection_reason'],
                        'current_question_id' => $activeQuestion['question_id'],
                        'current_text' => $activeQuestion['question_text'],
                        'similarity' => round($similarityScore, 3),
                        'warning' => 'Внимание: обнаружен вопрос, похожий на ранее забракованный. Рекомендуется проверить его на соответствие критериям качества.'
                    ];
                }
            }
        }

        return [
            'potential_duplicates' => $potentialDuplicateWarnings,
            'total' => count($potentialDuplicateWarnings)
        ];
    }

    public function markQuestionAsDuplicate(int $originalQuestionId, int $duplicateQuestionId): bool
    {
        try {
            $updateStatement = $this->databaseConnection->prepare("
                UPDATE hier_questions
                SET is_duplicate = 1, duplicate_of = :original_question_id
                WHERE question_id = :duplicate_question_id
            ");
            $updateStatement->execute([
                ':original_question_id' => $originalQuestionId,
                ':duplicate_question_id' => $duplicateQuestionId
            ]);
            return true;
        } catch (Throwable $exception) {
            error_log('Ошибка при маркировке вопроса как дубликата: ' . $exception->getMessage());
            return false;
        }
    }
}
