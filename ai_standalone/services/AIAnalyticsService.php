<?php

declare(strict_types=1);

final class AIAnalyticsService
{
    public const DEMO_IMPORT_FILENAME = 'ai_synthetic_demo.seed';
    private ?int $currentImportId = null;
    private RuleClassifierService $ruleClassifier;
    private int $minStudentsForDiscrimination = 10;

    public function __construct(private PDO $databaseConnection, ?int $importId = null)
    {
        $this->currentImportId = $importId;
        $this->ruleClassifier = new RuleClassifierService($databaseConnection);
    }

    public function setMinStudentsForDiscrimination(int $value): void
    {
        $this->minStudentsForDiscrimination = max(2, $value);
    }

    public function getCurrentImportId(): ?int
    {
        return $this->currentImportId;
    }

    public function getDemoImportId(): ?int
    {
        $queryStatement = $this->databaseConnection->prepare("SELECT import_id FROM imports_log WHERE source_filename = :f ORDER BY import_id DESC LIMIT 1");
        $queryStatement->execute([':f' => self::DEMO_IMPORT_FILENAME]);
        $importIdValue = $queryStatement->fetchColumn();
        return $importIdValue !== false ? (int)$importIdValue : null;
    }

    public function getSyllabusTopics(): array
    {
        try {
            return $this->databaseConnection->query("SELECT * FROM ai_syllabus_topics ORDER BY syllabus_topic_id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Ошибка при получении тем силлабуса: ' . $exception->getMessage());
            return [];
        }
    }

    public function getQuestionAnalysisList(): array
    {
        try {
            $sqlQuery = "
                SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id, hq.max_score,
                       s.title AS syllabus_title, s.keywords AS syllabus_keywords,
                       AVG(r.received_score) as avg_score, COUNT(DISTINCT r.student_id) as attempts
                FROM hier_questions hq
                INNER JOIN ai_syllabus_topics s ON s.syllabus_topic_id = hq.syllabus_topic_id
                LEFT JOIN raw_exam_results r ON r.question_id = hq.question_id
                WHERE hq.syllabus_topic_id IS NOT NULL
                GROUP BY hq.question_id, s.syllabus_topic_id
                ORDER BY hq.question_id
            ";
            return $this->databaseConnection->query($sqlQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Ошибка при получении списка вопросов для анализа: ' . $exception->getMessage());
            return [];
        }
    }

    public function validateQuestionSyllabusAlignment(int $questionId): ?array
    {
        $queryStatement = $this->databaseConnection->prepare(
            "SELECT hq.*, s.keywords, s.title AS syllabus_title, s.topic_code,
                    AVG(r.received_score) as avg_score, COUNT(DISTINCT r.student_id) as attempts
             FROM hier_questions hq
             LEFT JOIN ai_syllabus_topics s ON s.syllabus_topic_id = hq.syllabus_topic_id
             LEFT JOIN raw_exam_results r ON r.question_id = hq.question_id
             WHERE hq.question_id = :id
             GROUP BY hq.question_id, s.syllabus_topic_id"
        );
        $queryStatement->execute([':id' => $questionId]);
        $questionData = $queryStatement->fetch(PDO::FETCH_ASSOC);
        if (!$questionData) return null;

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

        $keywordAlignmentScore = 0;
        if (!empty($questionData['syllabus_title']) && !empty($questionData['keywords'])) {
            $keywordAlignmentScore = 1.0;
        }

        return [
            'question_id' => $questionId,
            'syllabus_title' => $questionData['syllabus_title'] ?? '—',
            'topic_code' => $questionData['topic_code'] ?? '—',
            'keyword_coverage' => $keywordAlignmentScore * 100,
            'avg_score' => round($averageScore, 2),
            'attempts' => $studentAttempts,
            'issues' => $validationIssues,
            'status' => count($validationIssues) ? 'warning' : 'ok',
        ];
    }

    public function getValidationOverview(): array
    {
        $validationResults = [];
        foreach ($this->getQuestionAnalysisList() as $questionItem) {
            $questionId = (int)$questionItem['question_id'];
            $validationData = $this->validateQuestionSyllabusAlignment($questionId);
            if ($validationData) {
                $validationResults[] = $validationData;
            }
        }
        return $validationResults;
    }

    public function getQuestionQualityMetrics(): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return [];

        $sqlQuery = "
            SELECT r.question_id, COUNT(*) AS n, AVG(r.received_score) AS mean_score,
                   STDDEV_SAMP(r.received_score) AS std_score,
                   SUM(CASE WHEN r.received_score >= 60 THEN 1 ELSE 0 END) / COUNT(*) AS p_correct_proxy
            FROM raw_exam_results r
            WHERE r.import_id = :imp AND r.question_id IS NOT NULL AND r.received_score IS NOT NULL
            GROUP BY r.question_id
        ";
        $queryStatement = $this->databaseConnection->prepare($sqlQuery);
        $queryStatement->execute([':imp' => $importIdentifier]);
        $questionStatistics = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $maximumScoreMapping = [];
        try {
            $maxScoreQuery = $this->databaseConnection->query("SELECT question_id, max_score FROM ai_question_ai")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($maxScoreQuery as $questionId => $maxScore) {
                $maximumScoreMapping[(int)$questionId] = (float)$maxScore;
            }
        } catch (Throwable $exception) {
            error_log('Ошибка при получении максимальных баллов: ' . $exception->getMessage());
        }

        $qualityAnalysisResults = [];
        foreach ($questionStatistics as $statRow) {
            $questionIdentifier = (int)$statRow['question_id'];
            $maxPossibleScore = $maximumScoreMapping[$questionIdentifier] ?? 100.0;
            $meanAchievedScore = (float)$statRow['mean_score'];
            $successRatio = $meanAchievedScore / $maxPossibleScore;
            $qualityFlag = 'normal';
            $qualityReason = '';

            if ($successRatio >= 0.92) {
                $qualityFlag = 'too_easy';
                $qualityReason = 'Очень высокий средний балл — вопрос почти не дифференцирует студентов по уровню знаний';
            } elseif ($successRatio <= 0.35) {
                $qualityFlag = 'too_hard';
                $qualityReason = 'Низкий средний балл — проверьте формулировку вопроса и его соответствие учебной программе';
            }

            $qualityAnalysisResults[] = [
                'question_id' => $questionIdentifier,
                'n' => (int)$statRow['n'],
                'mean_score' => round($meanAchievedScore, 2),
                'std_score' => round((float)($statRow['std_score'] ?? 0), 2),
                'p_correct_proxy' => round((float)$statRow['p_correct_proxy'], 3),
                'difficulty_pct' => round(100 * (1 - $successRatio), 1),
                'flag' => $qualityFlag,
                'reason' => $qualityReason,
            ];
        }
        return $qualityAnalysisResults;
    }

    public function getDiscriminationIndex(): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return [];

        $questionsQuery = $this->databaseConnection->prepare("
            SELECT DISTINCT question_id
            FROM raw_exam_results
            WHERE import_id = :import_id AND question_id IS NOT NULL
        ");
        $questionsQuery->execute([':import_id' => $importIdentifier]);
        $questionList = $questionsQuery->fetchAll(PDO::FETCH_COLUMN);

        $discriminationResults = [];
        foreach ($questionList as $questionId) {
            $qualityMetrics = $this->ruleClassifier->calculateQuestionQualityMetrics((int)$questionId);

            if (($qualityMetrics['total_attempts'] ?? 0) < $this->minStudentsForDiscrimination) {
                continue;
            }

            $correlationValue = $qualityMetrics['discrimination_index'];
            $discriminationLabel = 'weak';
            if ($correlationValue >= 0.4) {
                $discriminationLabel = 'strong';
            } elseif ($correlationValue >= 0.2) {
                $discriminationLabel = 'medium';
            }

            $discriminationResults[] = [
                'question_id' => (int)$questionId,
                'discrimination' => $correlationValue,
                'label' => $discriminationLabel,
            ];
        }

        return $discriminationResults;
    }

    private function studentDisciplineTotals(int $importId): array
    {
        $queryStatement = $this->databaseConnection->prepare(
            "SELECT student_id, AVG(discipline_score) AS total
             FROM raw_exam_results
             WHERE import_id = :imp AND student_id IS NOT NULL
             GROUP BY student_id"
        );
        $queryStatement->execute([':imp' => $importId]);
        $studentScoreRows = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $studentScoreData = [];
        foreach ($studentScoreRows as $scoreRow) {
            $studentScoreData[] = ['student_id' => (int)$scoreRow['student_id'], 'total' => (float)$scoreRow['total']];
        }
        return $studentScoreData;
    }

    public function getItemTotalCorrelation(): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return [];

        $studentDisciplineScores = $this->studentDisciplineTotals($importIdentifier);
        $scoreMapping = [];
        foreach ($studentDisciplineScores as $studentData) {
            $scoreMapping[$studentData['student_id']] = $studentData['total'];
        }
        $queryStatement = $this->databaseConnection->prepare(
            "SELECT question_id, student_id, received_score
             FROM raw_exam_results
             WHERE import_id = :imp AND question_id IS NOT NULL AND student_id IS NOT NULL AND received_score IS NOT NULL"
        );
        $queryStatement->execute([':imp' => $importIdentifier]);
        $questionScorePairs = [];
        while ($scoreRow = $queryStatement->fetch(PDO::FETCH_ASSOC)) {
            $questionId = (int)$scoreRow['question_id'];
            $studentId = (int)$scoreRow['student_id'];
            if (!isset($scoreMapping[$studentId])) continue;
            $questionScorePairs[$questionId][] = ['x' => (float)$scoreRow['received_score'], 'y' => $scoreMapping[$studentId]];
        }
        $correlationResults = [];
        foreach ($questionScorePairs as $questionId => $scorePairs) {
            $correlationResults[] = [
                'question_id' => $questionId,
                'r' => round(self::pearson($scorePairs), 4),
                'flag' => abs(self::pearson($scorePairs)) < 0.12 ? 'low_corr' : 'ok',
            ];
        }
        return $correlationResults;
    }

