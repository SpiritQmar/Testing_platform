<?php
$_perf_start = microtime(true);
if (session_status() === PHP_SESSION_NONE) session_start();

define('PER_PAGE', 50);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/AIAnalyticsService.php';
require_once __DIR__ . '/../services/TfIdfService.php';
require_once __DIR__ . '/../services/ValidationService.php';
require_once __DIR__ . '/../services/EmbeddingsService.php';

$currentLang = $_SESSION['ai_lang'] ?? 'ru';
$langFile = __DIR__ . '/../lang/' . $currentLang . '.json';
$translations = file_exists($langFile) ? (json_decode(file_get_contents($langFile), true) ?? []) : [];
if (!function_exists('t')) {
    function t(string $k): string { global $translations; return $translations[$k] ?? $k; }
}
if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$imports = [];
try {
    $imports = $pdo->query("SELECT import_id FROM imports_log WHERE import_type='exam_results_upload' AND rows_imported>0 ORDER BY import_id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$importId = null;
if (isset($_SESSION['ai_analytics_import_id'])) {
    foreach ($imports as $imp) {
        if ((int)$imp['import_id'] === (int)$_SESSION['ai_analytics_import_id']) {
            $importId = (int)$imp['import_id'];
            break;
        }
    }
}
if ($importId === null && !empty($imports)) {
    $importId = (int)$imports[0]['import_id'];
}

$ai = new AIAnalyticsService($pdo, $importId);

$minStudents = $_SESSION['min_students_discrimination'] ?? 10;
$ai->setMinStudentsForDiscrimination($minStudents);

$section       = preg_replace('/[^a-z\-]/', '', $_GET['section'] ?? 'validation');
$sortColumn    = $_GET["{$section}_sort"] ?? null;
$sortDirection = strtoupper($_GET["{$section}_dir"] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$page          = max(1, (int)($_GET["{$section}_page"] ?? 1));
$perPage       = PER_PAGE;
$filterVal     = $_GET["{$section}_filter"] ?? 'all';
if (!in_array($filterVal, ['all','success','warning','danger'])) $filterVal = 'all';

$_perf_init_ms = round((microtime(true) - $_perf_start) * 1000);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Perf-Init-Ms: ' . $_perf_init_ms);

function renderPaginationAjax(string $sec, int $cur, int $total, int $items, ?string $sort, ?string $dir, string $filter = 'all'): string {
    if ($total <= 1) return '';
    $sortParams   = $sort ? "&{$sec}_sort={$sort}&{$sec}_dir={$dir}" : '';
    $filterParam  = $filter !== 'all' ? "&{$sec}_filter={$filter}" : '';
    $extra        = $sortParams . $filterParam;
    $html = '<div class="pagination">';
    if ($cur > 1) $html .= "<a href=\"?section={$sec}&{$sec}_page=" . ($cur-1) . $extra . "\"><button>&laquo;</button></a>";
    for ($i = 1; $i <= $total; $i++) {
        if ($i == $cur) { $html .= "<button class=\"active\">{$i}</button>"; }
        elseif (abs($i - $cur) <= 2 || $i == 1 || $i == $total) { $html .= "<a href=\"?section={$sec}&{$sec}_page={$i}{$extra}\"><button>{$i}</button></a>"; }
        elseif (abs($i - $cur) == 3) { $html .= '<span>...</span>'; }
    }
    if ($cur < $total) $html .= "<a href=\"?section={$sec}&{$sec}_page=" . ($cur+1) . $extra . "\"><button>&raquo;</button></a>";
    $html .= '</div>';
    $html .= "<p class=\"text-center text-muted small\">" . t('page') . " {$cur} " . t('of') . " {$total} ({$items} " . t('records') . ")</p>";
    return $html;
}

function sortIcon(string $col, ?string $active, ?string $dir): string {
    if ($col === $active) return '<span class="sort-icon active">' . ($dir === 'ASC' ? '▲' : '▼') . '</span>';
    return '<span class="sort-icon">⇅</span>';
}

function sortHref(string $sec, string $col, ?string $active, ?string $dir, string $filter = 'all'): string {
    $newDir      = ($col === $active && $dir === 'ASC') ? 'DESC' : 'ASC';
    $filterParam = $filter !== 'all' ? "&{$sec}_filter={$filter}" : '';
    return "?section={$sec}&{$sec}_sort={$col}&{$sec}_dir={$newDir}{$filterParam}";
}

function sortTh(string $sec, string $col, string $label, ?string $active, ?string $dir, string $filter = 'all'): string {
    $href = sortHref($sec, $col, $active, $dir, $filter);
    $icon = sortIcon($col, $active, $dir);
    return "<th><a href=\"{$href}\">{$label} {$icon}</a></th>";
}

function applyBadgeFilter(array $data, string $filterVal, string ...$fields): array {
    if ($filterVal === 'all') return $data;
    return array_values(array_filter($data, function($r) use ($filterVal, $fields) {
        foreach ($fields as $f) { if (($r[$f] ?? '') === $filterVal) return true; }
        return false;
    }));
}

if ($importId === null) {
    echo '<p class="text-muted">' . t('select_import') . '</p>';
    exit;
}

switch ($section) {

case 'validation':
    $embSvc = new EmbeddingsService();
    $tfidf  = new TfIdfService($pdo, $embSvc, $importId);
    $valSvc = new ValidationService($pdo, $embSvc, $tfidf);
    $raw    = $valSvc->getValidationOverview($importId, true);

    $data = [];
    if (!empty($raw) && is_array($raw)) {
        foreach ($raw as $v) {
            try {
                $issues = [];
                if (!empty($v['issues']) && is_array($v['issues'])) {
                    foreach ($v['issues'] as $iss) $issues[] = is_array($iss) ? ($iss['message'] ?? '') : $iss;
                }
                $sc = ($v['status'] ?? 'ok') === 'ok' ? 'success' : (($v['status'] ?? '') === 'warning' ? 'warning' : 'danger');
                $data[] = [
                    'question_id'   => $v['question_id'] ?? '—',
                    'syllabus_title'=> $v['syllabus_title'] ?? '',
                    'avg_score'     => $v['avg_score'] ?? '—',
                    'attempts'      => $v['attempts'] ?? '—',
                    'status_class'  => $sc,
                    'status'        => $v['status'] ?? 'ok',
                    'issues'        => implode(', ', $issues),
                ];
            } catch (Throwable $e) {}
        }
    }

    if ($sortColumn) {
        $valid = ['question_id','syllabus_title','avg_score','attempts','status'];
        if (in_array($sortColumn, $valid)) {
            $d = $sortDirection === 'DESC' ? -1 : 1;
            usort($data, fn($a,$b) => ($a[$sortColumn] <=> $b[$sortColumn]) * $d);
        }
    }
    $data  = applyBadgeFilter($data, $filterVal, 'status_class');
    $total = count($data); $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($page, $pages);
    $paged = array_slice($data, ($page-1)*$perPage, $perPage);
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?= sortTh('validation','question_id',t('th_id'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('validation','syllabus_title',t('th_topic'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('validation','avg_score',t('th_avg_score'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('validation','attempts',t('th_attempts'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('validation','status',t('th_status'),$sortColumn,$sortDirection,$filterVal) ?>
          <th><?= t('th_issues') ?></th>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="6" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['status_class'] ?>">
              <td><strong><?= h($r['question_id']) ?></strong></td>
              <td><?= h($r['syllabus_title']) ?></td>
              <td><?= h($r['avg_score']) ?></td>
              <td><?= h($r['attempts']) ?></td>
              <td><span class="badge badge-soft <?= $r['status_class'] ?>"><?= h($r['status']) ?></span></td>
              <td><?= h($r['issues']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('validation', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

case 'quality':
    $discMap = [];
    try {
        foreach ($ai->getDiscriminationIndex() as $d) $discMap[(int)$d['question_id']] = $d;
    } catch (Throwable $e) {}
    $disciplineMap = [];
    try {
        $st = $pdo->query("SELECT DISTINCT question_id, discipline_name FROM raw_exam_results");
        while ($r = $st->fetch()) $disciplineMap[$r['question_id']] = $r['discipline_name'];
    } catch (Throwable $e) {}

    $metrics = $ai->getQuestionQualityMetrics($sortColumn, $sortDirection);
    $data = [];
    foreach (($metrics ?: []) as $m) {
        $disc = $discMap[$m['question_id']] ?? null;
        $fc   = $m['flag'] === 'normal' ? 'success' : ($m['flag'] === 'too_easy' ? 'warning' : 'danger');
        $ft   = $m['flag'] === 'normal' ? 'Норма' : ($m['flag'] === 'too_easy' ? 'Легкий' : 'Сложный');
        $data[] = [
            'question_id'     => $m['question_id'],
            'discipline'      => $disciplineMap[$m['question_id']] ?? '',
            'n'               => $m['n'],
            'mean_score'      => $m['mean_score'],
            'p_correct_proxy' => $m['p_correct_proxy'],
            'difficulty_pct'  => $m['difficulty_pct'],
            'flag_class'      => $fc, 'flag_text' => $ft,
            'discrimination'  => $disc ? $disc['discrimination'].' ('.$disc['label'].')' : '—',
            'reason'          => $m['reason'],
        ];
    }
    if ($sortColumn && in_array($sortColumn, ['discipline','flag_text','discrimination','reason'])) {
        $d = $sortDirection === 'DESC' ? -1 : 1;
        usort($data, function($a,$b) use ($sortColumn,$d) {
            if ($sortColumn === 'flag_text') {
                $ord = ['Сложный'=>3,'Легкий'=>2,'Норма'=>1];
                return (($ord[$a[$sortColumn]]??0) <=> ($ord[$b[$sortColumn]]??0)) * $d;
            }
            return ($a[$sortColumn] <=> $b[$sortColumn]) * $d;
        });
    }
    $data  = applyBadgeFilter($data, $filterVal, 'flag_class');
    $total = count($data); $pages = max(1,(int)ceil($total/$perPage));
    $page  = min($page,$pages);
    $paged = array_slice($data,($page-1)*$perPage,$perPage);
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?= sortTh('quality','question_id',t('th_id'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','discipline','Discipline',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','n',t('th_n'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','mean_score',t('th_avg'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','p_correct_proxy',t('th_success_pct'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','difficulty_pct',t('th_difficulty_pct'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','flag_text',t('th_flag'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','discrimination',t('th_discrimination'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('quality','reason',t('th_reason'),$sortColumn,$sortDirection,$filterVal) ?>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="9" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['flag_class'] ?>">
              <td><strong><?= h($r['question_id']) ?></strong></td>
              <td><?= h($r['discipline']) ?></td>
              <td><?= h($r['n']) ?></td>
              <td><?= h($r['mean_score']) ?></td>
              <td><?= h($r['p_correct_proxy']) ?></td>
              <td><?= h($r['difficulty_pct']) ?>%</td>
              <td><span class="badge badge-soft <?= $r['flag_class'] ?>"><?= h($r['flag_text']) ?></span></td>
              <td><?= h($r['discrimination']) ?></td>
              <td><?= h($r['reason']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('quality', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

case 'correlation':
    $corr = $ai->getItemTotalCorrelation($sortColumn, $sortDirection);
    $data = [];
    foreach (($corr ?: []) as $c) {
        $fc   = $c['flag'] === 'ok' ? 'success' : 'warning';
        $flbl = $c['flag'] === 'ok' ? 'Хорошая' : 'Слабая';
        $data[] = ['question_id'=>$c['question_id'],'r'=>$c['r'],'flag_class'=>$fc,'flag'=>$flbl];
    }
    if ($sortColumn === 'flag') {
        $d = $sortDirection === 'DESC' ? -1 : 1;
        usort($data, fn($a,$b) => ($a['flag'] <=> $b['flag']) * $d);
    }
    $data  = applyBadgeFilter($data, $filterVal, 'flag_class');
    $total = count($data); $pages = max(1,(int)ceil($total/$perPage));
    $page  = min($page,$pages);
    $paged = array_slice($data,($page-1)*$perPage,$perPage);
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?= sortTh('correlation','question_id',t('th_id'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('correlation','r',t('th_coefficient'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('correlation','flag',t('th_flag'),$sortColumn,$sortDirection,$filterVal) ?>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="3" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['flag_class'] ?>">
              <td><strong><?= h($r['question_id']) ?></strong></td>
              <td><?= h($r['r']) ?></td>
              <td><span class="badge badge-soft <?= $r['flag_class'] ?>"><?= h($r['flag']) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('correlation', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

case 'students':
    $allPatterns = $ai->getStudentRiskPatterns(1, 99999, $sortColumn, $sortDirection);
    $data = [];
    $ptLabels = ['ok' => 'Норма', 'systemic' => 'Системный риск', 'spot' => 'Локальный провал'];
    foreach (($allPatterns ?: []) as $p) {
        $tc  = $p['pattern_type']==='ok' ? 'success' : ($p['pattern_type']==='systemic' ? 'danger' : 'warning');
        $lbl = $ptLabels[$p['pattern_type']] ?? $p['pattern_type'];
        $data[] = ['student_id'=>$p['student_id'],'avg_item'=>$p['avg_item'],'min_item'=>$p['min_item'],
                   'discipline_total'=>$p['discipline_total'],'type_class'=>$tc,'pattern_type'=>$lbl,
                   'notes'=>implode(', ',$p['notes'])];
    }
    $data  = applyBadgeFilter($data, $filterVal, 'type_class');
    $total = count($data); $pages = max(1,(int)ceil($total/$perPage));
    $page  = min($page,$pages);
    $paged = array_slice($data,($page-1)*$perPage,$perPage);
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?= sortTh('students','student_id',t('th_id'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('students','avg_item',t('th_avg'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('students','min_item',t('th_min'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('students','discipline_total',t('th_total'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('students','pattern_type',t('th_type'),$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('students','notes',t('th_notes'),$sortColumn,$sortDirection,$filterVal) ?>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="6" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['type_class'] ?>">
              <td><strong><?= h($r['student_id']) ?></strong></td>
              <td><?= h($r['avg_item']) ?></td>
              <td><?= h($r['min_item']) ?></td>
              <td><?= h($r['discipline_total']) ?></td>
              <td><span class="badge badge-soft <?= $r['type_class'] ?>"><?= h($r['pattern_type']) ?></span></td>
              <td><?= h($r['notes']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('students', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

case 'semantic':
    $sem  = $ai->getSemanticAnalysis();
    $data = [];
    foreach (($sem ?: []) as $s) {
        $sc = $s['alignment_level']==='high' ? 'success' : ($s['alignment_level']==='medium' ? 'warning' : 'danger');
        $data[] = ['question_id'=>$s['question_id'],'syllabus_title'=>$s['syllabus_title'],
                   'discipline_name'=>$s['discipline_name'],'average_score'=>$s['avg_score'],
                   'status_class'=>$sc,'alignment_level'=>$s['alignment_level']];
    }
    if ($sortColumn && in_array($sortColumn,['question_id','syllabus_title','discipline_name','average_score','alignment_level'])) {
        $d = $sortDirection === 'DESC' ? -1 : 1;
        usort($data, fn($a,$b) => ($a[$sortColumn] <=> $b[$sortColumn]) * $d);
    }
    $data  = applyBadgeFilter($data, $filterVal, 'status_class');
    $total = count($data); $pages = max(1,(int)ceil($total/$perPage));
    $page  = min($page,$pages);
    $paged = array_slice($data,($page-1)*$perPage,$perPage);
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?= sortTh('semantic','question_id','ID',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('semantic','syllabus_title','Тема',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('semantic','discipline_name','Дисциплина',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('semantic','average_score','Средний балл',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('semantic','alignment_level','Статус',$sortColumn,$sortDirection,$filterVal) ?>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="5" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['status_class'] ?>">
              <td><strong><?= h($r['question_id']) ?></strong></td>
              <td><?= h($r['syllabus_title']) ?></td>
              <td><?= h($r['discipline_name']) ?></td>
              <td><?= h($r['average_score']) ?></td>
              <td><span class="badge badge-soft <?= $r['status_class'] ?>"><?= h($r['alignment_level']) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('semantic', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

case 'criteria':
    $raw  = $ai->getCriteriaPerformanceAnalysis();
    $data = [];
    foreach (($raw ?: []) as $c) {
        $sc = ($c['status'] ?? 'ok') === 'ok' ? 'success' : (($c['status'] ?? '') === 'warning' ? 'warning' : 'danger');
        $data[] = array_merge($c, ['status_class' => $sc]);
    }
    if ($sortColumn) {
        $d = $sortDirection === 'DESC' ? -1 : 1;
        usort($data, fn($a,$b) => (($a[$sortColumn]??'') <=> ($b[$sortColumn]??'')) * $d);
    }
    $data  = applyBadgeFilter($data, $filterVal, 'status_class');
    $total = count($data); $pages = max(1,(int)ceil($total/$perPage));
    $page  = min($page,$pages);
    $paged = array_slice($data,($page-1)*$perPage,$perPage);

    $cols = $paged ? array_keys($paged[0]) : [];
    $skipCols = ['status_class'];
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?php foreach ($cols as $col): if (in_array($col,$skipCols)) continue; ?>
            <?= sortTh('criteria', $col, ucfirst(str_replace('_',' ',$col)), $sortColumn, $sortDirection, $filterVal) ?>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="<?= max(1,count($cols)-count($skipCols)) ?>" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['status_class'] ?>">
              <?php foreach ($cols as $col): if (in_array($col,$skipCols)) continue;
                if ($col === 'status'): ?>
                  <td><span class="badge badge-soft <?= $r['status_class'] ?>"><?= h($r[$col]) ?></span></td>
                <?php else: ?>
                  <td><?= h($r[$col] ?? '') ?></td>
                <?php endif; endforeach; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('criteria', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

case 'answers':
    $raw = $ai->getStudentAnswerAnalysis() ?: [];
    foreach ($raw as &$row) {
        $rate = (float)($row['plagiarism_rate'] ?? 0);
        $row['_flag_class'] = $rate > 10 ? 'danger' : ($rate > 5 ? 'warning' : 'success');
        $row['_flag_label'] = $rate > 10 ? 'Высокий плагиат' : ($rate > 5 ? 'Умеренный' : 'Норма');
    }
    unset($row);
    $data = $raw;
    if ($sortColumn) {
        $d = $sortDirection === 'DESC' ? -1 : 1;
        usort($data, fn($a,$b) => (($a[$sortColumn]??'') <=> ($b[$sortColumn]??'')) * $d);
    }
    $data  = applyBadgeFilter($data, $filterVal, '_flag_class');
    $total = count($data); $pages = max(1,(int)ceil($total/$perPage));
    $page  = min($page,$pages);
    $paged = array_slice($data,($page-1)*$perPage,$perPage);
    ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr>
          <?= sortTh('answers','language','Язык',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('answers','total_answers','Всего ответов',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('answers','avg_score','Ср. балл',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('answers','avg_penalty','Штраф',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('answers','plagiarism_count','Плагиат (кол-во)',$sortColumn,$sortDirection,$filterVal) ?>
          <?= sortTh('answers','plagiarism_rate','% плагиата',$sortColumn,$sortDirection,$filterVal) ?>
          <th>Флаг</th>
        </tr></thead>
        <tbody>
          <?php if (empty($paged)): ?><tr><td colspan="7" class="text-muted"><?= t('no_data') ?></td></tr>
          <?php else: foreach ($paged as $r): ?>
            <tr class="ai-row-<?= $r['_flag_class'] ?>">
              <td><strong><?= h($r['language']) ?></strong></td>
              <td><?= h($r['total_answers']) ?></td>
              <td><?= h($r['avg_score']) ?></td>
              <td><?= h($r['avg_penalty']) ?></td>
              <td><?= h($r['plagiarism_count']) ?></td>
              <td><?= h($r['plagiarism_rate']) ?>%</td>
              <td><span class="badge badge-soft <?= $r['_flag_class'] ?>"><?= h($r['_flag_label']) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPaginationAjax('answers', $page, $pages, $total, $sortColumn, $sortDirection, $filterVal) ?>
    <?php break;

default:
    echo '<p class="text-muted">Section not supported for AJAX.</p>';
}

$_perf_total_ms = round((microtime(true) - $_perf_start) * 1000);
echo '<span class="perf-server-timing" data-section="' . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . '" data-init-ms="' . $_perf_init_ms . '" data-total-ms="' . $_perf_total_ms . '" style="display:none;"></span>';
