<?php

declare(strict_types=1);

require_once __DIR__ . '/EmbeddingsService.php';

final class TfIdfService
{
    private PDO $databaseConnection;
    private ?EmbeddingsService $semanticAnalysisService;
    private ?int $currentImportId;

    public function __construct(PDO $databaseConnection, ?EmbeddingsService $semanticAnalysisService = null, ?int $importId = null)
    {
        $this->databaseConnection = $databaseConnection;
        $this->semanticAnalysisService = $semanticAnalysisService;
        $this->currentImportId = $importId;
    }

    public function analyzeQuestionSyllabusAlignment(): array
    {
        if ($this->currentImportId === null) {
            return [];
        }

        $alignmentResults = [];
        $queryStatement = $this->databaseConnection->prepare("
            SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id, hq.max_score,
                   s.title as syllabus_title, s.discipline_name, s.keywords as syllabus_keywords,
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
        ");
        $queryStatement->execute([
            ':source_import_id' => $this->currentImportId,
            ':stats_import_id' => $this->currentImportId,
        ]);
        $examQuestions = $queryStatement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($examQuestions as $questionData) {
            $averageScore = (float)($questionData['avg_score'] ?? 0);
            $maximumScore = (float)($questionData['max_score'] ?? 100);
            $attemptCount = (int)($questionData['attempts'] ?? 0);

            $textSimilarityScore = $this->calculateTfIdfSimilarity(
                $questionData['question_text'] ?? '',
                $questionData['syllabus_title'] ?? '',
                $questionData['syllabus_keywords'] ?? ''
            );

            $semanticSimilarityScore = 0;
            if ($this->semanticAnalysisService && $this->semanticAnalysisService->isEmbeddingsServiceAvailable()) {
                $semanticAnalysisResult = $this->semanticAnalysisService->getSimilarity(
                    $questionData['question_text'] ?? '',
                    trim(($questionData['syllabus_title'] ?? '') . ' ' . ($questionData['syllabus_keywords'] ?? ''))
                );
                if ($semanticAnalysisResult) {
                    $semanticSimilarityScore = $semanticAnalysisResult['similarity'] ?? 0;
                }
            }

            $combinedAlignmentScore = $textSimilarityScore * 0.6 + $semanticSimilarityScore * 0.4;

            $alignmentLevel = 'high';
            if ($combinedAlignmentScore < 0.15) {
                $alignmentLevel = 'low';
            } elseif ($combinedAlignmentScore < 0.4) {
                $alignmentLevel = 'medium';
            }

            $alignmentResults[] = [
                'question_id' => (int)$questionData['question_id'],
                'syllabus_topic_id' => (int)$questionData['syllabus_topic_id'],
                'syllabus_title' => $questionData['syllabus_title'],
                'discipline_name' => $questionData['discipline_name'],
                'alignment_analysis' => [
                    'text_similarity' => round($textSimilarityScore, 3),
                    'semantic_similarity' => round($semanticSimilarityScore, 3),
                    'combined_score' => round($combinedAlignmentScore, 3),
                    'alignment_level' => $alignmentLevel,
                    'syllabus_context' => [
                        'syllabus_title' => $questionData['syllabus_title'],
                        'discipline' => $questionData['discipline_name'],
                        'average_score' => round($averageScore, 2),
                        'student_attempts' => $attemptCount,
                    ],
                ],
            ];
        }
        return $alignmentResults;
    }

    public function calculateTfIdfSimilarity(string $questionText, string $syllabusTitle, string $syllabusKeywords): float
    {
        if (empty($questionText) || empty($syllabusTitle)) {
            return 0.0;
        }

        $questionWordTokens = $this->tokenizeText($questionText);
        $syllabusWordTokens = $this->tokenizeText($syllabusTitle . ' ' . $syllabusKeywords);

        if (empty($questionWordTokens) || empty($syllabusWordTokens)) {
            return 0.0;
        }

        $questionTermFrequency = $this->calculateTermFrequency($questionWordTokens);
        $syllabusTermFrequency = $this->calculateTermFrequency($syllabusWordTokens);

        $allUniqueTokens = array_unique(array_merge($questionWordTokens, $syllabusWordTokens));
        $documentFrequency = $this->calculateDocumentFrequency([$questionWordTokens, $syllabusWordTokens]);
        $inverseDocumentFrequency = $this->calculateInverseDocumentFrequency($documentFrequency, 2);

        $questionTfIdfVector = $this->calculateTfIdfVector($questionTermFrequency, $inverseDocumentFrequency, $allUniqueTokens);
        $syllabusTfIdfVector = $this->calculateTfIdfVector($syllabusTermFrequency, $inverseDocumentFrequency, $allUniqueTokens);

        return $this->calculateCosineSimilarity($questionTfIdfVector, $syllabusTfIdfVector);
    }

    private function tokenizeText(string $text): array
    {
        $normalizedText = mb_strtolower($text, 'UTF-8');
        $cleanedText = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalizedText);
        $wordTokens = preg_split('/\s+/', $cleanedText, -1, PREG_SPLIT_NO_EMPTY);

        $commonStopWords = ['и', 'в', 'на', 'с', 'для', 'по', 'из', 'к', 'а', 'the', 'and', 'of', 'to', 'in', 'for', 'with', 'on', 'at', 'from', 'by'];
        return array_values(array_filter($wordTokens, fn($token) => mb_strlen($token) > 2 && !in_array($token, $commonStopWords)));
    }

