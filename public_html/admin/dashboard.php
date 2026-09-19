<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

$pdo = getDB();

$totalCards = $pdo->query("SELECT COUNT(*) FROM cards")->fetchColumn();
$totalPublished = $pdo->query("SELECT COUNT(*) FROM cards WHERE status='published'")->fetchColumn();
$totalSessions = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$byLevel = $pdo->query("SELECT level, COUNT(*) as cnt FROM cards GROUP BY level")->fetchAll(PDO::FETCH_ASSOC);
$byMode = $pdo->query("SELECT id, name as cnt, 0 FROM categories")->fetchAll();
// Count by mode
$modeCounts = [];
foreach (['couple','wmw','mwm','mwmw'] as $m) {
    $modeCounts[$m] = $pdo->query("SELECT COUNT(*) FROM cards WHERE modes LIKE '%\"$m\"%'")->fetchColumn();
}

// Favorite cards (most liked across sessions)
$favStats = [];
$sessions = $pdo->query("SELECT favorite_card_ids FROM sessions WHERE favorite_card_ids != '[]'")->fetchAll(PDO::FETCH_ASSOC);
$favCount = [];
foreach ($sessions as $s) {
    $ids = json_decode($s['favorite_card_ids'], true) ?? [];
    foreach ($ids as $id) {
        $favCount[$id] = ($favCount[$id] ?? 0) + 1;
    }
}
arsort($favCount);
$topFavCards = [];
if ($favCount) {
    $topIds = array_slice(array_keys($favCount), 0, 10);
    $placeholders = implode(',', array_fill(0, count($topIds), '?'));
    $stmt = $pdo->prepare("SELECT id, text, level FROM cards WHERE id IN ($placeholders)");
    $stmt->execute($topIds);
    $topFavCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recentSessions = $pdo->query("SELECT code, mode, players, created_at, favorite_card_ids FROM sessions ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Дашборд — Админка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#0a0a0a;color:#fff;padding:24px;min-height:100vh}
a{color:#00A7D1;text-decoration:none;transition:.2s}
a:hover{color:#F663B3}
h1{font-size:28px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.header-bar a{font-size:14px;padding:8px 16px;border-radius:60px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff}
.header-bar a:hover{background:rgba(246,99,179,.1);border-color:#F663B3}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:32px}
.stat{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px;text-align:center}
.stat__num{font-size:32px;font-weight:700;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.stat__label{font-size:13px;color:rgba(255,255,255,.4);margin-top:4px}
h2{font-size:18px;margin:24px 0 16px;color:rgba(255,255,255,.6)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:24px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.4);font-size:11px;text-transform:uppercase;letter-spacing:1px}
td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.05)}
.badge{padding:2px 8px;border-radius:60px;font-size:11px}
.badge-green{background:rgba(76,175,80,.2);color:#4CAF50}
.badge-orange{background:rgba(255,107,53,.2);color:#FF6B35}
.badge-purple{background:rgba(190,90,255,.2);color:#BE5AFF}
.badge-red{background:rgba(255,82,82,.2);color:#FF5252}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="header-bar">
    <div><h1>Дашборд</h1></div>
    <div>
        <a href="/admin/cards.php">📋 Карточки</a>
        <a href="/admin/settings.php">⚙️ Настройки</a>
        <a href="/admin/users.php">👥 Пользователи</a>
        <a href="/admin/dashboard.php" class="active">📊 Дашборд</a>
        <a href="/admin/logout.php">🚪 Выйти</a>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="stat__num"><?=$totalCards?></div><div class="stat__label">Всего карточек</div></div>
    <div class="stat"><div class="stat__num"><?=$totalPublished?></div><div class="stat__label">Опубликовано</div></div>
    <div class="stat"><div class="stat__num"><?=$totalSessions?></div><div class="stat__label">Сессий</div></div>
    <div class="stat"><div class="stat__num"><?=$totalUsers?></div><div class="stat__label">Пользователей</div></div>
</div>

<div class="grid-2">
    <div>
        <h2>По уровням</h2>
        <table><thead><tr><th>Уровень</th><th>Кол-во</th></tr></thead><tbody>
        <?php foreach ($byLevel as $l): ?>
        <tr><td><span class="badge badge-<?=$l['level']?>"><?=$l['level']?></span></td><td><?=$l['cnt']?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <h2>По режимам</h2>
        <table><thead><tr><th>Режим</th><th>Кол-во</th></tr></thead><tbody>
        <tr><td>♂♀ Пара</td><td><?=$modeCounts['couple']?></td></tr>
        <tr><td>♀♂♀ ЖМЖ</td><td><?=$modeCounts['wmw']?></td></tr>
        <tr><td>♂♀♂ МЖМ</td><td><?=$modeCounts['mwm']?></td></tr>
        <tr><td>♂♀♂♀ МЖМЖ</td><td><?=$modeCounts['mwmw']?></td></tr>
        </tbody></table>
    </div>
    <div>
        <h2>❤ Топ избранных (от игроков)</h2>
        <?php if ($topFavCards): ?>
        <table><thead><tr><th>ID</th><th>Текст</th><th>Уровень</th><th>❤ Раз</th></tr></thead><tbody>
        <?php foreach ($topFavCards as $c): $cnt = $favCount[$c['id']] ?? 0; ?>
        <tr><td>#<?=$c['id']?></td><td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e(mb_substr($c['text'],0,60))?></td><td><span class="badge badge-<?=$c['level']?>"><?=$c['level']?></span></td><td><?=$cnt?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php else: ?>
        <p style="font-size:13px;color:rgba(255,255,255,.3)">Пока нет лайков от игроков</p>
        <?php endif; ?>
    </div>
</div>

<h2>Последние сессии</h2>
<?php if ($recentSessions): ?>
<table><thead><tr><th>Код</th><th>Режим</th><th>Игроки</th><th>Дата</th><th>Лайков</th></tr></thead><tbody>
<?php foreach ($recentSessions as $s):
    $players = json_decode($s['players'], true);
    $names = implode(', ', array_column($players, 'name'));
    $favs = json_decode($s['favorite_card_ids'], true);
    $favCount = count($favs ?? []);
?>
<tr><td style="font-size:11px;color:rgba(255,255,255,.3)"><?=e(mb_substr($s['code'],0,12))?>…</td><td><?=e($s['mode'])?></td><td><?=e($names)?></td><td style="font-size:11px;color:rgba(255,255,255,.3)"><?=e($s['created_at'])?></td><td><?=$favCount > 0 ? '❤ '.$favCount : '—'?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?>
<p style="font-size:13px;color:rgba(255,255,255,.3)">Нет сессий</p>
<?php endif; ?>
</body>
</html>