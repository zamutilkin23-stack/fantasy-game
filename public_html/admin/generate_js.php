<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();
$pdo = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_img'])) {
    $cardId = (int)$_POST['card_id'];
    $svgContent = trim($_POST['svg_data'] ?? '');
    if ($cardId && $svgContent) {
        $filename = 'card_svg_' . $cardId . '_' . bin2hex(random_bytes(4)) . '.svg';
        file_put_contents(__DIR__ . '/../img/cards/' . $filename, $svgContent);
        $url = '/img/cards/' . $filename;
        $stmt = $pdo->prepare("UPDATE cards SET image_url = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$url, $cardId]);
        $saved = true;
    }
}
$cards = $pdo->query("SELECT id, text, level, performer, target FROM cards WHERE status='published' ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="utf-8"><title>JS-генератор иллюстраций</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Exo 2',sans-serif;background:#0a0a0a;color:#fff;padding:24px}
a{color:#00A7D1;text-decoration:none;transition:.2s}a:hover{color:#F663B3}
h1{font-size:28px;margin-bottom:8px;background:linear-gradient(90deg,#F663B3,#00A7D1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
h2{font-size:18px;margin:16px 0;color:rgba(255,255,255,.6)}
.bar{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px}
.bar a{font-size:14px;padding:8px 16px;border-radius:60px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff}
.bar a:hover{background:rgba(246,99,179,.1);border-color:#F663B3}
.panel{display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start}
.panel-left{flex:1;min-width:300px;max-width:500px}
.panel-right{flex:1;min-width:300px;text-align:center}
.ctrl{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px;margin-bottom:16px}
.ctrl label{display:block;font-size:12px;color:rgba(255,255,255,.4);margin-top:12px;margin-bottom:4px}
.ctrl select,.ctrl textarea{width:100%;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font-size:14px;outline:none;font-family:inherit}
.ctrl textarea{min-height:60px;resize:vertical}
.ctrl select{appearance:none;cursor:pointer}
.ctrl button{padding:10px 24px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer;font-size:14px;margin-top:12px;width:100%}
.ctrl button:hover{box-shadow:0 0 20px rgba(246,99,179,.3)}
#preview svg{width:100%;max-width:400px;height:auto;border-radius:16px;border:1px solid rgba(255,255,255,.08)}
.msg{padding:10px;border-radius:12px;font-size:13px;margin-bottom:12px}
.msg-ok{background:rgba(76,175,80,.15);border:1px solid rgba(76,175,80,.3);color:#4CAF50}
</style>
</head>
<body>
<div class="bar">
    <div><h1>JS-генератор иллюстраций</h1></div>
    <div>
        <a href="/admin/cards.php">📋 Назад</a>
        <a href="/admin/dashboard.php">📊 Дашборд</a>
    </div>
</div>
<?php if (!empty($saved)): ?><div class="msg msg-ok">✓ Изображение сохранено к карточке!</div><?php endif; ?>
<div class="panel">
    <div class="panel-left">
        <div class="ctrl">
            <label>Выбрать карточку</label>
            <select id="cardSelect" onchange="loadCard()">
                <option value="">— выберите —</option>
                <?php foreach ($cards as $c): ?>
                <option value="<?=$c['id']?>" data-text="<?=e($c['text'])?>" data-level="<?=e($c['level'])?>" data-performer="<?=e($c['performer'])?>" data-target="<?=e($c['target'])?>">
                    [#<?=$c['id']?>] <?=e(mb_substr($c['text'],0,60))?>
                </option>
                <?php endforeach; ?>
            </select>
            <label>Или введите текст вручную</label>
            <textarea id="promptText" placeholder="Текст задания..."></textarea>
            <label>Уровень</label>
            <select id="levelSelect">
                <option value="green">🟢 Зелёный</option>
                <option value="orange">🟠 Оранжевый</option>
                <option value="purple">🟣 Фиолетовый</option>
                <option value="red">🔴 Красный</option>
            </select>
            <label>Исполнитель</label>
            <select id="performerSelect">
                <option value="any">👤 Любой</option>
                <option value="male">♂ Мужчина</option>
                <option value="female">♀ Женщина</option>
            </select>
            <button onclick="generate()">🎨 Сгенерировать</button>
        </div>
    </div>
    <div class="panel-right">
        <h2>Предпросмотр</h2>
        <div id="preview"></div>
        <form method="post" style="margin-top:12px">
            <input type="hidden" name="save_img" value="1">
            <input type="hidden" name="card_id" id="saveCardId">
            <input type="hidden" name="svg_data" id="saveSvg">
            <button type="submit" style="padding:10px 24px;border-radius:60px;border:none;background:linear-gradient(90deg,#F663B3,#00A7D1);color:#fff;font-weight:600;cursor:pointer;font-size:14px">💾 Сохранить</button>
        </form>
    </div>
</div>
<script>
const COL={green:'#4CAF50',orange:'#FF6B35',purple:'#BE5AFF',red:'#FF5252'};
const BG={green:'rgba(76,175,80,.1)',orange:'rgba(255,107,53,.1)',purple:'rgba(190,90,255,.1)',red:'rgba(255,82,82,.1)'};
const LAB={green:'Флирт',orange:'Ласки',purple:'Экстрим',red:'Секс'};
function loadCard(){const s=document.getElementById('cardSelect'),o=s.options[s.selectedIndex];if(!o.value)return;document.getElementById('promptText').value=o.dataset.text;document.getElementById('levelSelect').value=o.dataset.level;document.getElementById('performerSelect').value=o.dataset.performer;document.getElementById('saveCardId').value=o.value;}
function generate(){const t=document.getElementById('promptText').value.trim();if(!t){alert('Введите текст');return;}
const l=document.getElementById('levelSelect').value,c=COL[l],b=BG[l],la=LAB[l],a=c.replace('#','').match(/../g).map(x=>parseInt(x,16));
const h=t.toLowerCase();let s='touch';
if(/поцелуй|целуй|губы|язык/.test(h))s='kiss';else if(/минет|орал|соси/.test(h))s='oral';else if(/секс|поза|войди|член|трах|кончи|проник/.test(h))s='sex';else if(/грудь|лифчик|сосок|груди/.test(h))s='breast';else if(/сними|раздевай|одежд/.test(h))s='undress';else if(/шея|шею|ключиц/.test(h))s='neck';
const r=`${c}${Math.round(Math.random()*33+22)}`;const svg=`<svg viewBox="0 0 400 560" xmlns="http://www.w3.org/2000/svg"><rect width="400" height="560" fill="${b}" rx="16"/><rect width="400" height="4" fill="${c}" opacity=".3"/><rect x="16" y="16" width="${la.length*9+24}" height="24" rx="12" fill="${c}" opacity=".15"/><text x="26" y="32" fill="${c}" font-size="11" font-weight="700" font-family="sans-serif">${la}</text><defs><linearGradient id="f"><stop offset="0%" stop-color="${b}" stop-opacity="0"/><stop offset="100%" stop-color="${b}" stop-opacity=".5"/></linearGradient></defs>
${s==='kiss'?`<ellipse cx="165" cy="250" rx="45" ry="55" fill="${c}" opacity=".15"/><ellipse cx="235" cy="250" rx="45" ry="55" fill="${c}" opacity=".15"/><ellipse cx="200" cy="275" rx="35" ry="18" fill="${c}" opacity=".25"/><ellipse cx="200" cy="278" rx="25" ry="10" fill="${c}" opacity=".15"/><circle cx="185" cy="235" r="5" fill="${c}" opacity=".3"/><circle cx="215" cy="235" r="5" fill="${c}" opacity=".3"/><text x="200" y="220" text-anchor="middle" fill="${c}" font-size="28" opacity=".2">❤</text>`:
s==='oral'?`<ellipse cx="200" cy="240" rx="20" ry="25" fill="${c}" opacity=".15"/><ellipse cx="200" cy="310" rx="40" ry="20" fill="${c}" opacity=".2"/><ellipse cx="200" cy="305" rx="25" ry="10" fill="${c}" opacity=".15"/><line x1="190" y1="270" x2="190" y2="295" stroke="${c}" opacity=".3" stroke-width="2"/><line x1="210" y1="270" x2="210" y2="295" stroke="${c}" opacity=".3" stroke-width="2"/>`:
s==='sex'?`<ellipse cx="175" cy="260" rx="35" ry="50" fill="${c}" opacity=".15" transform="rotate(-10,175,260)"/><ellipse cx="225" cy="260" rx="35" ry="50" fill="${c}" opacity=".15" transform="rotate(10,225,260)"/><ellipse cx="200" cy="275" rx="40" ry="15" fill="${c}" opacity=".2"/><ellipse cx="200" cy="240" rx="15" ry="10" fill="${c}" opacity=".25"/><line x1="200" y1="230" x2="200" y2="245" stroke="${c}" opacity=".4" stroke-width="3"/>`:
s==='breast'?`<ellipse cx="180" cy="250" rx="20" ry="25" fill="${c}" opacity=".15"/><ellipse cx="220" cy="250" rx="20" ry="25" fill="${c}" opacity=".15"/><circle cx="182" cy="248" r="7" fill="${c}" opacity=".25"/><circle cx="218" cy="248" r="7" fill="${c}" opacity=".25"/><path d="M170,275 Q200,310 230,275" fill="none" stroke="${c}" opacity=".2" stroke-width="2"/>`:
s==='undress'?`<ellipse cx="180" cy="260" rx="30" ry="40" fill="${c}" opacity=".12"/><ellipse cx="220" cy="250" rx="30" ry="40" fill="${c}" opacity=".12"/><rect x="155" y="225" width="25" height="30" rx="4" fill="none" stroke="${c}" opacity=".3" stroke-width="1.5"/><rect x="215" y="215" width="30" height="40" rx="4" fill="none" stroke="${c}" opacity=".3" stroke-width="1.5"/><line x1="168" y1="235" x2="168" y2="250" stroke="${c}" opacity=".25" stroke-width="1.5"/>`:
s==='neck'?`<ellipse cx="185" cy="255" rx="25" ry="35" fill="${c}" opacity=".15"/><ellipse cx="215" cy="245" rx="20" ry="30" fill="${c}" opacity=".12"/><path d="M210,230 Q220,210 230,235" fill="none" stroke="${c}" opacity=".35" stroke-width="2.5"/><circle cx="218" cy="218" r="5" fill="${c}" opacity=".2"/>`:
`<path d="M160,250 Q180,235 200,250 Q220,265 240,250" fill="none" stroke="${c}" opacity=".3" stroke-width="3" stroke-linecap="round"/><path d="M150,275 Q175,260 200,275 Q225,290 250,275" fill="none" stroke="${c}" opacity=".2" stroke-width="2.5" stroke-linecap="round"/><circle cx="180" cy="245" r="4" fill="${c}" opacity=".4"/><circle cx="200" cy="240" r="4" fill="${c}" opacity=".4"/><circle cx="220" cy="245" r="4" fill="${c}" opacity=".3"/>`}
<foreignObject x="20" y="360" width="360" height="120"><div xmlns="http://www.w3.org/1999/xhtml" style="color:#fff;font-size:16px;text-align:center;font-weight:600;line-height:1.4;padding:8px;font-family:Exo 2,sans-serif">${t}</div></foreignObject>
<rect y="480" width="400" height="80" fill="url(#f)"/></svg>`;
document.getElementById('preview').innerHTML=svg;document.getElementById('saveSvg').value=svg;}
</script>
</body>
</html>