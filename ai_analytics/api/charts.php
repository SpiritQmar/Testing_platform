<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

try {
    $cfg = require __DIR__ . '/../config.php';
    $easyT = (float)($cfg['coefficients']['quality']['easy_threshold'] ?? 0.8);
    $hardT = (float)($cfg['coefficients']['quality']['hard_threshold'] ?? 0.3);

    $imports = $pdo->query(
        "SELECT import_id FROM imports_log WHERE import_type='exam_results_upload' AND rows_imported>0 ORDER BY import_id DESC LIMIT 1"
    )->fetchAll(PDO::FETCH_COLUMN);
    $importId = isset($imports[0]) ? (int)$imports[0] : null;

    $qualityData = ['easy' => 0, 'normal' => 0, 'hard' => 0];
    $passFailData = ['pass' => 0, 'fail' => 0];
    $riskData = ['ok' => 0, 'systemic' => 0, 'spot' => 0];

    if ($importId) {
        $maxScores = $pdo->prepare(
            "SELECT hq.question_id, hq.max_score FROM hier_questions hq
             INNER JOIN (SELECT DISTINCT question_id FROM raw_exam_results WHERE import_id = :imp) r ON r.question_id = hq.question_id"
        );
        $maxScores->execute([':imp' => $importId]);
        $maxMap = [];
        foreach ($maxScores->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $maxMap[(int)$row['question_id']] = (float)($row['max_score'] ?: 100);
        }

        $qStats = $pdo->prepare(
            "SELECT question_id, AVG(received_score) AS mean_score FROM raw_exam_results
             WHERE import_id = :imp AND received_score IS NOT NULL GROUP BY question_id"
        );
        $qStats->execute([':imp' => $importId]);
        foreach ($qStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $qid  = (int)$row['question_id'];
            $mean = (float)$row['mean_score'];
            $max  = $maxMap[$qid] ?? 100.0;
            $ratio = $max > 0 ? $mean / $max : 0.0;
            if ($ratio >= $easyT) $qualityData['easy']++;
            elseif ($ratio <= $hardT) $qualityData['hard']++;
            else $qualityData['normal']++;
        }

        $passRow = $pdo->prepare(
            "SELECT SUM(CASE WHEN received_score >= 60 THEN 1 ELSE 0 END) AS pass_cnt,
                    SUM(CASE WHEN received_score < 60 THEN 1 ELSE 0 END) AS fail_cnt
             FROM raw_exam_results WHERE import_id = :imp AND received_score IS NOT NULL"
        );
        $passRow->execute([':imp' => $importId]);
        $pr = $passRow->fetch(PDO::FETCH_ASSOC) ?: [];
        $passFailData = ['pass' => (int)($pr['pass_cnt'] ?? 0), 'fail' => (int)($pr['fail_cnt'] ?? 0)];

        $sysAvg = (float)($cfg['coefficients']['risk']['systemic_avg'] ?? 50.0);
        $spotMin = (float)($cfg['coefficients']['risk']['spot_min'] ?? 20.0);
        $studentStats = $pdo->prepare(
            "SELECT student_id, AVG(received_score) AS avg_s, MIN(received_score) AS min_s
             FROM raw_exam_results WHERE import_id = :imp AND received_score IS NOT NULL GROUP BY student_id"
        );
        $studentStats->execute([':imp' => $importId]);
        foreach ($studentStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $avg = (float)$row['avg_s'];
            $min = (float)$row['min_s'];
            if ($avg < $sysAvg) $riskData['systemic']++;
            elseif ($min < $spotMin) $riskData['spot']++;
            else $riskData['ok']++;
        }

    }

    $blue  = ['#3b82f6', '#60a5fa', '#93c5fd'];
    $green = ['#10b981', '#34d399', '#6ee7b7'];
    $red   = ['#ef4444', '#f87171', '#fca5a5'];

    $qualityChart = [
        'labels'   => ['Лёгкие', 'Нормальные', 'Сложные'],
        'datasets' => [[
            'data'            => [$qualityData['easy'], $qualityData['normal'], $qualityData['hard']],
            'backgroundColor' => [$green[0], $blue[0], $red[0]],
        ]],
    ];

    $correlationChart = [
        'labels'   => ['Сдали', 'Не сдали'],
        'datasets' => [[
            'data'            => [$passFailData['pass'], $passFailData['fail']],
            'backgroundColor' => [$green[0], $red[0]],
        ]],
    ];

    $riskChart = [
        'labels'   => ['Норма', 'Системный риск', 'Точечный риск'],
        'datasets' => [[
            'data'            => [$riskData['ok'], $riskData['systemic'], $riskData['spot']],
            'backgroundColor' => [$green[0], $red[0], '#f59e0b'],
        ]],
    ];

    $valOk = 0; $valWarn = 0;
    if ($importId) {
        try {
            $valQ = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM question_validation_cache WHERE import_id = :imp GROUP BY status");
            $valQ->execute([':imp' => $importId]);
            foreach ($valQ->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['status'] === 'ok') $valOk = (int)$row['cnt'];
                else $valWarn += (int)$row['cnt'];
            }
        } catch (Throwable $e) {}
    }
    if ($valOk === 0 && $valWarn === 0 && $importId) {
        $warnQ = $pdo->prepare(
            "SELECT COUNT(DISTINCT r.question_id) AS warn_cnt
             FROM raw_exam_results r
             INNER JOIN hier_questions hq ON hq.question_id = r.question_id
             WHERE r.import_id = :imp AND r.received_score IS NOT NULL
             GROUP BY r.question_id
             HAVING COUNT(DISTINCT r.student_id) < 5
                 OR AVG(r.received_score) < (COALESCE(MAX(hq.max_score),100) * 0.3)
                 OR AVG(r.received_score) > (COALESCE(MAX(hq.max_score),100) * 0.95)"
        );
        try {
            $warnQ->execute([':imp' => $importId]);
            $valWarn = (int)$warnQ->rowCount();
            $totalQ = $qualityData['easy'] + $qualityData['normal'] + $qualityData['hard'];
            $valOk = max(0, $totalQ - $valWarn);
        } catch (Throwable $e) {}
    }
    $validationChart = [
        'labels'   => ['Корректно (ok)', 'Предупреждение (warning)'],
        'datasets' => [[
            'data'            => [$valOk, $valWarn],
            'backgroundColor' => [$green[0], '#f59e0b'],
        ]],
    ];

    $semLabels = []; $semData = []; $semColors = [];
    if ($importId) {
        try {
            $semQ = $pdo->prepare(
                "SELECT alignment_level, COUNT(*) AS cnt
                 FROM question_semantic_cache
                 WHERE import_id = :imp
                 GROUP BY alignment_level"
            );
            $semQ->execute([':imp' => $importId]);
            $semMap = [];
            foreach ($semQ->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $semMap[$row['alignment_level']] = (int)$row['cnt'];
            }

            if (empty($semMap)) {
                $kvQ = $pdo->prepare(
                    "SELECT keyword_coverage FROM question_validation_cache WHERE import_id = :imp"
                );
                $kvQ->execute([':imp' => $importId]);
                foreach ($kvQ->fetchAll(PDO::FETCH_COLUMN) as $cov) {
                    $cov = (float)$cov;
                    if ($cov >= 0.67)      $semMap['high']   = ($semMap['high']   ?? 0) + 1;
                    elseif ($cov >= 0.33)  $semMap['medium'] = ($semMap['medium'] ?? 0) + 1;
                    else                   $semMap['low']    = ($semMap['low']    ?? 0) + 1;
                }
            }

            $levelCfg = [
                'high'   => ['Высокое сходство',  $green[0]],
                'medium' => ['Среднее сходство',   '#f59e0b'],
                'low'    => ['Низкое сходство',    $red[0]],
            ];
            foreach ($levelCfg as $lvl => [$lbl, $clr]) {
                if (isset($semMap[$lvl])) {
                    $semLabels[] = $lbl;
                    $semData[]   = $semMap[$lvl];
                    $semColors[] = $clr;
                }
            }
        } catch (Throwable $e) {}
    }
    $semanticChart = $semLabels ? [
        'labels'   => $semLabels,
        'datasets' => [[
            'label'           => 'Количество вопросов',
            'data'            => $semData,
            'backgroundColor' => $semColors,
        ]],
    ] : null;

    $total = max(1, array_sum($qualityData));
    $passTotal = $passFailData['pass'] + $passFailData['fail'];
    $criteriaChart = [
        'labels'   => ['Лёгкие %', 'Нормальные %', 'Сложные %', 'Сдали %', 'Риск норма %'],
        'datasets' => [[
            'label'           => 'Показатели',
            'data'            => [
                round($qualityData['easy']   / $total * 100, 1),
                round($qualityData['normal'] / $total * 100, 1),
                round($qualityData['hard']   / $total * 100, 1),
                $passTotal > 0 ? round($passFailData['pass'] / $passTotal * 100, 1) : 0,
                ($riskData['ok'] + $riskData['systemic'] + $riskData['spot']) > 0
                    ? round($riskData['ok'] / ($riskData['ok'] + $riskData['systemic'] + $riskData['spot']) * 100, 1) : 0,
            ],
            'backgroundColor' => 'rgba(59,130,246,0.3)',
            'borderColor'     => $blue[0],
        ]],
    ];

    $ansLangs = []; $ansScores = []; $ansPlagRates = [];
    if ($importId) {
        try {
            $ansQ = $pdo->prepare(
                "SELECT sa.language,
                        COUNT(*) AS answers,
                        ROUND(AVG(sa.final_score), 2) AS avg_score,
                        ROUND(COUNT(CASE WHEN sa.plagiarism_penalty > 0 THEN 1 END) / COUNT(*) * 100, 2) AS plagiarism_rate
                 FROM student_answers sa
                 JOIN work_mapping wm ON wm.work_id = sa.work_id
                 WHERE wm.import_id = :imp AND sa.language IS NOT NULL
                 GROUP BY sa.language
                 ORDER BY answers DESC"
            );
            $ansQ->execute([':imp' => $importId]);
            foreach ($ansQ->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ansLangs[]     = ucfirst((string)$row['language']);
                $ansScores[]    = (float)$row['avg_score'];
                $ansPlagRates[] = (float)$row['plagiarism_rate'];
            }
        } catch (Throwable $e) {}
        if (!$ansLangs) {
            try {
                $ansQ2 = $pdo->prepare(
                    "SELECT sa.language,
                            COUNT(*) AS answers,
                            ROUND(AVG(sa.final_score), 2) AS avg_score,
                            ROUND(COUNT(CASE WHEN sa.plagiarism_penalty > 0 THEN 1 END) / COUNT(*) * 100, 2) AS plagiarism_rate
                     FROM student_answers sa
                     WHERE sa.language IS NOT NULL
                     GROUP BY sa.language
                     ORDER BY answers DESC"
                );
                $ansQ2->execute();
                foreach ($ansQ2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $ansLangs[]     = ucfirst((string)$row['language']);
                    $ansScores[]    = (float)$row['avg_score'];
                    $ansPlagRates[] = (float)$row['plagiarism_rate'];
                }
            } catch (Throwable $e) {}
        }
    }
    $plagiarismChart = $ansLangs ? [
        'labels'   => $ansLangs,
        'datasets' => [
            [
                'label'           => 'Средний балл',
                'data'            => $ansScores,
                'backgroundColor' => array_fill(0, count($ansLangs), $blue[0]),
            ],
            [
                'label'           => '% плагиата',
                'data'            => $ansPlagRates,
                'backgroundColor' => array_fill(0, count($ansLangs), $red[0]),
            ],
        ],
    ] : null;

    echo json_encode([
        'success'     => true,
        'quality'     => $qualityChart,
        'correlation' => $correlationChart,
        'studentRisk' => $riskChart,
        'validation'  => $validationChart,
        'semantic'    => $semanticChart,
        'criteria'    => $criteriaChart,
        'plagiarism'  => $plagiarismChart,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
