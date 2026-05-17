<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/coefficients.php';

$cfg = require __DIR__ . '/config.php';
$coef = $cfg['coefficients'];
$csrf = csrf_token();

$labels = [
    'quality' => [
        'easy_threshold'        => 'Порог «слишком лёгкий» (0–1)',
        'hard_threshold'        => 'Порог «слишком сложный» (0–1)',
        'discrimination_strong' => 'Порог сильной дискриминации',
    ],
    'risk' => [
        'systemic_avg' => 'Системный риск: средний балл (%)',
        'spot_min'     => 'Точечный риск: минимальный балл (%)',
    ],
    'weights' => [
        'score_weight'      => 'Вес оценки',
        'penalty_easy_hard' => 'Штраф за лёгкость/сложность',
    ],
    'discrimination' => [
        'min_students' => 'Минимум студентов для индекса',
    ],
    'similarity' => [
        'duplicate_threshold' => 'Порог дубликата (0–1)',
    ],
    'keyword' => [
        'min_token_length' => 'Мин. длина токена (симв.)',
    ],
];

$sectionTitles = [
    'quality'        => 'Качество вопросов',
    'risk'           => 'Риск студентов',
    'weights'        => 'Веса',
    'discrimination' => 'Дискриминация',
    'similarity'     => 'Схожесть',
    'keyword'        => 'Ключевые слова',
];