    private static function pearson(array $pairs): float
    {
        $pairCount = count($pairs);
        if ($pairCount < 4) return 0.0;
        $meanX = $meanY = 0.0;
        foreach ($pairs as $pair) { $meanX += $pair['x']; $meanY += $pair['y']; }
        $meanX /= $pairCount; $meanY /= $pairCount;
        $numerator = $deviationX = $deviationY = 0.0;
        foreach ($pairs as $pair) {
            $varianceX = $pair['x'] - $meanX; $varianceY = $pair['y'] - $meanY;
            $numerator += $varianceX * $varianceY; $deviationX += $varianceX * $varianceX; $deviationY += $varianceY * $varianceY;
        }
        $denominator = sqrt($deviationX) * sqrt($deviationY);
        return $denominator > 1e-9 ? $numerator / $denominator : 0.0;
    }

    public function getStudentRiskPatterns(int $page = 1, int $perPage = 50): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return [];

        $offset = ($page - 1) * $perPage;

        $queryStatement = $this->databaseConnection->prepare(
            "SELECT student_id, AVG(received_score) AS avg_item,
                    MIN(received_score) AS min_item,
                    STDDEV_SAMP(received_score) AS std_item,
                    COUNT(*) AS n_items
             FROM raw_exam_results
             WHERE import_id = :imp AND student_id IS NOT NULL AND received_score IS NOT NULL
             GROUP BY student_id
             LIMIT :limit OFFSET :offset"
        );
        $queryStatement->bindValue(':imp', $importIdentifier);
        $queryStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $queryStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $queryStatement->execute();

