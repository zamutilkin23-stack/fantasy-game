<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

$pdo = getDB();
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();

// Handle create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $level = $_POST['level'] ?? 'green';
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $performer = $_POST['performer'] ?? 'any';
        $target = $_POST['target'] ?? 'partner_f';
        $modes = $_POST['modes'] ?? [];
        $status = $_POST['status'] ?? 'draft';
        $isFinale = (int)(!empty($_POST['is_finale']));
        $isFavorite = (int)(!empty($_POST['is_favorite']));
        $promptOverride = trim($_POST['prompt_override'] ?? '');
        $modesJson = json_safe($modes);

        if ($text) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO cards (text, level, category_id, performer, target, modes, status, author_id, is_finale, is_favorite, prompt_override) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$text, $level, $categoryId, $performer, $target, $modesJson, $status, $_SESSION['admin_user_id'], $isFinale, $isFavorite, $promptOverride ?: null]);
            } else {
                $stmt = $pdo->prepare("UPDATE cards SET text=?, level=?, category_id=?, performer=?, target=?, modes=?, status=?, is_finale=?, is_favorite=?, prompt_override=?, updated_at=datetime('now') WHERE id=?");
                $stmt->execute([$text, $level, $categoryId, $performer, $target, $modesJson, $status, $isFinale, $isFavorite, $promptOverride ?: null, $id]);
            }
        }
        redirect('/admin/cards.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM cards WHERE id=?")->execute([$id]);
        }
        redirect('/admin/cards.php');
    }
}

// Get edit card
$editCard = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCard = $stmt->fetch();
}

