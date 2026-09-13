<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

$pdo = getDB();
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
$subcategories = $pdo->query("SELECT s.*, c.slug as cat_slug FROM subcategories s JOIN categories c ON c.id = s.category_id ORDER BY s.category_id, s.sort_order")->fetchAll();
$subsByCat = [];
foreach ($subcategories as $s) $subsByCat[$s['category_id']][] = $s;
$uploadDir = __DIR__ . '/../img/cards/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_image') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cards SET image_url = NULL, updated_at = datetime('now') WHERE id = ?");
            $stmt->execute([$id]);
        }
        redirect('/admin/cards.php?edit=' . $id);
    }

    $uploadedUrl = null;
    if (!empty($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['card_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $filename = 'card_' . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($_FILES['card_image']['tmp_name'], $uploadDir . $filename);
            $uploadedUrl = '/img/cards/' . $filename;
        }
    }

    $urlFromField = trim($_POST['image_url'] ?? '');
    $finalImageUrl = $uploadedUrl ?: ($urlFromField ?: null);

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $level = $_POST['level'] ?? 'green';
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $subcategoryId = (int)($_POST['subcategory_id'] ?? 0) ?: null;
        $performer = $_POST['performer'] ?? 'any';
        $target = $_POST['target'] ?? 'partner_f';
        $performerOrient = $_POST['performer_orientation'] ?? 'any';
        $targetOrient = $_POST['target_orientation'] ?? 'any';
        $modes = $_POST['modes'] ?? [];
        $status = $_POST['status'] ?? 'draft';
        $isFinale = (int)(!empty($_POST['is_finale']));
        $isFavorite = (int)(!empty($_POST['is_favorite']));
        $promptOverride = trim($_POST['prompt_override'] ?? '');
        $modesJson = json_safe($modes);

        if ($text && !empty($modes)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO cards (text, level, category_id, subcategory_id, performer, target, performer_orientation, target_orientation, modes, status, author_id, is_finale, is_favorite, prompt_override, image_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$text, $level, $categoryId, $subcategoryId, $performer, $target, $performerOrient, $targetOrient, $modesJson, $status, $_SESSION['admin_user_id'], $isFinale, $isFavorite, $promptOverride ?: null, $finalImageUrl]);
            } else {
                $sql = "UPDATE cards SET text=?, level=?, category_id=?, subcategory_id=?, performer=?, target=?, performer_orientation=?, target_orientation=?, modes=?, status=?, is_finale=?, is_favorite=?, prompt_override=?, updated_at=datetime('now')";
                $params = [$text, $level, $categoryId, $subcategoryId, $performer, $target, $performerOrient, $targetOrient, $modesJson, $status, $isFinale, $isFavorite, $promptOverride ?: null];
                if ($finalImageUrl) { $sql .= ", image_url=?"; $params[] = $finalImageUrl; }
                $sql .= " WHERE id=?";
                $params[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
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

$editCard = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCard = $stmt->fetch();
}

$cards = $pdo->query("SELECT c.*, u.login as author_name FROM cards c LEFT JOIN users u ON c.author_id = u.id ORDER BY c.id DESC")->fetchAll();
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>Карточки — Админка</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#0a0a0a;color:#fff;padding:24px;min-height:100vh}
a{color:#00A7D1;text-decoration:none;transition:.2s}
a:hover{color:#F663B3}
h1{font-size:28px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
h2{font-size:18px;margin:24px 0 16px;color:rgba(255,255,255,.6)}
.header-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.header-bar a{font-size:14px;padding:8px 16px;border-radius:60px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff}
.header-bar a:hover{background:rgba(246,99,179,.1);border-color:#F663B3}
.header-bar .active{background:rgba(246,99,179,.15);border-color:#F663B3}
.form-wrap{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;margin-bottom:32px;max-width:700px}
.form-wrap label{display:block;font-size:12px;color:rgba(255,255,255,.4);margin-bottom:4px;margin-top:12px}
.form-wrap label:first-child{margin-top:0}
.form-wrap textarea,.form-wrap select,.form-wrap input[type="text"],.form-wrap input[type="url"]{width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;transition:.2s;font-family:inherit}
.form-wrap textarea:focus,.form-wrap select:focus,.form-wrap input[type="text"]:focus,.form-wrap input[type="url"]:focus{border-color:#F663B3}
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
.card-preview{width:180px;flex-shrink:0}
.card-preview img{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.15);display:block}
.card-preview__hint{text-align:center;font-size:11px;color:rgba(255,255,255,.3);margin-top:6px}
.img-upload{margin-top:4px}
.img-upload__row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.img-upload__url{flex:1;width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;font-family:inherit}
.img-upload__url:focus{border-color:#F663B3}
.img-upload__divider{text-align:center;font-size:12px;color:rgba(255,255,255,.2);margin:4px 0}
.img-upload__file{display:none}
.img-upload__filename{font-size:12px;color:rgba(255,255,255,.4)}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:24px}
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
.image-search{display:flex;gap:8px;margin-top:8px}
.image-search input{flex:1;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;font-family:inherit}
.image-search input:focus{border-color:#F663B3}
.image-search button{padding:10px 20px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer;font-size:13px}
.image-results{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-top:12px;max-height:400px;overflow-y:auto}
.image-results img{width:100%;border-radius:8px;border:2px solid transparent;cursor:pointer;transition:.2s;object-fit:cover;height:100px}
.image-results img:hover,.image-results img.selected{border-color:#F663B3}
.image-results .img-title{font-size:10px;color:rgba(255,255,255,.3);text-align:center;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.search-hint{font-size:12px;color:rgba(255,255,255,.3);margin-top:4px}
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

<div class="form-wrap" style="display:flex;gap:24px;align-items:flex-start">
    <?php if ($editCard && $editCard['image_url']): ?>
    <div class="card-preview">
        <img src="<?=e($editCard['image_url'])?>" alt="Превью" loading="lazy">
        <div class="card-preview__hint">Текущее изображение</div>
    </div>
    <?php endif; ?>
    <div style="flex:1">
    <h2><?=$editCard?'Редактировать карточку #'.$editCard['id']:'Создать новую карточку'?></h2>
    <form method="post" enctype="multipart/form-data">
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
            <div class="form-row">
            <div>
                <label>Категория</label>
                <select name="category_id" onchange="updateSubcategories(this.value)">
                    <option value="">— без категории —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?=$cat['id']?>" <?=($editCard['category_id']??'')==$cat['id']?'selected':''?>><?=e($cat['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Подкатегория</label>
                <select name="subcategory_id" id="subcategorySelect">
                    <option value="">— без подкатегории —</option>
                    <?php foreach ($subcategories as $sub): ?>
                    <option value="<?=$sub['id']?>" data-cat="<?=$sub['category_id']?>" <?=($editCard['subcategory_id']??'')==$sub['id']?'selected':''?>><?=e($sub['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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

        <div class="form-row">
            <div>
                <label>Ориентация исполнителя</label>
                <select name="performer_orientation">
                    <option value="any" <?=($editCard['performer_orientation']??'any')==='any'?'selected':''?>>👤 Любая</option>
                    <option value="hetero" <?=($editCard['performer_orientation']??'')==='hetero'?'selected':''?>>💑 Гетеро</option>
                    <option value="bi" <?=($editCard['performer_orientation']??'')==='bi'?'selected':''?>>🌈 Би</option>
                </select>
            </div>
            <div>
                <label>Ориентация цели</label>
                <select name="target_orientation">
                    <option value="any" <?=($editCard['target_orientation']??'any')==='any'?'selected':''?>>👤 Любая</option>
                    <option value="hetero" <?=($editCard['target_orientation']??'')==='hetero'?'selected':''?>>💑 Гетеро</option>
                    <option value="bi" <?=($editCard['target_orientation']??'')==='bi'?'selected':''?>>🌈 Би</option>
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

        <label>Изображение</label>
        <div class="img-upload">
            <div class="img-upload__row">
                <input type="url" name="image_url" value="<?=e($editCard['image_url']??'')?>" placeholder="Ссылка на картинку (URL)" class="img-upload__url">
            </div>
            <div class="img-upload__divider">или</div>
            <div class="img-upload__row">
                <input type="file" name="card_image" accept="image/*" class="img-upload__file" id="cardImageInput">
                <label for="cardImageInput" class="btn-cancel" style="font-size:13px;padding:8px 16px;cursor:pointer;display:inline-block">📁 Выбрать файл</label>
                <span class="img-upload__filename" id="fileName"></span>
                <?php if ($editCard && $editCard['image_url']): ?>
                <button type="submit" name="action" value="delete_image" class="delete" style="padding:8px 16px;border-radius:60px;cursor:pointer;background:rgba(255,82,82,.1);border:1px solid rgba(255,82,82,.3);color:#FF5252;font-size:13px" onclick="return confirm('Удалить изображение?')">🗑 Удалить</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="action" value="<?=$editCard?'update':'create'?>" class="btn-save"><?=$editCard?'Сохранить':'Создать'?></button>
            <?php if ($editCard): ?>
            <a href="/admin/cards.php" class="btn-cancel" style="display:inline-flex;align-items:center;padding:10px 24px;border-radius:60px;text-decoration:none">Отмена</a>
            <?php endif; ?>
        </div>
    </form>
</div>
</div>

<!-- Cards list -->
<?php if (empty($cards)): ?>
<div class="empty">Нет карточек. Создайте первую выше.</div>
<?php else: ?>
<table>
<thead>
<tr>
    <th>ID</th><th>Текст</th><th>Уровень</th><th>Категория</th><th>Исполнитель</th><th>Цель</th><th>Режимы</th><th>Статус</th><th>Картинка</th><th>Автор</th><th></th>
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
    <td style="font-size:12px;color:rgba(255,255,255,.4)"><?=$card['image_url']?'<a href="'.e($card['image_url']).'" target="_blank" style="color:#4CAF50">✓ есть</a>':'—'?></td>
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
document.getElementById('cardImageInput')?.addEventListener('change', function() {
    document.getElementById('fileName').textContent = this.files[0]?.name || '';
});

function updateSubcategories(catId) {
    const select = document.getElementById('subcategorySelect');
    select.value = '';
    select.querySelectorAll('option').forEach(o => {
        o.style.display = o.value === '' || o.dataset.cat == catId ? '' : 'none';
    });
}
// Init on load
updateSubcategories(document.querySelector('select[name="category_id"]')?.value || '');
</script>
</body>
</html>