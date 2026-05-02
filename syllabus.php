<?php
require_once __DIR__.'/includes/layout.php';
require_login();
$list=rows("SELECT * FROM ai_syllabus_topics ORDER BY discipline_name, topic_code LIMIT 300");
page_header('Силлабус и темы','syllabus.php');
?>
<div class="card"><h3>Темы силлабуса</h3><table><thead><tr><th>ID</th><th>Дисциплина</th><th>Курс</th><th>Код</th><th>Тема</th><th>Ключевые слова</th></tr></thead><tbody><?php foreach($list as $r): ?><tr><td><?=h($r['syllabus_topic_id'])?></td><td><?=h($r['discipline_name'])?></td><td><?=h($r['course_number'])?></td><td><?=h($r['topic_code'])?></td><td><?=h($r['title'])?></td><td><?=h($r['keywords'])?></td></tr><?php endforeach; ?></tbody></table></div>
<?php page_footer(); ?>
