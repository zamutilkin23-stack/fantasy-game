<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['show_screenshots','show_features','show_categories_detail','show_levels_count','screenshot_placeholder'];
    foreach ($keys as $k) {
        $v = $k === 'screenshot_placeholder' ? ($_POST[$k] ?? '') : (!empty($_POST[$k]) ? '1' : '0');
        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=?");
        $stmt->execute([$k, $v, $v]);
    }
    $ok = true;
}

$settings = $pdo->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Настройки — Админка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#14042d;color:#fff;padding:24px;min-height:100vh}
a{color:#00A7D1;text-decoration:none;transition:.2s}
a:hover{color:#F663B3}
h1{font-size:28px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.header-bar a{font-size:14px;padding:8px 16px;border-radius:60px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff}
.header-bar a:hover{background:rgba(246,99,179,.1);border-color:#F663B3}
.form-wrap{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;max-width:600px}
.form-wrap label{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.05);cursor:pointer}
.form-wrap label:last-child{border-bottom:none}
.form-wrap label span{font-size:15px}
.form-wrap label small{font-size:12px;color:rgba(255,255,255,.4);display:block;margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0;background:rgba(255,255,255,.1);border-radius:12px;transition:.2s}
.toggle:has(input:checked){background:linear-gradient(90deg,#F663B3,#00A7D1)}
.toggle input{display:none}
.toggle::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s}
.toggle:has(input:checked)::after{left:23px}
.form-wrap textarea{width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;margin-top:8px;resize:vertical;min-height:60px;font-family:inherit}
.form-wrap textarea:focus{border-color:#F663B3}
.form-actions{margin-top:20px}
.form-actions button{padding:10px 24px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer;font-size:14px;transition:.2s}
.form-actions button:hover{box-shadow:0 0 20px rgba(246,99,179,.3)}
.ok{padding:12px;border-radius:12px;background:rgba(76,175,80,.15);border:1px solid rgba(76,175,80,.3);color:#4CAF50;margin-bottom:16px;font-size:13px}
</style>
</head>
<body>

<div class="header-bar">
    <div>
        <h1>Настройки сайта</h1>
        <p style="font-size:13px;color:rgba(255,255,255,.4)">Управление видимостью блоков на главной</p>
    </div>
    <div>
        <a href="/admin/cards.php">📋 Карточки</a>
        <a href="/admin/users.php">👥 Пользователи</a>
        <a href="/admin/logout.php">🚪 Выйти</a>
    </div>
</div>

<?php if (!empty($ok)): ?><div class="ok">✓ Настройки сохранены</div><?php endif; ?>

<form method="post" class="form-wrap">
    <label>
        <div class="toggle"><input type="checkbox" name="show_screenshots" value="1" <?=($settings['show_screenshots']??'1')==='1'?'checked':''?>></div>
        <div><span>Галерея скриншотов</span><small>Показывать блок «Игровой интерфейс» на главной</small></div>
    </label>
    <label>
        <div class="toggle"><input type="checkbox" name="show_features" value="1" <?=($settings['show_features']??'1')==='1'?'checked':''?>></div>
        <div><span>Особенности игры</span><small>Показывать блок «Особенности игры» (6 карточек)</small></div>
    </label>
    <label>
        <div class="toggle"><input type="checkbox" name="show_categories_detail" value="1" <?=($settings['show_categories_detail']??'1')==='1'?'checked':''?>></div>
        <div><span>Разнообразие заданий</span><small>Показывать блок с категориями и описаниями</small></div>
    </label>
    <label>
        <div class="toggle"><input type="checkbox" name="show_levels_count" value="1" <?=($settings['show_levels_count']??'1')==='1'?'checked':''?>></div>
        <div><span>Количество заданий</span><small>Показывать блок с цифрами (500+/1500+/1000+)</small></div>
    </label>
    <label style="flex-direction:column;align-items:stretch;cursor:default">
        <div style="display:flex;align-items:center;gap:12px;width:100%">
            <span>Текст-заглушка</span>
            <small style="color:rgba(255,255,255,.4);font-size:12px">Показывается вместо скриншотов, если галерея выключена</small>
        </div>
        <textarea name="screenshot_placeholder"><?=e($settings['screenshot_placeholder']??'')?></textarea>
    </label>

    <div class="form-actions">
        <button type="submit">Сохранить</button>
    </div>
</form>
</body>
</html>