// List all cards
$cards = $pdo->query("SELECT c.*, u.login as author_name FROM cards c LEFT JOIN users u ON c.author_id = u.id ORDER BY c.id DESC")->fetchAll();
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Карточки — Админка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#14042d;color:#fff;padding:24px;min-height:100vh}
a{color:#00A7D1;text-decoration:none;transition:.2s}
a:hover{color:#F663B3}
h1{font-size:28px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
h2{font-size:18px;margin:24px 0 16px;color:rgba(255,255,255,.6)}
.header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.header-bar a{font-size:14px;padding:8px 16px;border-radius:60px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff}
.header-bar a:hover{background:rgba(246,99,179,.1);border-color:#F663B3}
.header-bar .active{background:rgba(246,99,179,.15);border-color:#F663B3}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.4);font-size:11px;text-transform:uppercase;letter-spacing:1px}
td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.05)}
tr:hover td{background:rgba(255,255,255,.02)}
.badge{padding:2px 8px;border-radius:60px;font-size:11px;font-weight:600}
.badge-green{background:rgba(76,175,80,.2);color:#4CAF50}
.badge-orange{background:rgba(255,107,53,.2);color:#FF6B35}
.badge-red{background:rgba(255,82,82,.2);color:#FF5252}
.badge-draft{background:rgba(255,255,255,.1);color:rgba(255,255,255,.4)}
.badge-review{background:rgba(255,193,7,.15);color:#FFC107}
.badge-published{background:rgba(76,175,80,.15);color:#4CAF50}
.actions{display:flex;gap:8px}
.actions a,.actions button{font-size:12px;padding:4px 12px;border-radius:60px;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.6);transition:.2s}
.actions a:hover,.actions button:hover{background:rgba(255,255,255,.05)}
.actions .delete{color:#FF5252;border-color:rgba(255,82,82,.3)}
.actions .delete:hover{background:rgba(255,82,82,.1)}
.empty{text-align:center;padding:48px;color:rgba(255,255,255,.3);font-size:14px}

/* Form */
.form-wrap{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;margin-bottom:32px;max-width:700px}
.form-wrap label{display:block;font-size:12px;color:rgba(255,255,255,.4);margin-bottom:4px;margin-top:12px}
.form-wrap label:first-child{margin-top:0}
.form-wrap textarea,.form-wrap select,.form-wrap input[type="text"]{width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;transition:.2s;font-family:inherit}
.form-wrap textarea:focus,.form-wrap select:focus,.form-wrap input[type="text"]:focus{border-color:#F663B3}
.form-wrap textarea{min-height:80px;resize:vertical}
.form-wrap select{appearance:none;cursor:pointer}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-actions{display:flex;gap:12px;margin-top:16px}
.form-actions button{padding:10px 24px;border-radius:60px;border:none;font-weight:600;cursor:pointer;font-size:14px;transition:.2s}
.btn-save{background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff}
.btn-save:hover{box-shadow:0 0 20px rgba(246,99,179,.3)}
.btn-cancel{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1)!important;color:rgba(255,255,255,.6)}
.btn-cancel:hover{background:rgba(255,255,255,.1)}
.modes-grid{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}
.modes-grid label{display:flex;align-items:center;gap:4px;font-size:13px;color:rgba(255,255,255,.6);cursor:pointer;margin-top:0;padding:6px 12px;border-radius:60px;border:1px solid rgba(255,255,255,.1);transition:.2s}
.modes-grid label:has(input:checked){border-color:#F663B3;background:rgba(246,99,179,.1)}
.modes-grid input{display:none}
</style>
</head>
<body>

<div class="header-bar">
    <div>
        <h1>Админка</h1>
        <p style="font-size:13px;color:rgba(255,255,255,.4)">Управление карточками заданий</p>
    </div>
    <div>
        <a href="/admin/cards.php" class="<?=!isset($_GET['edit'])?'active':''?>">📋 Все карточки</a>
        <a href="/admin/settings.php">⚙️ Настройки</a>
        <a href="/admin/users.php">👥 Пользователи</a>
        <a href="/admin/logout.php">🚪 Выйти</a>
    </div>
</div>

<!-- Edit / Create form -->
<div class="form-wrap">
    <h2><?=$editCard?'Редактировать карточку #'.$editCard['id']:'Создать новую карточку'?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?=$editCard?'update':'create'?>">
        <?php if ($editCard): ?>
        <input type="hidden" name="id" value="<?=$editCard['id']?>">
        <?php endif; ?>

        <label>Текст задания *</label>
        <textarea name="text" required><?=e($editCard['text']??'')?></textarea>

        <div class="form-row">
            <div>
                <label>Уровень</label>
                <select name="level">
                    <option value="green" <?=($editCard['level']??'')==='green'?'selected':''?>>🟢 Зелёный (флирт)</option>
                    <option value="orange" <?=($editCard['level']??'')==='orange'?'selected':''?>>🟠 Оранжевый (ласки)</option>
                    <option value="red" <?=($editCard['level']??'')==='red'?'selected':''?>>🔴 Красный (секс)</option>
                </select>
            </div>
            <div>
                <label>Категория</label>
                <select name="category_id">
                    <option value="">— без категории —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?=$cat['id']?>" <?=($editCard['category_id']??'')==$cat['id']?'selected':''?>><?=e($cat['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label>Исполнитель (кто выполняет)</label>
                <select name="performer">
                    <option value="male" <?=($editCard['performer']??'')==='male'?'selected':''?>>♂ Мужчина</option>
                    <option value="female" <?=($editCard['performer']??'')==='female'?'selected':''?>>♀ Женщина</option>
                    <option value="any" <?=($editCard['performer']??'')==='any'?'selected':''?>>👤 Любой</option>
                    <option value="all" <?=($editCard['performer']??'')==='all'?'selected':''?>>👥 Все игроки</option>
                </select>
            </div>
            <div>
                <label>Цель (на кого направлено)</label>
                <select name="target">
                    <option value="partner_f" <?=($editCard['target']??'')==='partner_f'?'selected':''?>>♀ На партнёршу</option>
                    <option value="partner_m" <?=($editCard['target']??'')==='partner_m'?'selected':''?>>♂ На партнёра</option>
                    <option value="self" <?=($editCard['target']??'')==='self'?'selected':''?>>🙋 На себя</option>
                    <option value="each_other" <?=($editCard['target']??'')==='each_other'?'selected':''?>>🤝 Друг на друга</option>
                    <option value="all" <?=($editCard['target']??'')==='all'?'selected':''?>>👥 На всех</option>
                </select>
            </div>
        </div>

        <label>Режимы (где доступна)</label>
        <div class="modes-grid">
            <?php $cardModes = json_decode($editCard['modes']??'[]', true) ?? []; ?>
            <label><input type="checkbox" name="modes[]" value="couple" <?=in_array('couple',$cardModes)||empty($editCard)?'checked':''?>> Пара</label>
            <label><input type="checkbox" name="modes[]" value="wmw" <?=in_array('wmw',$cardModes)?'checked':''?>> ЖМЖ</label>
            <label><input type="checkbox" name="modes[]" value="mwm" <?=in_array('mwm',$cardModes)?'checked':''?>> МЖМ</label>
            <label><input type="checkbox" name="modes[]" value="mwmw" <?=in_array('mwmw',$cardModes)?'checked':''?>> МЖМЖ</label>
        </div>

        <div class="form-row">
            <div>
                <label>Статус</label>
                <select name="status">
                    <option value="draft" <?=($editCard['status']??'')==='draft'?'selected':''?>>Черновик</option>
                    <option value="review" <?=($editCard['status']??'')==='review'?'selected':''?>>На проверке</option>
                    <option value="published" <?=($editCard['status']??'')==='published'?'selected':''?>>Опубликовано</option>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <div style="display:flex;gap:16px;padding-top:4px">
                    <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer">
                        <input type="checkbox" name="is_finale" value="1" <?=$editCard['is_finale']??0?'checked':''?>> Финальная
                    </label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer">
                        <input type="checkbox" name="is_favorite" value="1" <?=$editCard['is_favorite']??0?'checked':''?>> Избранное
                    </label>
                </div>
            </div>
        </div>

        <label>Промпт для AI (если нужно изменить текст для генерации картинки)</label>
        <input type="text" name="prompt_override" value="<?=e($editCard['prompt_override']??'')?>" placeholder="Оставьте пустым — будет использован текст задания">

        <div class="form-actions">
            <button type="submit" class="btn-save"><?=$editCard?'Сохранить':'Создать'?></button>
            <?php if ($editCard): ?>
            <button type="button" class="btn-save" onclick="generateImage(<?=$editCard['id']?>)" style="background:rgba(190,90,255,.3);border:1px solid rgba(190,90,255,.3)">🎨 Сгенерировать картинку</button>
            <a href="/admin/cards.php" class="btn-cancel" style="display:inline-flex;align-items:center;padding:10px 24px;border-radius:60px;text-decoration:none">Отмена</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Cards list -->
<?php if (empty($cards)): ?>
<div class="empty">Нет карточек. Создайте первую выше.</div>
<?php else: ?>
<table>
<thead>
<tr>
    <th>ID</th><th>Текст</th><th>Уровень</th><th>Категория</th><th>Исполнитель</th><th>Цель</th><th>Режимы</th><th>Статус</th><th>Автор</th><th></th>
</tr>
</thead>
<tbody>
<?php foreach ($cards as $card): ?>
<tr>
    <td style="color:rgba(255,255,255,.3)">#<?=$card['id']?></td>
    <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e(mb_substr($card['text'],0,80))?></td>
    <td><span class="badge badge-<?=$card['level']?>"><?=['green'=>'🟢','orange'=>'🟠','red'=>'🔴'][$card['level']]??''?></span></td>
    <td style="font-size:12px;color:rgba(255,255,255,.5)"><?php
        $c = array_filter($categories, fn($x)=>$x['id']==$card['category_id']);
        echo $c ? e(current($c)['name']) : '—';
    ?></td>
    <td><?=['male'=>'♂','female'=>'♀','any'=>'👤','all'=>'👥'][$card['performer']]??$card['performer']?></td>
    <td><?=['partner_f'=>'♀','partner_m'=>'♂','self'=>'🙋','each_other'=>'🤝','all'=>'👥'][$card['target']]??$card['target']?></td>
    <td style="font-size:11px;color:rgba(255,255,255,.4)"><?php
        $ms = json_decode($card['modes'],true)??[];
        echo $ms ? implode(' | ', $ms) : '—';
    ?></td>
    <td><span class="badge badge-<?=$card['status']?>"><?=$card['status']?></span></td>
    <td style="font-size:12px;color:rgba(255,255,255,.4)"><?=e($card['author_name']??'—')?></td>
    <td class="actions">
        <a href="/admin/cards.php?edit=<?=$card['id']?>">✏️</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$card['id']?>">
            <button type="submit" class="delete">🗑</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<script>
function generateImage(cardId) {
    const btn = event.target;
    btn.textContent = '⏳ Генерация...';
    btn.disabled = true;
    fetch('/admin/generate_image.php?card_id=' + cardId)
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                alert('Промпт для AI готов:\n\n' + d.prompt + '\n\nКартинка будет добавлена после генерации через внешний сервис.');
            } else {
                alert('Ошибка: ' + d.error);
            }
        })
        .catch(e => alert('Ошибка: ' + e.message))
        .finally(() => {
            btn.textContent = '🎨 Сгенерировать картинку';
            btn.disabled = false;
        });
}
</script>
</body>
</html>