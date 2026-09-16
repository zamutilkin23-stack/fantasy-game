<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

$code = $_GET['code'] ?? '';
if (!$code) redirect('/start');

$pdo = getDB();

$action = $_GET['action'] ?? '';
if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $cardId = (int)($_GET['card_id'] ?? 0);
    $turn = (int)($_GET['turn'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = ?");
    $stmt->execute([$code]);
    $session = $stmt->fetch();
    if (!$session) { echo json_safe(['ok'=>false,'error'=>'Session not found']); exit; }
    $completedIds = json_decode($session['completed_card_ids'], true) ?? [];
    $score = json_decode($session['score'], true) ?? [];
    $players = json_decode($session['players'], true);
    $totalPlayers = count($players);
    $stageLevel = $session['stage_level'] ?? 'green';
    $stageCount = (int)($session['stage_count'] ?? 0);
    $mode = $session['mode'];
    $isGroup = in_array($mode, ['wmw','mwm','mwmw']);
    $stageLimits = $isGroup ? ['green'=>12,'orange'=>10,'purple'=>8,'red'=>6] : ['green'=>25,'orange'=>20,'purple'=>15,'red'=>10];
    $nextStage = ['green'=>'orange','orange'=>'purple','purple'=>'red','red'=>null];
    if ($action === 'complete') {
        if (!in_array($cardId, $completedIds)) $completedIds[] = $cardId;
        $score[(string)$turn] = ($score[(string)$turn] ?? 0) + 1;
        $stageCount++;
        $nextTurn = ($turn + 1) % $totalPlayers;
    } elseif ($action === 'skip') {
        if (!in_array($cardId, $completedIds)) $completedIds[] = $cardId;
        $score[(string)$turn] = ($score[(string)$turn] ?? 0) - 1;
        $stageCount++;
        $nextTurn = ($turn + 1) % $totalPlayers;
    } else { echo json_safe(['ok'=>false]); exit; }
    if ($stageCount >= $stageLimits[$stageLevel] && $nextStage[$stageLevel]) {
        $stageLevel = $nextStage[$stageLevel]; $stageCount = 0;
        $stageJustAdvanced = true;
    } else {
        $stageJustAdvanced = false;
    }
    $stmt = $pdo->prepare("UPDATE sessions SET completed_card_ids=?, score=?, current_turn=?, stage_level=?, stage_count=?, updated_at=datetime('now') WHERE code=?");
    $stmt->execute([json_safe($completedIds), json_safe($score), $nextTurn, $stageLevel, $stageCount, $code]);
    echo json_safe(['ok'=>true,'score'=>$score,'nextTurn'=>$nextTurn,'stageLevel'=>$stageLevel,'stageCount'=>$stageCount,'stageJustAdvanced'=>$stageJustAdvanced]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sessions WHERE code = ?");
$stmt->execute([$code]);
$session = $stmt->fetch();
if (!$session) redirect('/start');

$players = json_decode($session['players'], true);
$settings = json_decode($session['settings'], true) ?? [];
$completedIds = json_decode($session['completed_card_ids'], true) ?? [];
$currentTurn = (int)$session['current_turn'];
$score = json_decode($session['score'], true) ?? [];
$mode = $session['mode'];
$stageLevel = $session['stage_level'] ?? 'green';
$stageCount = (int)($session['stage_count'] ?? 0);
$selectedCategories = $settings['categories'] ?? [];

$params = [];
$sql = "SELECT * FROM cards WHERE status='published' AND level=?";
$params[] = $stageLevel;
if (!empty($selectedCategories)) {
    $cp = implode(',', array_fill(0, count($selectedCategories), '?'));
    $sql .= " AND category_id IN ($cp)";
    $params = array_merge($params, $selectedCategories);
}
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$allCards = $stmt->fetchAll();

$currentPlayer = $players[$currentTurn] ?? $players[0];

// Filter cards to count how many fit this player/mode combo
$filteredCards = array_filter($allCards, function($card) use ($completedIds, $currentPlayer, $players, $mode) {
    if (in_array($card['id'], $completedIds)) return false;
    $cm = json_decode($card['modes'], true) ?? [];
    if (empty($cm) || !in_array($mode, $cm)) return false;
    $p = $card['performer']; $t = $card['target']; $po = $card['performer_orientation']??'any';
    if ($p!=='any' && $p!=='all' && $p!==$currentPlayer['gender']) return false;
    $po2 = $currentPlayer['orientation']??'hetero';
    if ($po!=='any' && $po2!=='any' && $po2!==$po) return false;
    if ($t==='partner_m') { $h=false; foreach($players as $pl) { if($pl['gender']==='male' && $pl['name']!==$currentPlayer['name']) $h=true; } if(!$h) return false; }
    if ($t==='partner_f') { $h=false; foreach($players as $pl) { if($pl['gender']==='female' && $pl['name']!==$currentPlayer['name']) $h=true; } if(!$h) return false; }
    if ($po2==='hetero') {
        if ($t==='partner_m' && $currentPlayer['gender']==='male') return false;
        if ($t==='partner_f' && $currentPlayer['gender']==='female') return false;
    }
    return true;
});
$filteredCount = count($filteredCards);
$availableCards = array_values($filteredCards);

$isGroup = in_array($mode, ['wmw','mwm','mwmw']);
$stageLimits = $isGroup ? ['green'=>12,'orange'=>10,'purple'=>8,'red'=>6] : ['green'=>25,'orange'=>20,'purple'=>15,'red'=>10];
$nextStage = ['green'=>'orange','orange'=>'purple','purple'=>'red','red'=>null];
$stageComplete = $stageCount >= $stageLimits[$stageLevel] && $nextStage[$stageLevel] !== null;
$gameOver = $stageCount >= $stageLimits[$stageLevel] && $nextStage[$stageLevel] === null;

$stageLabel = ['green'=>'🟢 Флирт','orange'=>'🟠 Ласки','purple'=>'🟣 Экстрим','red'=>'🔴 Секс'];

$currentCard = null;
if (!empty($availableCards) && !$stageComplete && !$gameOver) {
    $currentCard = $availableCards[array_rand($availableCards)];
}

$stmt = $pdo->prepare("UPDATE sessions SET updated_at=datetime('now') WHERE code=?");
$stmt->execute([$code]);

$performerName = $currentPlayer['name'];
$targetName = '';
if ($currentCard) {
    $t = $currentCard['target'];
    foreach ($players as $p) {
        if ($p['name']===$performerName) continue;
        if ($t==='partner_m' && $p['gender']==='male') { $targetName=$p['name']; break; }
        if ($t==='partner_f' && $p['gender']==='female') { $targetName=$p['name']; break; }
        if ($t==='all') { $targetName='всем'; break; }
        if ($t==='self') { $targetName='себе'; break; }
        if ($t==='each_other') { $targetName='друг другу'; break; }
    }
    if (!$targetName && $t==='partner_f') $targetName='партнёрше';
    if (!$targetName && $t==='partner_m') $targetName='партнёру';
}
$isPunishment = ($score[(string)$currentTurn]??0) < 0 && isset($_GET['punish']);

$allCategories = [];
$catStmt = $pdo->query("SELECT id, name FROM categories");
while ($catRow = $catStmt->fetch()) $allCategories[$catRow['id']] = $catRow['name'];
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Тайная комната — игра</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0a0a0a">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="/css/game.css">
<style>
.game{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px;text-align:center;position:relative;overflow:hidden}
.game__players{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-bottom:32px}
.game__player{padding:8px 16px;border-radius:60px;background:var(--glass);border:1px solid var(--glass-border);font-size:13px;transition:.3s;opacity:.4}
.game__player--active{opacity:1;border-color:var(--pink);box-shadow:0 0 20px rgba(246,99,179,.2);animation:pulse 2s infinite}
.game__player .orient{font-size:10px;color:rgba(255,255,255,.4);display:block;margin-top:2px}
.card-wrap{perspective:800px;width:100%;max-width:400px;margin-bottom:24px}
.card{position:relative;width:100%;padding-bottom:140%;border-radius:var(--radius);cursor:pointer;transform-style:preserve-3d;transition:transform .6s}
.card--flipped{transform:rotateY(180deg)}
.card__front,.card__back{position:absolute;inset:0;backface-visibility:hidden;border-radius:var(--radius);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
.card__back{background:linear-gradient(135deg,var(--pink),var(--blue));z-index:2}
.card__back::after{content:'?';font-size:64px;font-weight:700;color:rgba(255,255,255,.3)}
.card__front{transform:rotateY(180deg);background:var(--glass);backdrop-filter:blur(30px);border:1px solid var(--glass-border);overflow:hidden}
.card__front--green{background:rgba(76,175,80,.1);border-color:rgba(76,175,80,.3)}
.card__front--orange{background:rgba(255,107,53,.1);border-color:rgba(255,107,53,.3)}
.card__front--purple{background:rgba(190,90,255,.1);border-color:rgba(190,90,255,.3)}
.card__front--red{background:rgba(255,82,82,.1);border-color:rgba(255,82,82,.3)}
.card__front--punishment{background:rgba(255,82,82,.08);border-color:rgba(255,82,82,.3)}
.card__img{width:100%;max-height:40%;object-fit:cover;border-radius:var(--radius-sm);margin-bottom:12px}
.card__level{position:absolute;top:12px;right:12px;padding:4px 12px;border-radius:60px;font-size:10px;font-weight:600;z-index:2}
.card__level--green{background:rgba(76,175,80,.2);color:var(--green)}
.card__level--orange{background:rgba(255,107,53,.2);color:var(--orange)}
.card__level--purple{background:rgba(190,90,255,.2);color:var(--lilac)}
.card__level--red{background:rgba(255,82,82,.2);color:var(--red)}
.card__text{font-size:clamp(16px,3vw,22px);font-weight:600;line-height:1.4;padding:0 8px;position:relative;z-index:2}
.card__who{display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:13px;position:relative;z-index:2;flex-wrap:wrap;justify-content:center}
.card__performer{background:rgba(246,99,179,.2);border:1px solid rgba(246,99,179,.3);padding:3px 12px;border-radius:60px;font-weight:600;color:var(--pink)}
.card__arrow{color:rgba(255,255,255,.3);font-size:16px}
.card__target-name{background:rgba(0,167,209,.2);border:1px solid rgba(0,167,209,.3);padding:3px 12px;border-radius:60px;font-weight:600;color:var(--blue)}
.card__target{font-size:12px;color:rgba(255,255,255,.4);margin-top:8px;position:relative;z-index:2}
.card__hint{position:absolute;bottom:12px;font-size:10px;color:rgba(255,255,255,.2);z-index:2}
.card__punishment{position:absolute;top:12px;left:12px;padding:3px 10px;border-radius:60px;font-size:10px;font-weight:600;background:rgba(255,82,82,.25);border:1px solid rgba(255,82,82,.4);color:var(--red);z-index:2}
.card__animation{position:absolute;inset:0;pointer-events:none;z-index:0;overflow:hidden;border-radius:var(--radius)}
.card__animation svg{width:100%;height:100%;opacity:.15}
.timer-ring{position:absolute;top:-8px;right:-8px;width:80px;height:80px;z-index:3}
.timer-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.timer-ring circle{fill:none;stroke-width:4;stroke-linecap:round;transition:stroke-dashoffset 1s linear}
.timer-ring .bg{stroke:rgba(255,255,255,.1)}
.timer-ring .progress{stroke:var(--pink);stroke-dasharray:157.08}
.timer-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:var(--pink);text-shadow:0 0 10px rgba(246,99,179,.3)}
.game__stage{display:flex;gap:12px;margin-bottom:16px;font-size:13px}
.stage-badge{padding:4px 14px;border-radius:60px;font-weight:600}
.stage-badge--green{background:rgba(76,175,80,.2);color:var(--green);border:1px solid rgba(76,175,80,.3)}
.stage-badge--orange{background:rgba(255,107,53,.2);color:var(--orange);border:1px solid rgba(255,107,53,.3)}
.stage-badge--purple{background:rgba(190,90,255,.2);color:var(--lilac);border:1px solid rgba(190,90,255,.3)}
.stage-badge--red{background:rgba(255,82,82,.2);color:var(--red);border:1px solid rgba(255,82,82,.3)}
.stage-progress{color:rgba(255,255,255,.3)}
.game__actions{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:16px}
.game__actions .btn{min-width:160px}
.btn--green{background:rgba(76,175,80,.2);border:1px solid rgba(76,175,80,.3);color:var(--green)}
.btn--green:hover{background:rgba(76,175,80,.3)}
.btn--skip{background:rgba(255,82,82,.15);border:1px solid rgba(255,82,82,.3);color:var(--red)}
.btn--skip:hover{background:rgba(255,82,82,.25)}
.game__score{display:flex;gap:16px;margin-bottom:24px;font-size:13px}
.score-item{background:var(--glass);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:8px 16px}
@keyframes cardEnter{from{opacity:0;transform:translateY(60px) scale(.8)}to{opacity:1;transform:translateY(0) scale(1)}}
.card-wrap{animation:cardEnter .5s cubic-bezier(.34,1.56,.64,1)}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}
.card--burn{animation:cardBurn .6s forwards}
@keyframes cardBurn{to{opacity:0;transform:scale(.5) rotate(10deg) translateY(40px)}}
.particles{position:fixed;inset:0;pointer-events:none;z-index:999}
.particle{position:absolute;width:6px;height:6px;border-radius:50%;animation:particleFly 1s forwards}
@keyframes particleFly{0%{opacity:1}100%{opacity:0;transform:translate(var(--dx),var(--dy))}}
.result{position:fixed;inset:0;z-index:998;background:rgba(10,10,10,.95);backdrop-filter:blur(20px);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;animation:fadeIn .4s}
.result h2{font-size:32px;margin-bottom:16px}
.result p{color:rgba(255,255,255,.5);margin-bottom:24px;line-height:1.6}
.result .btn{margin-top:8px}
</style>
</head>
<body>
<div class="layout">
<div class="game no-select touch-manipulation" id="game">

    <div class="game__players">
        <?php foreach ($players as $i => $p): ?>
        <div class="game__player <?=$i === $currentTurn ? 'game__player--active' : ''?>">
            <?=e($p['name'])?>
            <span class="orient"><?=$p['orientation'] === 'bi' ? 'би' : 'гетеро'?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="game__stage">
        <span class="stage-badge stage-badge--<?=$stageLevel?>"><?=$stageLabel[$stageLevel]?></span>
        <span class="stage-progress"><?=$stageCount?>/<?=$stageLimits[$stageLevel]?></span>
    </div>

    <div class="game__score">
        <?php foreach ($players as $i => $p): $pts = $score[$i] ?? 0; ?>
        <div class="score-item"><?=e($p['name'])?>: <strong><?=$pts?></strong></div>
        <?php endforeach; ?>
    </div>

    <?php if ($stageComplete): ?>
    <div class="result" style="display:flex">
        <h2>🎉 Этап пройден!</h2>
        <p>Отличная работа! Переходим к следующему уровню.</p>
        <a href="/game?code=<?=e($code)?>" class="btn btn--glow">Продолжить</a>
    </div>
    <?php elseif ($gameOver): ?>
    <div class="result" style="display:flex">
        <h2>🏆 Игра завершена!</h2>
        <p>Вы прошли все три этапа. Отличная игра!</p>
        <a href="/start" class="btn btn--glow">Новая игра</a>
    </div>
    <?php elseif ($currentCard): ?>
    <div class="card-wrap" id="cardWrap">
        <div class="card" id="card" onclick="flipCard()">
            <div class="card__back"></div>
            <div class="card__front card__front--<?=$isPunishment?'punishment':$currentCard['level']?>">
                <?php if ($isPunishment): ?><div class="card__punishment">⚠ НАКАЗАНИЕ</div><?php endif; ?>
                <?php if ($currentCard['image_url']): ?>
                <img src="<?=e($currentCard['image_url'])?>" alt="" class="card__img" loading="lazy">
                <?php endif; ?>
                <div class="card__level card__level--<?=$currentCard['level']?>">
                    <?=$stageLabel[$currentCard['level']]?>
                </div>
                <div class="card__who">
                    <?php if ($currentCard['performer'] === 'all'): ?>
                        <span class="card__performer">👥 Все делают</span>
                    <?php else: ?>
                        <span class="card__performer">🎯 <?=e($performerName)?></span>
                    <?php endif; ?>
                    <?php if ($targetName): ?>
                        <span class="card__arrow">→</span>
                        <span class="card__target-name"><?=e($targetName)?></span>
                    <?php endif; ?>
                </div>
                <div class="card__text"><?=e($currentCard['text'])?></div>
                <div class="card__target">
                    <?php
                    $tl = ['partner_m'=>'→ партнёру','partner_f'=>'→ партнёрше','self'=>'→ себе','each_other'=>'→ друг другу','all'=>'→ всем'];
                    echo $tl[$currentCard['target']] ?? '';
                    if ($currentCard['category_id'] && isset($allCategories[$currentCard['category_id']])) {
                        echo ' · ' . e($allCategories[$currentCard['category_id']]);
                    }
                    ?>
                </div>
                <div class="card__animation">
                    <?php $catSlug = ''; echo ''; ?>
                </div>
                <div class="card__hint">нажмите, чтобы перевернуть</div>
            </div>
        </div>
        <div class="timer-ring" id="timerRing" style="display:none">
            <svg viewBox="0 0 60 60">
                <circle class="bg" cx="30" cy="30" r="25"/>
                <circle class="progress" cx="30" cy="30" r="25" id="timerProgress"/>
            </svg>
            <div class="timer-text" id="timerText">0:00</div>
        </div>
    </div>

    <div class="game__actions" id="actions">
        <button class="btn btn--green" onclick="doComplete()">✓ Выполнено</button>
        <button class="btn btn--skip" onclick="doSkip()">✗ Пропустить</button>
        <button class="btn btn--outline" onclick="flipCard()">🔄 Перевернуть</button>
        <button class="btn btn--outline" onclick="doFinish()" style="border-color:rgba(255,82,82,.3);color:var(--red)">⏹ Завершить игру</button>
    </div>
    <?php else: ?>
    <div class="result" style="display:flex">
        <h2>😕 Нет подходящих карточек</h2>
        <p>Попробуйте изменить настройки или начать новую игру.</p>
        <a href="/start" class="btn btn--glow">Новая игра</a>
    </div>
    <?php endif; ?>

    <div class="particles" id="particles"></div>
</div>

<div class="result" id="result" style="display:none">
    <h2 id="resultTitle">Игра завершена</h2>
    <p id="resultText">Отличная игра!</p>
    <div id="resultScores"></div>
    <a href="/start" class="btn btn--glow">Новая игра</a>
    <a href="/game?code=<?=e($code)?>" class="btn btn--outline" style="margin-top:8px">Продолжить</a>
</div>
</div>

<script>
const CODE = '<?=e($code)?>';
const CARD_ID = <?=$currentCard ? $currentCard['id'] : 'null'?>;
const CURRENT_TURN = <?=$currentTurn?>;

let flipped = false;
let timerInterval = null;
let timerSeconds = <?=$currentCard ? ['green'=>90,'orange'=>180,'purple'=>240,'red'=>300][$currentCard['level']] : 0?>;

function flipCard() {
    if (flipped) return;
    document.getElementById('card').classList.add('card--flipped');
    flipped = true;
    document.getElementById('timerRing').style.display = 'block';
    startTimer();
}
function startTimer() {
    const circle = document.getElementById('timerProgress');
    const circum = 2 * Math.PI * 25;
    circle.style.strokeDasharray = circum;
    let rem = timerSeconds;
    updateDisplay(circle, circum, rem, timerSeconds);
    timerInterval = setInterval(() => {
        rem--;
        if (rem <= 0) { clearInterval(timerInterval); document.getElementById('timerRing').style.display = 'none'; document.getElementById('timerText').textContent = '0:00'; }
        updateDisplay(circle, circum, rem, timerSeconds);
        const m = Math.floor(rem/60), s = rem%60;
        document.getElementById('timerText').textContent = m + ':' + (s<10?'0':'') + s;
    }, 1000);
}
function updateDisplay(circle, c, rem, max) {
    circle.style.strokeDashoffset = c - (rem/max)*c;
    const m = Math.floor(rem/60), s = rem%60;
    document.getElementById('timerText').textContent = m + ':' + (s<10?'0':'') + s;
}
function doComplete() {
    clearInterval(timerInterval);
    if (CARD_ID !== null) {
        fetch('/game.php?action=complete&code='+CODE+'&card_id='+CARD_ID+'&turn='+CURRENT_TURN, {method:'POST'})
            .then(r=>r.json()).then(d=>{
                if(d.ok) {
                    if(d.stageJustAdvanced) showParticles();
                    setTimeout(()=>location.reload(), d.stageJustAdvanced ? 1500 : 800);
                }
            });
    }
}
function doSkip() {
    clearInterval(timerInterval);
    document.getElementById('card').classList.add('card--burn');
    if (CARD_ID !== null) {
        fetch('/game.php?action=skip&code='+CODE+'&card_id='+CARD_ID+'&turn='+CURRENT_TURN, {method:'POST'})
            .then(r=>r.json()).then(d=>{
                if (d.score && d.score[CURRENT_TURN] < 0) setTimeout(()=>{location.href='/game?code='+CODE+'&punish=1';},600);
                else setTimeout(()=>location.reload(),600);
            });
    }
}
function doFinish() { if(confirm('Завершить игру?')) window.location.href='/start'; }
function showParticles() {
    const c=document.getElementById('particles'), cols=['#F663B3','#00A7D1','#BE5AFF','#FF6B35','#4CAF50'];
    for(let i=0;i<30;i++){const p=document.createElement('div');p.className='particle';const sz=4+Math.random()*6;p.style.width=sz+'px';p.style.height=sz+'px';p.style.background=cols[Math.floor(Math.random()*cols.length)];p.style.left=(30+Math.random()*40)+'%';p.style.top=(30+Math.random()*40)+'%';p.style.setProperty('--dx',(Math.random()-.5)*200+'px');p.style.setProperty('--dy',-(Math.random()*200+100)+'px');p.style.animationDuration=(.6+Math.random()*.8)+'s';c.appendChild(p);setTimeout(()=>p.remove(),1500);}
}
</script>
</body>
</html>