    private function calculateTermFrequency(array $tokens): array
    {
        $termFrequency = [];
        $totalTokenCount = count($tokens);
        foreach ($tokens as $token) {
            $termFrequency[$token] = ($termFrequency[$token] ?? 0) + 1;
        }
        foreach ($termFrequency as &$frequencyValue) {
            $frequencyValue = $frequencyValue / $totalTokenCount;
        }
        return $termFrequency;
    }

    private function calculateDocumentFrequency(array $documents): array
    {
        $documentFrequency = [];
        foreach ($documents as $document) {
            $uniqueDocumentTokens = array_unique($document);
            foreach ($uniqueDocumentTokens as $token) {
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }
        return $documentFrequency;
    }

    private function calculateInverseDocumentFrequency(array $documentFrequency, int $totalDocumentCount): array
    {
        $inverseDocumentFrequency = [];
        foreach ($documentFrequency as $token => $docFreq) {
            $inverseDocumentFrequency[$token] = log($totalDocumentCount / $docFreq);
        }
        return $inverseDocumentFrequency;
    }

    private function calculateTfIdfVector(array $termFrequency, array $inverseDocumentFrequency, array $allTokens): array
    {
        $tfidfVector = [];
        foreach ($allTokens as $token) {
            $tfidfVector[$token] = ($termFrequency[$token] ?? 0) * ($inverseDocumentFrequency[$token] ?? 0);
        }
        return array_values($tfidfVector);
    }

    private function calculateCosineSimilarity(array $vector1, array $vector2): float
    {
        $dotProduct = 0;
        $normVector1 = 0;
        $normVector2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $normVector1 += $vector1[$i] * $vector1[$i];
            $normVector2 += $vector2[$i] * $vector2[$i];
        }

        if ($normVector1 == 0 || $normVector2 == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normVector1) * sqrt($normVector2));
    }

    public function compareQuestionSimilarity(int $firstQuestionId, int $secondQuestionId): array
    {
        $queryStatement = $this->databaseConnection->prepare("
            SELECT question_id, question_text
            FROM hier_questions
            WHERE question_id IN (?, ?)
        ");
        $queryStatement->execute([$firstQuestionId, $secondQuestionId]);
        $questionData = $queryStatement->fetchAll(PDO::FETCH_ASSOC);

        if (count($questionData) < 2) {
            return ['similarity' => 0, 'error' => 'Один или оба вопроса не найдены в базе данных'];
        }

        $firstQuestion = $questionData[0];
        $secondQuestion = $questionData[1];

        $similarityScore = $this->calculateTfIdfSimilarity(
            $firstQuestion['question_text'] ?? '',
            $secondQuestion['question_text'] ?? '',
            ''
        );

        return [
            'question_id_1' => $firstQuestion['question_id'],
            'question_id_2' => $secondQuestion['question_id'],
            'similarity' => round($similarityScore, 3),
            'is_potential_duplicate' => $similarityScore > 0.85
        ];
    }
}