        $studentDisciplineScores = $this->studentDisciplineTotals($importIdentifier);
        $scoreMapping = [];
        foreach ($studentDisciplineScores as $studentData) { $scoreMapping[$studentData['student_id']] = $studentData['total']; }
        $medianScore = self::calculateMedian(array_column($studentDisciplineScores, 'total'));

        $riskAnalysisResults = [];
        while ($resultRow = $queryStatement->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$resultRow['student_id'];
            $averageItemScore = (float)$resultRow['avg_item'];
            $minimumItemScore = (float)$resultRow['min_item'];
            $standardDeviationItem = (float)($resultRow['std_item'] ?? 0);
            $totalDisciplineScore = $scoreMapping[$studentId] ?? $averageItemScore;
            $riskPatternType = 'ok';
            $riskNotes = [];
            if ($averageItemScore < 45 && $totalDisciplineScore < $medianScore) {
                $riskPatternType = 'systemic';
                $riskNotes[] = 'Низкие баллы по большинству вопросов - возможны системные проблемы с подготовкой';
            } elseif ($minimumItemScore < 35 && $averageItemScore > 60) {
                $riskPatternType = 'spot';
                $riskNotes[] = 'Обнаружены значительные провалы по отдельным вопросам';
            }
            if ($standardDeviationItem < 4 && (int)$resultRow['n_items'] > 6) {
                $riskNotes[] = 'Очень малый разброс баллов по вопросам - подозрительный паттерн ответов';
            }
            $riskAnalysisResults[] = [
                'student_id' => $studentId,
                'avg_item' => round($averageItemScore, 2),
                'min_item' => round($minimumItemScore, 2),
                'discipline_total' => round($totalDisciplineScore, 2),
                'pattern_type' => $riskPatternType,
                'notes' => $riskNotes,
            ];
        }
        return $riskAnalysisResults;
    }

    public function getStudentRiskPatternsCount(): int
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return 0;

        $queryStatement = $this->databaseConnection->prepare(
            "SELECT COUNT(DISTINCT student_id) as total
             FROM raw_exam_results
             WHERE import_id = :imp AND student_id IS NOT NULL AND received_score IS NOT NULL"
        );
        $queryStatement->execute([':imp' => $importIdentifier]);
        $result = $queryStatement->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    private static function calculateMedian(array $values): float
    {
        if (!$values) return 0.0;
        sort($values);
        $valueCount = count($values);
        $middleIndex = (int)floor(($valueCount - 1) / 2);
        return $valueCount % 2 ? $values[$middleIndex] : ($values[$middleIndex] + $values[$middleIndex + 1]) / 2;
    }

    public function getNationalityPerformanceBreakdown(): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return [];
        try {
            $sqlQuery = "
                SELECT p.nationality_group, AVG(r.received_score) AS avg_score, COUNT(*) AS n
                FROM raw_exam_results r
                JOIN ai_student_profile p ON p.student_id = r.student_id
                WHERE r.import_id = :imp AND r.received_score IS NOT NULL
                GROUP BY p.nationality_group
            ";
            $queryStatement = $this->databaseConnection->prepare($sqlQuery);
            $queryStatement->execute([':imp' => $importIdentifier]);
            return $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Ошибка при анализе по национальности: ' . $exception->getMessage());
            return [];
        }
    }

    public function analyzeRubricCompliance(): array
    {
        $questionList = $this->getQuestionAnalysisList();
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
            if (!$isClearFormulation) $complianceIssues[] = 'Короткая формулировка вопроса - недостаточно контекста для ответа';
            if ($dosageNote !== '' && !$dosageFocusAcceptable) $complianceIssues[] = 'В рубрике избыточный акцент на дозировку лекарственных препаратов';
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

    public function checkRejectedQuestionSimilarity(): array
    {
        try {
            $rejectedQuestions = $this->databaseConnection->query("SELECT id, question_text_snapshot, exam_year FROM ai_rejected_questions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Ошибка при получении забракованных вопросов: ' . $exception->getMessage());
            return [];
        }
        $currentQuestions = $this->getQuestionAnalysisList();
        $similarityWarnings = [];
        foreach ($currentQuestions as $currentQuestion) {
            $currentQuestionId = (int)$currentQuestion['question_id'];
            $currentQuestionText = (string)$currentQuestion['question_text'];
            foreach ($rejectedQuestions as $rejectedQuestion) {
                $similarityPercentage = self::calculateTextSimilarity($currentQuestionText, (string)$rejectedQuestion['question_text_snapshot']);
                if ($similarityPercentage >= 35) {
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

    private static function calculateTextSimilarity(string $firstText, string $secondText): float
    {
        $normalizedFirstText = preg_replace('/\s+/u', ' ', trim(mb_strtolower($firstText, 'UTF-8'))) ?? '';
        $normalizedSecondText = preg_replace('/\s+/u', ' ', trim(mb_strtolower($secondText, 'UTF-8'))) ?? '';
        if ($normalizedFirstText === '' || $normalizedSecondText === '') return 0.0;
        similar_text($normalizedFirstText, $normalizedSecondText, $similarityPercentage);
        return round($similarityPercentage, 1);
    }

    public function getTeacherQualityRankings(): array
    {
        $qualityMetrics = $this->getQuestionQualityMetrics();
        $discriminationIndices = $this->getDiscriminationIndex();
        $discriminationMapping = [];
        foreach ($discriminationIndices as $discriminationRow) { $discriminationMapping[(int)$discriminationRow['question_id']] = $discriminationRow; }
        $rankingResults = [];
        foreach ($qualityMetrics as $metricRow) {
            $questionId = (int)$metricRow['question_id'];
            $discriminationScore = $discriminationMapping[$questionId]['discrimination'] ?? 0;
            $qualityScore = (float)$metricRow['p_correct_proxy'] * 40 + min(30, max(0, $discriminationScore)) + (100 - (float)$metricRow['difficulty_pct']) * 0.15;
            if ($metricRow['flag'] === 'too_easy' || $metricRow['flag'] === 'too_hard') $qualityScore -= 15;
            $recommendationText = 'Оставить вопрос без изменений.';
            if ($metricRow['flag'] === 'too_easy') $recommendationText = 'Рассмотреть удаление вопроса или его усложнение';
            if ($metricRow['flag'] === 'too_hard') $recommendationText = 'Упростить формулировку вопроса';
            if (($discriminationMapping[$questionId]['label'] ?? '') === 'weak') $recommendationText .= '. Слабая дискриминативная способность вопроса';
            $rankingResults[] = ['question_id' => $questionId, 'quality_score' => round($qualityScore, 1), 'recommendation' => $recommendationText];
        }
        usort($rankingResults, static fn ($x, $y) => $y['quality_score'] <=> $x['quality_score']);
        return $rankingResults;
    }

    public function suggestQuestionWeights(): array
    {
        $qualityMetrics = $this->getQuestionQualityMetrics();
        $weightSuggestions = [];
        $difficultyWeights = [];
        foreach ($qualityMetrics as $metricRow) {
            $difficultyWeights[] = max(0.15, min(1.5, (float)$metricRow['difficulty_pct'] / 100));
        }
        $totalWeight = array_sum($difficultyWeights) ?: 1.0;
        $weightIndex = 0;
        foreach ($qualityMetrics as $metricRow) {
            $calculatedWeight = round(100 * $difficultyWeights[$weightIndex] / $totalWeight, 2);
            $weightSuggestions[] = ['question_id' => (int)$metricRow['question_id'], 'weight_pct' => $calculatedWeight, 'note' => 'Вес пропорционален сложности вопроса'];
            $weightIndex++;
        }
        return $weightSuggestions;
    }

    public function getOpenAnswerAnalysis(): array
    {
        try {
            $queryStatement = $this->databaseConnection->query("SELECT o.*, q.expected_keywords, q.question_text FROM ai_open_answers o JOIN ai_question_ai q ON q.question_id = o.question_id");
            $answerRows = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Ошибка при анализе открытых ответов: ' . $exception->getMessage());
            return [];
        }
        $analysisResults = [];
        foreach ($answerRows as $answerRow) {
            $studentAnswer = mb_strtolower((string)$answerRow['answer_text'], 'UTF-8');
            $expectedKeywords = (string)($answerRow['expected_keywords'] ?? '');
            $keywordList = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', $expectedKeywords) ?: [])));
            $matchedKeywordCount = 0;
            foreach ($keywordList as $keyword) {
                if ($keyword !== '' && mb_strpos($studentAnswer, mb_strtolower($keyword, 'UTF-8'), 0, 'UTF-8') !== false) $matchedKeywordCount++;
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

    public function getDataIntegrityFlags(): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) return [];
        $integrityWarnings = [];

        $queryStatement = $this->databaseConnection->prepare(
            "SELECT student_id, COUNT(DISTINCT received_score) AS uvals, COUNT(*) AS n
             FROM raw_exam_results WHERE import_id = :imp AND received_score IS NOT NULL
             GROUP BY student_id HAVING n > 5 AND uvals <= 2"
        );
        $queryStatement->execute([':imp' => $importIdentifier]);
        while ($resultRow = $queryStatement->fetch(PDO::FETCH_ASSOC)) {
            $integrityWarnings[] = ['type' => 'repeat_pattern', 'student_id' => (int)$resultRow['student_id'], 'detail' => 'Мало уникальных оценок - подозрительный паттерн ответов'];
        }

        $secondQueryStatement = $this->databaseConnection->prepare(
            "SELECT question_id, COUNT(DISTINCT student_id) AS studs, MIN(received_score) AS mn, MAX(received_score) AS mx
             FROM raw_exam_results WHERE import_id = :imp AND received_score IS NOT NULL
             GROUP BY question_id HAVING studs > 10 AND (mx - mn) < 3"
        );
        $secondQueryStatement->execute([':imp' => $importIdentifier]);
        while ($resultRow = $secondQueryStatement->fetch(PDO::FETCH_ASSOC)) {
            $integrityWarnings[] = ['type' => 'flat_question', 'question_id' => (int)$resultRow['question_id'], 'detail' => 'Почти одинаковые ответы у всех студентов - возможная проблема с вопросом'];
        }

        return $integrityWarnings;
    }

    public function getExamReliabilityMetrics(): array
    {
        $importIdentifier = $this->currentImportId;
        if ($importIdentifier === null) {
            return ['cronbach_alpha' => 0, 'avg_inter_item_correlation' => 0, 'question_count' => 0, 'interpretation' => 'Нет данных'];
        }

        $examDataQuery = $this->databaseConnection->prepare("
            SELECT DISTINCT exam_id
            FROM raw_exam_results
            WHERE import_id = :import_id AND exam_id IS NOT NULL
            LIMIT 1
        ");
        $examDataQuery->execute([':import_id' => $importIdentifier]);
        $examRow = $examDataQuery->fetch(PDO::FETCH_ASSOC);

        if (!$examRow || !$examRow['exam_id']) {
            return ['cronbach_alpha' => 0, 'avg_inter_item_correlation' => 0, 'question_count' => 0, 'interpretation' => 'Нет данных об экзамене'];
        }

        $reliabilityData = $this->ruleClassifier->calculateExamReliability((int)$examRow['exam_id']);

        $alpha = $reliabilityData['cronbach_alpha'];
        $interpretation = 'Недостаточно данных';
        if ($alpha >= 0.9) {
            $interpretation = 'Отличная надежность';
        } elseif ($alpha >= 0.8) {
            $interpretation = 'Хорошая надежность';
        } elseif ($alpha >= 0.7) {
            $interpretation = 'Приемлемая надежность';
        } elseif ($alpha >= 0.6) {
            $interpretation = 'Сомнительная надежность';
        } elseif ($alpha > 0) {
            $interpretation = 'Ненадежный экзамен - требуется ревизия вопросов';
        }

        return [
            'cronbach_alpha' => $alpha,
            'avg_inter_item_correlation' => $reliabilityData['avg_inter_item_correlation'],
            'question_count' => $reliabilityData['question_count'],
            'interpretation' => $interpretation,
            'correlation_matrix' => $reliabilityData['correlation_matrix'] ?? []
        ];
    }
}
