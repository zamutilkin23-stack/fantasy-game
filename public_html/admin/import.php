<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

$pdo = getDB();
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();

$fetchedCard = null;
$fetchedImg = '';
// Fetch one card from fanty.site
if (isset($_GET['fetch'])) {
    $ch = curl_init("https://fanty.site/ajax/demo-next-step");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        $data = json_decode($resp, true);
        if ($data && $data['status'] == 1) {
            $fetchedCard = $data['task'] ?? '';
            $fetchedImg = $data['img'][0] ?? '';
        }
    }
    if (!$fetchedCard) $error = 'Не удалось загрузить карточку с fanty.site';
}

// Save as draft
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_draft'])) {
    $text = trim($_POST['text'] ?? '');
    $level = $_POST['level'] ?? 'green';
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $performer = $_POST['performer'] ?? 'any';
    $target = $_POST['target'] ?? 'partner_f';
    $modes = $_POST['modes'] ?? ['couple'];
    $perfOrient = $_POST['performer_orientation'] ?? 'any';
    $targetOrient = $_POST['target_orientation'] ?? 'any';
    $imageUrl = trim($_POST['image_url'] ?? '') ?: null;
    $duration = $_POST['duration'] !== '' ? (int)$_POST['duration'] : null;
    if ($text) {
        $stmt = $pdo->prepare("INSERT INTO cards (text, level, category_id, performer, target, modes, performer_orientation, target_orientation, status, author_id, image_url, duration) VALUES (?,?,?,?,?,?,?,?,'draft',?,?,?)");
        $stmt->execute([$text, $level, $categoryId, $performer, $target, json_safe($modes), $perfOrient, $targetOrient, $_SESSION['admin_user_id'], $imageUrl, $duration]);
        $ok = 'Карточка сохранена как черновик (#'.$pdo->lastInsertId().')';
    }
}
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Импорт карточек — Админка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#0a0a0a;color:#fff;padding:24px;min-height:100vh}
a{color:#00A7D1;text-decoration:none;transition:.2s}a:hover{color:#F663B3}
h1{font-size:28px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.header-bar a{font-size:14px;padding:8px 16px;border-radius:60px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff}
.header-bar a:hover{background:rgba(246,99,179,.1);border-color:#F663B3}
.wrap{max-width:700px;margin:0 auto}
.card-preview{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;margin-bottom:16px}
.card-preview img{max-width:100%;border-radius:12px;margin-bottom:12px;max-height:200px}
.card-preview .text{font-size:18px;font-weight:600;line-height:1.4;margin-bottom:8px}
.form-wrap{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;margin-bottom:16px}
.form-wrap label{display:block;font-size:12px;color:rgba(255,255,255,.4);margin-bottom:4px;margin-top:12px}
.form-wrap textarea,.form-wrap select,.form-wrap input[type="text"],.form-wrap input[type="number"]{width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;font-family:inherit}
.form-wrap textarea:focus,.form-wrap select:focus,.form-wrap input:focus{border-color:#F663B3}
.form-wrap textarea{min-height:80px;resize:vertical}
.form-wrap select{appearance:none;cursor:pointer}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-actions{margin-top:16px;display:flex;gap:12px}
.form-actions button,.form-actions a{padding:10px 24px;border-radius:60px;font-weight:600;cursor:pointer;font-size:14px;text-decoration:none;display:inline-flex;align-items:center}
.btn-save{background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;border:none}
.btn-save:hover{box-shadow:0 0 20px rgba(246,99,179,.3)}
.btn-outline{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1)!important;color:rgba(255,255,255,.6)}
.btn-outline:hover{background:rgba(255,255,255,.1)}
.btn-pink{background:rgba(246,99,179,.2);border:1px solid rgba(246,99,179,.3);color:var(--pink)}
.btn-pink:hover{background:rgba(246,99,179,.3)}
.msg{padding:12px;border-radius:12px;margin-bottom:16px;font-size:13px}
.msg-ok{background:rgba(76,175,80,.15);border:1px solid rgba(76,175,80,.3);color:#4CAF50}
.msg-err{background:rgba(255,82,82,.15);border:1px solid rgba(255,82,82,.3);color:#FF5252}
.modes-grid{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}
.modes-grid label{display:flex;align-items:center;gap:4px;font-size:13px;color:rgba(255,255,255,.6);cursor:pointer;padding:6px 12px;border-radius:60px;border:1px solid rgba(255,255,255,.1)}
.modes-grid label:has(input:checked){border-color:#F663B3;background:rgba(246,99,179,.1)}
.modes-grid input{display:none}
</style>
</head>
<body>
<div class="header-bar">
    <div><h1>Импорт с fanty.site</h1></div>
    <div>
        <a href="/admin/cards.php">📋 Все карточки</a>
        <a href="/admin/dashboard.php">📊 Дашборд</a>
        <a href="/admin/categories.php">📁 Категории</a>
        <a href="/admin/logout.php">🚪 Выйти</a>
    </div>
</div>

<div class="wrap">
    <a href="/admin/import.php?fetch=1" class="btn-pink" style="padding:10px 24px;border-radius:60px;display:inline-block;font-weight:600;font-size:14px;cursor:pointer;margin-bottom:24px">🎲 Загрузить случайную карточку с fanty.site</a>

    <?php if (!empty($error)): ?><div class="msg msg-err"><?=e($error)?></div><?php endif; ?>
    <?php if (!empty($ok)): ?><div class="msg msg-ok"><?=e($ok)?></div><?php endif; ?>

    <?php if ($fetchedCard !== null): ?>
    <div class="card-preview">
        <?php if ($fetchedImg): ?><img src="<?=e($fetchedImg)?>" alt=""><br><?php endif; ?>
        <div class="text"><?=e($fetchedCard)?></div>
        <div style="font-size:12px;color:rgba(255,255,255,.3);margin-top:4px">Источник: fanty.site</div>
    </div>

    <form method="post" class="form-wrap">
        <label>Текст задания</label>
        <textarea name="text"><?=e($fetchedCard)?></textarea>

        <div class="form-row">
            <div><label>Уровень</label>
                <select name="level">
                    <option value="green">🟢 Зелёный</option>
                    <option value="orange">🟠 Оранжевый</option>
                    <option value="purple">🟣 Фиолетовый</option>
                    <option value="red">🔴 Красный</option>
                </select></div>
            <div><label>Категория</label>
                <select name="category_id">
                    <option value="">— без категории —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?=$cat['id']?>"><?=e($cat['name'])?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>

        <div class="form-row">
            <div><label>Исполнитель</label>
                <select name="performer">
                    <option value="any">👤 Любой</option>
                    <option value="male">♂ Мужчина</option>
                    <option value="female">♀ Женщина</option>
                    <option value="all">👥 Все</option>
                </select></div>
            <div><label>Цель</label>
                <select name="target">
                    <option value="partner_f">♀ Партнёрша</option>
                    <option value="partner_m">♂ Партнёр</option>
                    <option value="self">🙋 Себя</option>
                    <option value="each_other">🤝 Друг друга</option>
                    <option value="all">👥 Всех</option>
                </select></div>
        </div>

        <div class="form-row">
            <div><label>Ориентация исполнителя</label>
                <select name="performer_orientation">
                    <option value="any">👤 Любая</option>
                    <option value="hetero">💑 Гетеро</option>
                    <option value="bi">🌈 Би</option>
                </select></div>
            <div><label>Ориентация цели</label>
                <select name="target_orientation">
                    <option value="any">👤 Любая</option>
                    <option value="hetero">💑 Гетеро</option>
                    <option value="bi">🌈 Би</option>
                </select></div>
        </div>

        <label>Режимы</label>
        <div class="modes-grid">
            <label><input type="checkbox" name="modes[]" value="couple" checked>♂♀ Пара</label>
            <label><input type="checkbox" name="modes[]" value="wmw">♀♂♀ ЖМЖ</label>
            <label><input type="checkbox" name="modes[]" value="mwm">♂♀♂ МЖМ</label>
            <label><input type="checkbox" name="modes[]" value="mwmw">♂♀♂♀ МЖМЖ</label>
        </div>

        <label>Изображение (URL)</label>
        <input type="text" name="image_url" value="<?=e($fetchedImg)?>">

        <label>Время выполнения (сек)</label>
        <input type="number" name="duration" placeholder="Оставьте пустым — без таймера" min="10" max="3600">

        <div class="form-actions">
            <button type="submit" name="save_draft" class="btn-save">💾 Сохранить как черновик</button>
            <a href="/admin/import.php" class="btn-outline" style="padding:10px 24px">✕ Отмена</a>
        </div>
    </form>
    <?php elseif (!isset($_GET['fetch'])): ?>
    <div style="text-align:center;padding:60px 0;color:rgba(255,255,255,.3)">
        <div style="font-size:48px;margin-bottom:16px">👆</div>
        <p>Нажмите кнопку выше, чтобы загрузить случайную карточку с fanty.site</p>
        <p style="font-size:13px;margin-top:8px">Можно редактировать текст и поля перед сохранением</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>