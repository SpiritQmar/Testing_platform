<?php
require_once __DIR__.'/includes/layout.php';
require_login();
$k=[
 'questions'=>one("SELECT COUNT(*) FROM questions"),
 'students'=>one("SELECT COUNT(*) FROM students"),
 'imports'=>one("SELECT COUNT(*) FROM imports_log"),
 'avg'=>round((float)one("SELECT AVG(avg_score_before_appeal) FROM question_metrics"),2),
 'hard'=>one("SELECT COUNT(*) FROM question_metrics WHERE flag='hard'")
];
$chart=rows("SELECT question_id, avg_score_before_appeal FROM question_metrics ORDER BY question_id DESC LIMIT 14");
$disc=rows("SELECT discipline_name, COUNT(*) c FROM raw_exam_results GROUP BY discipline_name ORDER BY c DESC LIMIT 5");
$diff=rows("SELECT flag, COUNT(*) c FROM question_metrics GROUP BY flag");
$diffMap=['easy'=>0,'medium'=>0,'hard'=>0]; foreach($diff as $d){$diffMap[$d['flag']]=$d['c'];}
page_header('Dashboard','dashboard.php');
?>
<a class="live">⌁ Live</a>
<h2 class="page-title"><?=h(tr('dashboard.overview'))?></h2>
<p class="page-sub"><?=h(tr('dashboard.sub'))?></p>
<div class="metrics">
  <div class="metric-card"><div class="metric-icon">?</div><div class="metric-trend">↗ +12.5%</div><div class="metric-label"><?=h(tr('metric.total'))?></div><div class="metric-value"><?=h($k['questions'])?></div></div>
  <div class="metric-card"><div class="metric-icon">▥</div><div class="metric-trend">↗ +5.2%</div><div class="metric-label"><?=h(tr('metric.complexity'))?></div><div class="metric-value"><?=round((100-$k['avg'])/100,2)?></div></div>
  <div class="metric-card"><div class="metric-icon">↗</div><div class="metric-trend">↗ +8.1%</div><div class="metric-label"><?=h(tr('metric.correlation'))?></div><div class="metric-value">0.78</div></div>
  <div class="metric-card"><div class="metric-icon">!</div><div class="metric-trend">↘ -15.3%</div><div class="metric-label"><?=h(tr('metric.problems'))?></div><div class="metric-value"><?=h($k['hard'])?></div></div>
</div>
<div class="grid2">
  <div class="card"><h3><?=h(tr('chart.diff'))?></h3><div class="card-sub"><?=h(tr('chart.diff.sub'))?></div><div class="chartbox"><canvas id="diffChart"></canvas></div></div>
  <div class="card"><h3><?=h(tr('chart.disc'))?></h3><div class="card-sub"><?=h(tr('chart.disc.sub'))?></div><div class="chartbox"><canvas id="discChart"></canvas></div></div>
</div>
<div class="grid2">
  <div class="card"><h3>Performance Trend</h3><div class="card-sub">Quality score over time</div><div class="chartbox"><canvas id="scoreChart"></canvas></div></div>
  <div class="card"><h3>Question Quality</h3><div class="card-sub">Overall quality assessment</div><div class="chartbox"><canvas id="qualityChart"></canvas></div></div>
</div>
<script>
new Chart(document.getElementById('diffChart'),{type:'doughnut',data:{labels:['Легкий','Средний','Сложный'],datasets:[{data:[<?=$diffMap['easy']?>,<?=$diffMap['medium']?>,<?=$diffMap['hard']?>],backgroundColor:['#12b981','#f59e0b','#ef4444'],borderWidth:6,borderColor:'#fff'}]},options:{cutout:'62%',plugins:{legend:{position:'right'}}}});
new Chart(document.getElementById('discChart'),{type:'bar',data:{labels:<?=json_encode(array_column($disc,'discipline_name'),JSON_UNESCAPED_UNICODE)?>,datasets:[{label:'Questions',data:<?=json_encode(array_map('intval',array_column($disc,'c')))?>,backgroundColor:'#6366f1',borderRadius:8}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('scoreChart'),{type:'line',data:{labels:<?=json_encode(array_column($chart,'question_id'))?>,datasets:[{label:'Quality',data:<?=json_encode(array_map('floatval',array_column($chart,'avg_score_before_appeal')))?>,borderColor:'#12b981',backgroundColor:'rgba(18,185,129,.14)',fill:true,tension:.35}]},options:{scales:{y:{beginAtZero:true,max:100}}}});
new Chart(document.getElementById('qualityChart'),{type:'bar',data:{labels:['Quality'],datasets:[{label:'Score',data:[<?=$k['avg']?>],backgroundColor:'#12b981',borderRadius:8}]},options:{indexAxis:'y',scales:{x:{beginAtZero:true,max:100}}}});
</script>
<?php page_footer(); ?>
