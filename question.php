<?php
require_once __DIR__.'/includes/layout.php';
require_login();
$id=(int)($_GET['id']??0);
$r=rows("SELECT * FROM v_question_list WHERE question_id=?",[$id])[0]??null;
$sem=rows("SELECT * FROM semantic_analysis WHERE question_id=?",[$id])[0]??null;
$hist=rows("SELECT * FROM raw_exam_results WHERE question_id=? ORDER BY raw_id DESC LIMIT 30",[$id]);
page_header('Карточка вопроса','questions.php');
if(!$r){ echo "<div class='card'>Вопрос не найден</div>"; page_footer(); exit; }
?>
<div class="cards">
<?php card('ID вопроса','Q'.$r['question_id']); card('Средний балл',$r['avg_score_before_appeal']); card('Сложность',$r['difficulty_pct'].'%'); card('Семантика',$sem ? $sem['match_status'].' '.$sem['tfidf_score'] : 'нет'); ?>
</div>
<div class="card"><h3><?=h($r['discipline_name'])?></h3><p><?=h($r['recommendation'])?></p></div>
<div class="card"><h3>Последние результаты</h3><table><thead><tr><th>Студент</th><th>Язык</th><th>До</th><th>После</th><th>Итог</th></tr></thead><tbody><?php foreach($hist as $h): ?><tr><td><?=h($h['student_id'])?></td><td><?=h($h['exam_language'])?></td><td><?=h($h['score_before_appeal'])?></td><td><?=h($h['score_after_appeal'])?></td><td><?=h($h['discipline_score'])?></td></tr><?php endforeach; ?></tbody></table></div>
<?php page_footer(); ?>
