<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($login && $password) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin'");
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Администратор уже создан. Удалите install.php.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (login, password_hash, role) VALUES (?, ?, 'admin')")->execute([$login, $hash]);
            $ok = true;
        }
    }
}
$adminExists = false;
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin'");
    $adminExists = (int)$stmt->fetchColumn() > 0;
} catch (Exception $e) {}
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Установка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#14042d;color:#fff;min-height:100vh;display:grid;place-items:center}
.card{background:rgba(255,255,255,.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.1);border-radius:24px;padding:40px;max-width:420px;width:90%}
h1{font-size:28px;margin-bottom:12px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
p{color:rgba(255,255,255,.6);margin-bottom:24px;line-height:1.6}
input{width:100%;height:48px;border-radius:24px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.05);color:#fff;padding:0 20px;font-size:16px;margin-bottom:16px;outline:none;transition:.2s}
input:focus{border-color:#F663B3}
button{width:100%;height:48px;border-radius:24px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-size:18px;font-weight:600;cursor:pointer;transition:.3s}
button:hover{transform:scale(1.02);box-shadow:0 0 30px rgba(246,99,179,.3)}
.msg{padding:12px;border-radius:12px;margin-bottom:16px;font-size:14px}
.ok{background:rgba(0,200,100,.15);color:#4caf50;border:1px solid rgba(0,200,100,.3)}
.err{background:rgba(255,50,50,.15);color:#ff5252;border:1px solid rgba(255,50,50,.3)}
</style>
</head>
<body>
<div class="card">
<h1>Установка</h1>
<p>Создание первого администратора</p>
<?php if ($adminExists): ?>
<div class="msg ok">✓ Администратор уже создан. <strong>Удалите install.php</strong> с сервера.</div>
<?php else: ?>
<?php if (!empty($error)): ?><div class="msg err"><?=e($error)?></div><?php endif; ?>
<?php if (!empty($ok)): ?><div class="msg ok">✓ Администратор создан. Удалите install.php.</div><?php endif; ?>
<form method="post">
<input type="text" name="login" placeholder="Логин" required autocomplete="off">
<input type="password" name="password" placeholder="Пароль" required>
<button type="submit">Создать</button>
</form>
<?php endif; ?>
</div>
</body>
</html>