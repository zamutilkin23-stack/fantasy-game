<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($login, $password)) redirect('/admin/cards.php');
    $error = 'Неверный логин или пароль';
}
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Вход — Админка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#14042d;color:#fff;min-height:100vh;display:grid;place-items:center}
.card{background:rgba(255,255,255,.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.1);border-radius:24px;padding:40px;max-width:400px;width:90%}
h1{font-size:24px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
p{color:rgba(255,255,255,.4);margin-bottom:24px;font-size:14px}
.err{padding:12px;border-radius:12px;background:rgba(255,82,82,.15);border:1px solid rgba(255,82,82,.3);color:#ff5252;margin-bottom:16px;font-size:13px}
input{width:100%;height:48px;border-radius:24px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.05);color:#fff;padding:0 20px;font-size:16px;margin-bottom:16px;outline:none;transition:.2s}
input:focus{border-color:#F663B3}
button{width:100%;height:48px;border-radius:24px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-size:16px;font-weight:600;cursor:pointer;transition:.3s}
button:hover{box-shadow:0 0 30px rgba(246,99,179,.3);transform:scale(1.02)}
</style>
</head>
<body>
<div class="card">
<h1>Админка</h1>
<p>Вход в панель управления</p>
<?php if (!empty($error)): ?><div class="err"><?=e($error)?></div><?php endif; ?>
<form method="post">
<input type="text" name="login" placeholder="Логин" required autocomplete="off" autofocus>
<input type="password" name="password" placeholder="Пароль" required>
<button>Войти</button>
</form>
</div>
</body>
</html>