$disciplines = [];
try {
    $disciplines = $pdo->query("SELECT DISTINCT discipline_name FROM ai_syllabus_topics WHERE discipline_name IS NOT NULL ORDER BY discipline_name")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {}

$metricLabels = [
    'difficulty_index'     => 'Индекс сложности (0–1)',
    'discrimination_index' => 'Индекс дискриминации',
    'avg_score'            => 'Средний балл',
    'total_attempts'       => 'Всего попыток',
];
$opLabels = ['>' => '>', '<' => '<', '>=' => '≥', '<=' => '≤', 'between' => 'между'];
$catLabels = [
    'too_difficult'         => 'Слишком сложный',
    'too_easy'              => 'Слишком лёгкий',
    'poor_discrimination'   => 'Слабая дискриминация',
    'insufficient_attempts' => 'Мало попыток',
];
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Настройки аналитики</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f8f9fa;padding:20px;font-family:Inter,system-ui,sans-serif;color:#1f2937}
  .tab-bar{display:flex;gap:6px;margin-bottom:16px;border-bottom:1px solid #e5e7eb}
  .tab-btn{padding:10px 18px;background:none;border:none;border-bottom:3px solid transparent;font-size:14px;font-weight:500;color:#6b7280;cursor:pointer}
  .tab-btn.active{color:#3b82f6;border-bottom-color:#3b82f6}
  .tab-pane{display:none}
  .tab-pane.active{display:block}
  .card-block{background:#fff;border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
  .block-title{font-size:14px;font-weight:600;color:#374151;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #e5e7eb}
  .field-row{display:flex;align-items:center;gap:12px;margin-bottom:10px}
  .field-label{flex:1;font-size:13px;color:#4b5563}
  .field-input{width:110px;text-align:right;padding:5px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
  .field-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
  .btn-save{background:#3b82f6;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer}
  .btn-save:hover{background:#2563eb}
  .btn-mini{padding:5px 12px;font-size:12px;border-radius:5px;border:1px solid #d1d5db;background:#fff;cursor:pointer}
  .btn-mini-primary{background:#3b82f6;color:#fff;border-color:#3b82f6}
  .btn-mini-success{background:#10b981;color:#fff;border-color:#10b981}
  .btn-mini-danger{color:#dc2626;border-color:#fecaca}
  .alert-msg{padding:8px 14px;border-radius:6px;font-size:13px;margin-top:10px;display:none}
  .msg-ok{background:#d1fae5;color:#065f46}
  .msg-err{background:#fee2e2;color:#991b1b}
  .rule-row{padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;display:flex;gap:8px;align-items:center;font-size:13px;background:#fafafa}
  .rule-row.inactive{opacity:.5}
  .pill{padding:2px 8px;border-radius:10px;font-size:11px;background:#eef2ff;color:#3730a3}
  .pill.cat{background:#fef3c7;color:#92400e}
  .pill.op{background:#dcfce7;color:#166534}
  .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:10px}
  .form-grid input,.form-grid select{padding:7px 10px;font-size:13px;border:1px solid #d1d5db;border-radius:6px}
  table.t-overrides{width:100%;font-size:13px;border-collapse:collapse}
  table.t-overrides td,table.t-overrides th{padding:7px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
  .empty-hint{color:#6b7280;font-size:13px;padding:10px 0}
</style>
</head>
<body>

<div class="tab-bar">
  <button class="tab-btn active" data-tab="t-coef">Глобальные коэффициенты</button>
  <button class="tab-btn" data-tab="t-rules">Rule-конструктор</button>
  <button class="tab-btn" data-tab="t-overrides">Переопределения (per-level)</button>
  <button class="tab-btn" data-tab="t-search">AI поиск</button>
</div>

<div id="t-coef" class="tab-pane active">
  <form id="coef-form">
  <?php foreach ($coef as $cat => $fields): ?>
    <?php if (!isset($labels[$cat])) continue; ?>
    <div class="card-block">
      <div class="block-title"><?= htmlspecialchars($sectionTitles[$cat] ?? $cat, ENT_QUOTES, 'UTF-8') ?></div>
      <?php foreach ($fields as $key => $val): ?>
        <?php if (!isset($labels[$cat][$key])) continue; ?>
        <div class="field-row">
          <label class="field-label" for="f_<?= $cat ?>_<?= $key ?>"><?= htmlspecialchars($labels[$cat][$key], ENT_QUOTES, 'UTF-8') ?></label>
          <input
            id="f_<?= $cat ?>_<?= $key ?>"
            class="field-input"
            type="number"
            step="<?= is_int($val) ? '1' : '0.01' ?>"
            name="coefficients[<?= $cat ?>][<?= $key ?>]"
            value="<?= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <button type="submit" class="btn-save">Сохранить</button>
  <div id="coef-msg" class="alert-msg"></div>
  </form>
</div>

<div id="t-rules" class="tab-pane">
  <div class="card-block">
    <div class="block-title">Активные правила классификации</div>
    <div id="rules-list"><div class="empty-hint">Загрузка…</div></div>
    <div id="rules-msg" class="alert-msg"></div>
  </div>

  <div class="card-block">
    <div class="block-title">Создать правило</div>
    <form id="rule-form">
      <div class="form-grid">
        <input type="text" name="rule_name" placeholder="Название правила" required>
        <select name="metric_name" required>
          <?php foreach ($metricLabels as $k => $v): ?>
            <option value="<?= $k ?>"><?= htmlspecialchars($v, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="operator" id="op-select" required>
          <?php foreach ($opLabels as $k => $v): ?>
            <option value="<?= $k ?>"><?= htmlspecialchars($v, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" step="0.01" name="value_from" placeholder="Значение от" required>
        <input type="number" step="0.01" name="value_to" placeholder="Значение до (для between)" id="val-to" disabled>
        <select name="category" required>
          <?php foreach ($catLabels as $k => $v): ?>
            <option value="<?= $k ?>"><?= htmlspecialchars($v, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="action_message" placeholder="Сообщение/интерпретация">
        <input type="number" name="rule_order" placeholder="Порядок" value="1" min="1">
      </div>
      <button type="submit" class="btn-mini btn-mini-primary">Создать правило</button>
    </form>
  </div>
</div>

<div id="t-overrides" class="tab-pane">
  <div class="card-block">
    <div class="block-title">Текущие переопределения</div>
    <div id="overrides-list"><div class="empty-hint">Загрузка…</div></div>
    <div id="ov-msg" class="alert-msg"></div>
  </div>

  <div class="card-block">
    <div class="block-title">Создать/обновить переопределение</div>
    <form id="ov-form">
      <div class="form-grid">
        <select name="scope_type" required>
          <option value="discipline">Дисциплина</option>
          <option value="specialty">Специальность</option>
        </select>
        <input list="disc-list" name="scope_value" placeholder="Название" required>
        <datalist id="disc-list">
          <?php foreach ($disciplines as $d): ?>
            <option value="<?= htmlspecialchars((string)$d, ENT_QUOTES) ?>">
          <?php endforeach; ?>
        </datalist>
        <select name="category" required>
          <?php foreach ($coef as $cat => $_): ?>
            <option value="<?= $cat ?>"><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="coef_key" placeholder="Ключ (напр. easy_threshold)" required>
        <input type="number" step="0.0001" name="coef_value" placeholder="Значение" required>
      </div>
      <button type="submit" class="btn-mini btn-mini-success">Сохранить переопределение</button>
    </form>
  </div>
</div>

<div id="t-search" class="tab-pane">
  <div class="card-block">
    <div class="block-title">Семантический поиск (AI)</div>
    <div style="font-size:13px;color:#4b5563;margin-bottom:12px">
      Индексирует вопросы и темы силлабуса в виде эмбеддингов через Python сервис.
      Поиск в верхней панели сайта использует этот индекс для семантической выдачи.
      <br><br>
      <strong>Жми один раз</strong> — после построения индекс кэшируется, поиск работает автоматически.
      Повторно требуется только если добавлены новые вопросы/темы (после загрузки силлабуса инкрементальное обновление запускается автоматически).
    </div>
    <div id="search-stats" class="empty-hint">Загрузка статуса…</div>
    <div style="display:flex;gap:8px;margin-top:10px">
      <button class="btn-mini btn-mini-primary" onclick="indexSearch(false)">Доиндексировать</button>
      <button class="btn-mini btn-mini-danger" onclick="indexSearch(true)">Полная переиндексация</button>
    </div>
    <div id="search-msg" class="alert-msg"></div>
  </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const METRICS = <?= json_encode($metricLabels) ?>;
const OPS = <?= json_encode($opLabels) ?>;
const CATS = <?= json_encode($catLabels) ?>;

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

document.getElementById('coef-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('csrf_token', CSRF);
  const msg = document.getElementById('coef-msg');
  try {
    const r = await fetch('api/settings.php', { method: 'POST', body: fd });
    const d = await r.json();
    msg.style.display = 'block';
    if (d.success) { msg.className = 'alert-msg msg-ok'; msg.textContent = 'Сохранено: ' + (d.saved || []).join(', '); }
    else { msg.className = 'alert-msg msg-err'; msg.textContent = d.error || 'Ошибка'; }
  } catch(err) {
    msg.style.display = 'block';
    msg.className = 'alert-msg msg-err';
    msg.textContent = 'Ошибка сети: ' + err.message;
  }
});

document.getElementById('op-select').addEventListener('change', e => {
  document.getElementById('val-to').disabled = e.target.value !== 'between';
});

async function loadRules() {
  const r = await fetch('api/rules_list.php');
  const d = await r.json();
  const box = document.getElementById('rules-list');
  if (!d.success) { box.innerHTML = '<div class="alert-msg msg-err" style="display:block">'+(d.error||'')+'</div>'; return; }
  if (!d.rules.length) { box.innerHTML = '<div class="empty-hint">Правил пока нет</div>'; return; }
  box.innerHTML = d.rules.map(r => {
    const cond = (r.conditions || []).map(c => {
      const v = c.operator === 'between' ? `${c.value_from}..${c.value_to}` : c.value_from;
      return `<span class="pill">${METRICS[c.metric_name]||c.metric_name}</span> <span class="pill op">${OPS[c.operator]||c.operator}</span> <span class="pill">${v}</span>`;
    }).join(' ');
    const acts = (r.actions || []).map(a => `<span class="pill cat">${CATS[a.action_value]||a.action_value}</span>`).join(' ');
    return `<div class="rule-row ${r.is_active==1?'':'inactive'}">
      <strong style="min-width:140px">${r.rule_name}</strong>
      <div style="flex:1">${cond} → ${acts}</div>
      <button class="btn-mini" onclick="toggleRule(${r.rule_id})">${r.is_active==1?'Выкл':'Вкл'}</button>
      <button class="btn-mini btn-mini-danger" onclick="deleteRule(${r.rule_id})">Удалить</button>
    </div>`;
  }).join('');
}

async function toggleRule(rid) {
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action','toggle'); fd.append('rule_id', rid);
  await fetch('api/rules_action.php',{method:'POST',body:fd}); loadRules();
}
async function deleteRule(rid) {
  if (!confirm('Удалить правило?')) return;
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action','delete'); fd.append('rule_id', rid);
  await fetch('api/rules_action.php',{method:'POST',body:fd}); loadRules();
}

document.getElementById('rule-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this); fd.append('csrf_token', CSRF);
  const msg = document.getElementById('rules-msg');
  const r = await fetch('api/create_rule.php',{method:'POST',body:fd});
  const d = await r.json();
  msg.style.display='block';
  if (d.success) { msg.className='alert-msg msg-ok'; msg.textContent='Правило создано'; this.reset(); loadRules(); }
  else { msg.className='alert-msg msg-err'; msg.textContent=d.error||'Ошибка'; }
});

async function loadOverrides() {
  const r = await fetch('api/overrides.php?action=list');
  const d = await r.json();
  const box = document.getElementById('overrides-list');
  if (!d.success) { box.innerHTML = '<div class="alert-msg msg-err" style="display:block">'+(d.error||'')+'</div>'; return; }
  if (!d.overrides.length) { box.innerHTML = '<div class="empty-hint">Переопределений нет — используются глобальные значения</div>'; return; }
  box.innerHTML = `<table class="t-overrides">
    <thead><tr><th>Scope</th><th>Значение</th><th>Категория</th><th>Ключ</th><th>Значение</th><th></th></tr></thead>
    <tbody>${d.overrides.map(o => `
      <tr>
        <td>${o.scope_type}</td>
        <td>${o.scope_value}</td>
        <td>${o.category}</td>
        <td>${o.coef_key}</td>
        <td><strong>${o.coef_value}</strong></td>
        <td><button class="btn-mini btn-mini-danger" onclick="delOverride(${o.override_id})">Удалить</button></td>
      </tr>`).join('')}</tbody></table>`;
}

async function delOverride(id) {
  if (!confirm('Удалить переопределение?')) return;
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action','delete'); fd.append('override_id', id);
  await fetch('api/overrides.php',{method:'POST',body:fd}); loadOverrides();
}

document.getElementById('ov-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this); fd.append('csrf_token', CSRF); fd.append('action','set');
  const msg = document.getElementById('ov-msg');
  const r = await fetch('api/overrides.php',{method:'POST',body:fd});
  const d = await r.json();
  msg.style.display='block';
  if (d.success) { msg.className='alert-msg msg-ok'; msg.textContent='Сохранено'; this.reset(); loadOverrides(); }
  else { msg.className='alert-msg msg-err'; msg.textContent=d.error||'Ошибка'; }
});

async function loadSearchStats() {
  try {
    const r = await fetch('api/search.php?q=__stats__&top_k=1');
    const d = await r.json();
    const box = document.getElementById('search-stats');
    box.innerHTML = `Индекс: <strong>${d.index_size || 0}</strong> элементов · Режим: <strong>${d.mode || 'unknown'}</strong>`;
  } catch (e) {}
}

async function indexSearch(rebuild) {
  const msg = document.getElementById('search-msg');
  msg.style.display = 'block';
  msg.className = 'alert-msg';
  msg.textContent = 'Индексация… подождите (батч из 32 в раз)';
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  if (rebuild) fd.append('rebuild', '1');
  try {
    const r = await fetch('api/search_index.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
      msg.className = 'alert-msg msg-ok';
      msg.textContent = `Готово. Проиндексировано: ${d.indexed} / ${d.total} (${d.mode || ''})`;
      loadSearchStats();
    } else {
      msg.className = 'alert-msg msg-err';
      msg.textContent = d.error || 'Ошибка';
    }
  } catch (e) {
    msg.className = 'alert-msg msg-err';
    msg.textContent = 'Сеть: ' + e.message;
  }
}

loadRules();
loadOverrides();
loadSearchStats();
</script>
</body>
</html>
