<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

function page_header($title, $active='dashboard') {
    $u = user();
    $lang = current_lang();

    echo "<!doctype html><html lang='".h($lang)."'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>".h($title)." — UAMS</title>";
    echo "<link rel='stylesheet' href='assets/app.css'>";
    echo "<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>";
    echo "<script defer src='assets/app.js'></script>";
    echo "</head><body>";
    if (!$u) { echo "<main class='public'>"; return; }

    $nav = [
        'dashboard.php'=>['nav.dashboard','grid'],
        'ai.php'=>['nav.analytics','chart'],
        'questions.php'=>['nav.questions','question'],
        'import.php'=>['nav.import','upload'],
        'settings.php'=>['nav.settings','settings'],
    ];

    echo "<div class='uams-shell'>";
    echo "<aside class='uams-sidebar'>";
    echo "<div class='uams-brand'><div class='uams-logo'>?</div><div><div class='uams-name'>UAMS</div><div class='uams-small'>Question Analysis</div></div></div>";
    echo "<nav class='uams-nav'>";
    foreach($nav as $url=>$meta) {
        $cl = basename($url)==$active ? 'active' : '';
        echo "<a class='$cl' href='$url'><span class='i {$meta[1]}'></span><span>".h(tr($meta[0]))."</span><span class='arrow'>›</span></a>";
    }
    echo "</nav>";
    echo "<div class='teacher-card'><div class='avatar'>TU</div><div><b>Teacher User</b><span>teacher@university.edu</span></div></div>";
    echo "</aside>";

    echo "<section class='uams-main'>";
    echo "<header class='uams-top'>";
    echo "<div class='title-block'><h1>".h(tr('app.title'))."</h1><p>".h(tr('app.hello'))."</p></div>";
    echo "<form class='uams-search' method='get' action='questions.php'><span>⌕</span><input name='q' value='".h($_GET['q'] ?? '')."' placeholder='".h(tr('search'))."'><kbd>⌘K</kbd></form>";
    echo "<div class='uams-actions'><a class='bell' href='#'>♧<span>3</span></a><button type='button' id='themeToggle' class='icon'>☾</button><div class='lang'><button type='button'>◉ ".strtoupper($lang)."</button><div>";
    echo "<a href='".h(lang_url('ru'))."'>RU</a><a href='".h(lang_url('en'))."'>EN</a><a href='".h(lang_url('kk'))."'>KK</a>";
    echo "</div></div></div>";
    echo "</header>";
    echo "<div class='uams-content'>";
}

function page_footer() {
    if (user()) echo "</div></section></div>"; else echo "</main>";
    echo "</body></html>";
}

function card($title, $value, $hint='') {
    echo "<div class='metric-card'><div class='metric-icon'>?</div><div class='metric-trend'>↗ +12.5%</div><div class='metric-label'>".h($title)."</div><div class='metric-value'>".h($value)."</div><div class='metric-sub'>".h($hint)."</div></div>";
}
