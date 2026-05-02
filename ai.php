<?php
require_once __DIR__.'/includes/layout.php';
require_login();
$hard=rows("SELECT * FROM v_question_list WHERE flag='hard' ORDER BY difficulty_pct DESC LIMIT 10");
$easy=rows("SELECT * FROM v_question_list WHERE flag='easy' ORDER BY avg_score_before_appeal DESC LIMIT 10");
$weak=rows("SELECT hq.question_id, st.title, sa.tfidf_score, sa.match_status FROM semantic_analysis sa JOIN hier_questions hq ON hq.question_id=sa.question_id LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id=sa.syllabus_topic_id ORDER BY sa.tfidf_score ASC LIMIT 10");
page_header('ИИ-аналитика','ai.php');
?>
<div class="grid2">
  <div class="card"><h3>Вопросы повышенного риска</h3><?php foreach($hard as $q): ?><div class="risk"><b>Q<?=h($q['question_id'])?></b> <?=h($q['discipline_name'])?><br><span>Средний: <?=h($q['avg_score_before_appeal'])?> • <?=h($q['recommendation'])?></span></div><?php endforeach; ?></div>
  <div class="card"><h3>Слишком простые вопросы</h3><?php foreach($easy as $q): ?><div class="ok"><b>Q<?=h($q['question_id'])?></b> <?=h($q['discipline_name'])?><br><span>Средний: <?=h($q['avg_score_before_appeal'])?> • <?=h($q['recommendation'])?></span></div><?php endforeach; ?></div>
</div>
<div class="card"><h3>Семантическая проверка соответствия силлабусу</h3><table><thead><tr><th>Вопрос</th><th>Тема силлабуса</th><th>TF-IDF score</th><th>Статус</th></tr></thead><tbody><?php foreach($weak as $w): ?><tr><td>Q<?=h($w['question_id'])?></td><td><?=h($w['title'])?></td><td><?=h($w['tfidf_score'])?></td><td><?=h($w['match_status'])?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card"><h3>Что объединено</h3><p>Этот раздел объединяет твою систему обработки экзаменационных результатов и AI-часть одногруппника: семантическую привязку к силлабусу, правила классификации, диагностику сложности и рекомендации.</p></div>
<?php page_footer(); ?>
