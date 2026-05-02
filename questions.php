<?php
require_once __DIR__.'/includes/layout.php';
require_login();
$q=trim($_GET['q']??''); $flag=$_GET['flag']??'';
$params=[]; $where=[];
if($q!==''){ $where[]="(question_id LIKE ? OR discipline_name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
if($flag!==''){ $where[]="flag=?"; $params[]=$flag; }
$sql="SELECT * FROM v_question_list".($where?" WHERE ".implode(" AND ",$where):"")." ORDER BY question_id DESC LIMIT 200";
$list=rows($sql,$params);
page_header(tr('nav.questions'),'questions.php');
?>
<div class="card">
<form class="filters">
  <input name="q" placeholder="Поиск по ID или дисциплине" value="<?=h($q)?>">
  <select name="flag"><option value="">Все статусы</option><option value="hard" <?=$flag==='hard'?'selected':''?>>Hard</option><option value="medium" <?=$flag==='medium'?'selected':''?>>Medium</option><option value="easy" <?=$flag==='easy'?'selected':''?>>Easy</option></select>
  <button class="btn primary">Search</button>
</form>
</div>
<div class="card">
<?php if($q!=='' || $flag!==''): ?><div><span class='filter-chip'>Active filter: <?=h($q ?: $flag)?></span></div><?php endif; ?>
<table><thead><tr><th>ID</th><th>Дисциплина</th><th>Курс</th><th>Попыток</th><th>Средний до</th><th>Средний после</th><th>Сложность</th><th>Статус</th></tr></thead><tbody>
<?php foreach($list as $r): ?><tr onclick="location.href='question.php?id=<?=$r['question_id']?>'"><td>Q<?=h($r['question_id'])?></td><td><?=h($r['discipline_name'])?></td><td><?=h($r['course_number'])?></td><td><?=h($r['attempts_count'])?></td><td><?=h($r['avg_score_before_appeal'])?></td><td><?=h($r['avg_score_after_appeal'])?></td><td><?=h($r['difficulty_pct'])?>%</td><td><span class="badge <?=$r['flag']?>"><?=h($r['flag'])?></span></td></tr><?php endforeach; ?>
</tbody></table>
</div>
<?php page_footer(); ?>
