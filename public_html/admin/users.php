<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireRole('admin');

$pdo = getDB();

// Create editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'editor';
        if ($login && $password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $pdo->prepare("INSERT INTO users (login, password_hash, role) VALUES (?, ?, ?)")->execute([$login, $hash, $role]);
            } catch (Exception $e) {
                $error = 'Логин уже существует';
            }
        }
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id !== (int)$_SESSION['admin_user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        }
    }
    redirect('/admin/users.php');
}

$users = $pdo->query("SELECT * FROM users ORDER BY role DESC, id")->fetchAll();
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Пользователи — Админка</title>
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
.form-wrap{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;max-width:500px;margin-bottom:32px}
.form-wrap label{display:block;font-size:12px;color:rgba(255,255,255,.4);margin-bottom:4px;margin-top:12px}
.form-wrap input,.form-wrap select{width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;font-family:inherit}
.form-wrap input:focus,.form-wrap select:focus{border-color:#F663B3}
.form-wrap select{appearance:none;cursor:pointer}
.form-actions{margin-top:16px}
.form-actions button{padding:10px 24px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer;font-size:14px;transition:.2s}
table{width:100%;border-collapse:collapse;font-size:13px;max-width:500px}
th{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.4);font-size:11px;text-transform:uppercase;letter-spacing:1px}
td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.05)}
.badge-admin{background:rgba(246,99,179,.2);color:#F663B3;padding:2px 8px;border-radius:60px;font-size:11px}
.badge-editor{background:rgba(0,167,209,.15);color:#00A7D1;padding:2px 8px;border-radius:60px;font-size:11px}
.actions button{font-size:12px;padding:4px 12px;border-radius:60px;cursor:pointer;border:1px solid rgba(255,82,82,.3);background:transparent;color:#FF5252;transition:.2s}
.actions button:hover{background:rgba(255,82,82,.1)}
.err{padding:12px;border-radius:12px;background:rgba(255,82,82,.15);border:1px solid rgba(255,82,82,.3);color:#FF5252;margin-bottom:16px;font-size:13px}
</style>
</head>
<body>

<div class="header-bar">
    <div>
        <h1>Пользователи</h1>
        <p style="font-size:13px;color:rgba(255,255,255,.4)">Управление редакторами</p>
    </div>
    <div>
        <a href="/admin/cards.php">📋 Карточки</a>
        <a href="/admin/settings.php">⚙️ Настройки</a>
        <a href="/admin/logout.php">🚪 Выйти</a>
    </div>
</div>

<div class="form-wrap">
    <h2 style="font-size:16px;margin-bottom:4px">Создать пользователя</h2>
    <?php if (!empty($error)): ?><div class="err"><?=e($error)?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="create">
        <label>Логин</label>
        <input type="text" name="login" required autocomplete="off">
        <label>Пароль</label>
        <input type="password" name="password" required>
        <label>Роль</label>
        <select name="role">
            <option value="editor">Редактор</option>
            <option value="admin">Администратор</option>
        </select>
        <div class="form-actions"><button type="submit">Создать</button></div>
    </form>
</div>

<table>
<thead><tr><th>Логин</th><th>Роль</th><th>Создан</th><th></th></tr></thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
    <td><?=e($u['login'])?> <?=$u['id']==$_SESSION['admin_user_id']?'<span style="font-size:11px;color:rgba(255,255,255,.3)">(вы)</span>':''?></td>
    <td><span class="badge-<?=$u['role']?>"><?=$u['role']==='admin'?'Администратор':'Редактор'?></span></td>
    <td style="font-size:12px;color:rgba(255,255,255,.4)"><?=e($u['created_at'])?></td>
    <td class="actions">
        <?php if ($u['id'] != $_SESSION['admin_user_id']): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Удалить пользователя?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$u['id']?>">
            <button type="submit">Удалить</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>