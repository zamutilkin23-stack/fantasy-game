<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireRole('admin');

$pdo = getDB();

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cat') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($name && $slug) {
            $max = (int)$pdo->query("SELECT MAX(sort_order) FROM categories")->fetchColumn();
            $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?,?,?)")->execute([$name, $slug, $max + 1]);
        }
    }
    if ($action === 'edit_cat') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name) $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name, $id]);
    }
    if ($action === 'del_cat') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        $pdo->prepare("UPDATE cards SET category_id=NULL WHERE category_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM subcategories WHERE category_id=?")->execute([$id]);
    }

    if ($action === 'add_sub') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($catId && $name && $slug) {
            $max = (int)$pdo->prepare("SELECT MAX(sort_order) FROM subcategories WHERE category_id=?")->execute([$catId]) ? 0 : 0;
            $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM subcategories WHERE category_id=$catId")->fetchColumn();
            $pdo->prepare("INSERT INTO subcategories (category_id, name, slug, sort_order) VALUES (?,?,?,?)")->execute([$catId, $name, $slug, $max + 1]);
        }
    }
    if ($action === 'del_sub') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM subcategories WHERE id=?")->execute([$id]);
        $pdo->prepare("UPDATE cards SET subcategory_id=NULL WHERE subcategory_id=?")->execute([$id]);
    }

    redirect('/admin/categories.php');
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
$subcategories = $pdo->query("SELECT s.*, c.name as cat_name FROM subcategories s JOIN categories c ON c.id=s.category_id ORDER BY s.category_id, s.sort_order")->fetchAll();
$subsByCat = [];
foreach ($subcategories as $s) $subsByCat[$s['category_id']][] = $s;
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Категории — Админка</title>
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
.cat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px}
.cat-block{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px}
.cat-block h2{font-size:18px;margin-bottom:12px;color:rgba(255,255,255,.7)}
.cat-actions{display:flex;gap:8px;margin-top:8px}
.cat-actions a,.cat-actions button{padding:4px 12px;border-radius:60px;font-size:12px;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.6);transition:.2s;text-decoration:none}
.cat-actions button:hover,a:hover{background:rgba(255,255,255,.05)}
.sub-item{background:rgba(255,255,255,.03);border-radius:10px;padding:8px 12px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;font-size:13px}
.sub-name{color:rgba(255,255,255,.7)}
.add-form{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.add-form input{padding:8px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:13px;outline:none;flex:1}
.add-form button{padding:8px 16px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer;font-size:12px}
</style>
</head>
<body>
<div class="header-bar">
    <div><h1>Категории и подкатегории</h1></div>
    <div>
        <a href="/admin/cards.php">📋 Карточки</a>
        <a href="/admin/dashboard.php">📊 Дашборд</a>
        <a href="/admin/settings.php">⚙️ Настройки</a>
        <a href="/admin/logout.php">🚪 Выйти</a>
    </div>
</div>

<div class="cat-grid">
    <?php foreach ($categories as $cat): ?>
    <div class="cat-block">
        <h2><?=e($cat['name'])?> <span style="font-size:12px;color:rgba(255,255,255,.3)">(<?=$cat['slug']?>)</span></h2>
        <div class="cat-actions">
            <form method="post" style="display:inline" onsubmit="return prompt('Новое название:', '<?=e($cat['name'])?>') && (this.querySelector('[name=name]').value = prompt_result)">
                <input type="hidden" name="action" value="edit_cat">
                <input type="hidden" name="id" value="<?=$cat['id']?>">
                <input type="hidden" name="name" value="">
                <button type="submit">✏️</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Удалить категорию? Все подкатегории тоже удалятся.')">
                <input type="hidden" name="action" value="del_cat">
                <input type="hidden" name="id" value="<?=$cat['id']?>">
                <button type="submit" style="color:#FF5252">🗑</button>
            </form>
        </div>

        <?php if (!empty($subsByCat[$cat['id']])): ?>
        <div style="margin-top:12px">
            <?php foreach ($subsByCat[$cat['id']] as $sub): ?>
            <div class="sub-item">
                <span class="sub-name"><?=e($sub['name'])?></span>
                <form method="post" style="display:inline" onsubmit="return confirm('Удалить подкатегорию?')">
                    <input type="hidden" name="action" value="del_sub">
                    <input type="hidden" name="id" value="<?=$sub['id']?>">
                    <button type="submit" style="background:none;border:none;color:#FF5252;cursor:pointer;font-size:12px">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="add-form">
            <input type="text" id="subName_<?=$cat['id']?>" placeholder="Новая подкатегория">
            <input type="text" id="subSlug_<?=$cat['id']?>" placeholder="slug">
            <button onclick="addSub(<?=$cat['id']?>)">+ Добавить</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<form method="post" class="add-form" style="margin-top:32px;max-width:600px">
    <input type="hidden" name="action" value="add_cat">
    <input type="text" name="name" placeholder="Название новой категории" required style="flex:1;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none">
    <input type="text" name="slug" placeholder="slug (англ.)" required style="flex:1;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none">
    <button type="submit" style="padding:10px 24px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer">Создать категорию</button>
</form>

<script>
function addSub(catId) {
    const name = document.getElementById('subName_' + catId).value.trim();
    const slug = document.getElementById('subSlug_' + catId).value.trim();
    if (!name || !slug) { alert('Заполните название и slug'); return; }
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<input type="hidden" name="action" value="add_sub"><input type="hidden" name="category_id" value="' + catId + '"><input type="hidden" name="name" value="' + name + '"><input type="hidden" name="slug" value="' + slug + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>