<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

$code = $_GET['code'] ?? '';
if (!$code) redirect('/start');

$pdo = getDB();

// Handle AJAX actions
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

    if ($action === 'complete') {
        if (!in_array($cardId, $completedIds)) $completedIds[] = $cardId;
        $score[(string)$turn] = ($score[(string)$turn] ?? 0) + 1;
        $nextTurn = ($turn + 1) % $totalPlayers;
    } elseif ($action === 'skip') {
        if (!in_array($cardId, $completedIds)) $completedIds[] = $cardId;
        $score[(string)$turn] = ($score[(string)$turn] ?? 0) - 1;
        $nextTurn = ($turn + 1) % $totalPlayers;
    } else {
        echo json_safe(['ok'=>false]); exit;
    }

    $stmt = $pdo->prepare("UPDATE sessions SET completed_card_ids = ?, score = ?, current_turn = ?, updated_at = datetime('now') WHERE code = ?");
    $stmt->execute([json_safe($completedIds), json_safe($score), $nextTurn, $code]);

    echo json_safe(['ok'=>true, 'score'=>$score, 'nextTurn'=>$nextTurn]);
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

// Build filter
$selectedCategories = $settings['categories'] ?? [];
$selectedLevels = $settings['levels'] ?? ['green','orange','red'];
$duration = (int)($settings['duration'] ?? 0);

// Fetch published cards — уровни фильтруем в SQL, режим и остальное — в PHP
$params = [];
$sql = "SELECT * FROM cards WHERE status='published'";
if (!empty($selectedLevels)) {
    $levelPlaceholders = implode(',', array_fill(0, count($selectedLevels), '?'));
    $sql .= " AND level IN ($levelPlaceholders)";
    $params = $selectedLevels;
}
if (!empty($selectedCategories)) {
    $catPlaceholders = implode(',', array_fill(0, count($selectedCategories), '?'));
    $sql .= " AND category_id IN ($catPlaceholders)";
    $params = array_merge($params, $selectedCategories);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allCards = $stmt->fetchAll();

// Filter in PHP — строгие проверки: режим, исполнитель, ориентация
$currentPlayer = $players[$currentTurn] ?? $players[0];
$availableCards = array_filter($allCards, function($card) use ($completedIds, $currentPlayer, $players, $mode) {
    if (in_array($card['id'], $completedIds)) return false;

    // 1. MODES — карточка без режимов не показывается нигде
    $cardModes = json_decode($card['modes'], true) ?? [];
    if (empty($cardModes) || !in_array($mode, $cardModes)) return false;

    $performer = $card['performer'];
    $target = $card['target'];
    $perfOrient = $card['performer_orientation'] ?? 'any';
    $targetOrient = $card['target_orientation'] ?? 'any';

    // 2. Performer check
    if ($performer !== 'any' && $performer !== 'all') {
        if ($performer !== $currentPlayer['gender']) return false;
    }

    // 3. Performer orientation check
    $playerOrient = $currentPlayer['orientation'] ?? 'hetero';
    if ($perfOrient !== 'any' && $playerOrient !== $perfOrient) return false;

    // 4. Target availability
    if ($target === 'partner_m') {
        $has = false;
        foreach ($players as $p) {
            if ($p['gender'] === 'male' && $p['name'] !== $currentPlayer['name']) $has = true;
        }
        if (!$has) return false;
    }
    if ($target === 'partner_f') {
        $has = false;
        foreach ($players as $p) {
            if ($p['gender'] === 'female' && $p['name'] !== $currentPlayer['name']) $has = true;
        }
        if (!$has) return false;
    }

    // 5. Player orientation vs target
    if ($playerOrient === 'hetero') {
        if ($target === 'partner_m' && $currentPlayer['gender'] === 'male') return false;
        if ($target === 'partner_f' && $currentPlayer['gender'] === 'female') return false;
        if ($target === 'all') {
            $hasOpposite = false;
            foreach ($players as $p) {
                if ($p['gender'] !== $currentPlayer['gender']) $hasOpposite = true;
            }
            if (!$hasOpposite) return false;
        }
    }

    return true;
});

$availableCards = array_values($availableCards); // reindex

// Check if this turn is a punishment (score below 0 after skip)
$isPunishment = false;
$currentScore = $score[(string)$currentTurn] ?? 0;
if ($currentScore < 0 && isset($_GET['punish'])) {
    $isPunishment = true;
}

// Pick current card
$currentCard = null;
if (!empty($availableCards)) {
    $currentCard = $availableCards[array_rand($availableCards)];
}

// Save session
$stmt = $pdo->prepare("UPDATE sessions SET updated_at = datetime('now') WHERE code = ?");
$stmt->execute([$code]);

// Determine performer and target names
$performerName = $currentPlayer['name'];
$targetName = '';
if ($currentCard) {
    $target = $currentCard['target'];
    foreach ($players as $p) {
        if ($p['name'] === $performerName) continue;
        if ($target === 'partner_m' && $p['gender'] === 'male') { $targetName = $p['name']; break; }
        if ($target === 'partner_f' && $p['gender'] === 'female') { $targetName = $p['name']; break; }
        if ($target === 'all') { $targetName = 'всем'; break; }
        if ($target === 'self') { $targetName = 'себе'; break; }
        if ($target === 'each_other') { $targetName = 'друг другу'; break; }
    }
    if (!$targetName && $target === 'partner_f') $targetName = 'партнёрше';
    if (!$targetName && $target === 'partner_m') $targetName = 'партнёру';
}

// Load all categories once (avoid N+1)
$allCategories = [];
$catStmt = $pdo->query("SELECT id, name, slug FROM categories");
while ($catRow = $catStmt->fetch()) {
    $allCategories[$catRow['id']] = ['name' => $catRow['name'], 'slug' => $catRow['slug']];
}
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
/* === Game Screen === */
.game{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px;text-align:center;position:relative;overflow:hidden}
.game__players{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-bottom:32px}
.game__player{padding:8px 16px;border-radius:60px;background:var(--glass);border:1px solid var(--glass-border);font-size:13px;transition:.3s;opacity:.4}
.game__player--active{opacity:1;border-color:var(--pink);box-shadow:0 0 20px rgba(246,99,179,.2);animation:pulse 2s infinite}
.game__player .orient{font-size:10px;color:rgba(255,255,255,.4);display:block;margin-top:2px}

/* Card */
.card-wrap{perspective:800px;width:100%;max-width:400px;margin-bottom:24px}
.card{position:relative;width:100%;padding-bottom:140%;border-radius:var(--radius);cursor:pointer;transform-style:preserve-3d;transition:transform .6s cubic-bezier(.34,1.56,.64,1)}
.card--flipped{transform:rotateY(180deg)}
.card__front,.card__back{position:absolute;inset:0;backface-visibility:hidden;border-radius:var(--radius);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
.card__back{background:linear-gradient(135deg,var(--pink),var(--blue));z-index:2}
.card__back::after{content:'?';font-size:64px;font-weight:700;color:rgba(255,255,255,.3)}
.card__front{transform:rotateY(180deg);background:var(--glass);backdrop-filter:blur(30px);border:1px solid var(--glass-border);overflow:hidden}
.card__front--green{background:rgba(76,175,80,.1);border-color:rgba(76,175,80,.3)}
.card__front--orange{background:rgba(255,107,53,.1);border-color:rgba(255,107,53,.3)}
.card__front--red{background:rgba(255,82,82,.1);border-color:rgba(255,82,82,.3)}
.card__img{width:100%;max-height:40%;object-fit:cover;border-radius:var(--radius-sm);margin-bottom:12px}
.card__level{position:absolute;top:12px;right:12px;padding:4px 12px;border-radius:60px;font-size:10px;font-weight:600}
.card__level--green{background:rgba(76,175,80,.2);color:var(--green)}
.card__level--orange{background:rgba(255,107,53,.2);color:var(--orange)}
.card__level--red{background:rgba(255,82,82,.2);color:var(--red)}
.card__text{font-size:clamp(16px,3vw,22px);font-weight:600;line-height:1.4;padding:0 8px;position:relative;z-index:2}
.card__who{display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:13px;position:relative;z-index:2;flex-wrap:wrap;justify-content:center}
.card__performer{background:rgba(246,99,179,.2);border:1px solid rgba(246,99,179,.3);padding:3px 12px;border-radius:60px;font-weight:600;color:var(--pink)}
.card__arrow{color:rgba(255,255,255,.3);font-size:16px}
.card__target-name{background:rgba(0,167,209,.2);border:1px solid rgba(0,167,209,.3);padding:3px 12px;border-radius:60px;font-weight:600;color:var(--blue)}
.card__target{font-size:12px;color:rgba(255,255,255,.4);margin-top:8px;position:relative;z-index:2}
.card__hint{position:absolute;bottom:12px;font-size:10px;color:rgba(255,255,255,.2);z-index:2}

/* Punishment */
.card__punishment{position:absolute;top:12px;left:12px;padding:3px 10px;border-radius:60px;font-size:10px;font-weight:600;background:rgba(255,82,82,.25);border:1px solid rgba(255,82,82,.4);color:var(--red);z-index:2}
.card__front--punishment{background:rgba(255,82,82,.08);border-color:rgba(255,82,82,.3)}

/* Skip penalty button state */
.btn--punish{background:rgba(255,82,82,.2);border:1px solid rgba(255,82,82,.3);color:var(--red)}
.btn--punish:hover{background:rgba(255,82,82,.35)}

/* SVG Animations on card background */
.card__animation{position:absolute;inset:0;pointer-events:none;z-index:0;overflow:hidden;border-radius:var(--radius)}
.card__animation svg{width:100%;height:100%;opacity:.15}
@keyframes pulseHeart{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
@keyframes pulseRing{0%,100%{opacity:.1;r:25}50%{opacity:.2;r:28}}
@keyframes mouthMove{0%,100%{d:path('M85,180 Q100,160 115,180')}50%{d:path('M85,180 Q100,195 115,180')}}
@keyframes rotateToy{0%{transform:rotate(-5deg)}100%{transform:rotate(5deg)}}
@keyframes floatUp{0%{transform:translateY(0);opacity:1}100%{transform:translateY(-30px);opacity:0}}
@keyframes crownPulse{0%,100%{opacity:.2;transform:translateY(0)}50%{opacity:.35;transform:translateY(-3px)}}
@keyframes glowLine{0%,100%{opacity:.1}50%{opacity:.25}}
@keyframes sparkle{0%,100%{opacity:0;transform:scale(0)}50%{opacity:1;transform:scale(1)}}

/* Timer ring */
.timer-ring{position:absolute;top:-8px;right:-8px;width:60px;height:60px;z-index:3}
.timer-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.timer-ring circle{fill:none;stroke-width:4;stroke-linecap:round;transition:stroke-dashoffset 1s linear}
.timer-ring .bg{stroke:rgba(255,255,255,.1)}
.timer-ring .progress{stroke:var(--pink);stroke-dasharray:157.08}

/* Actions */
.game__actions{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:16px}
.game__actions .btn{min-width:160px}
.btn--green{background:rgba(76,175,80,.2);border:1px solid rgba(76,175,80,.3);color:var(--green)}
.btn--green:hover{background:rgba(76,175,80,.3)}
.btn--skip{background:rgba(255,82,82,.15);border:1px solid rgba(255,82,82,.3);color:var(--red)}
.btn--skip:hover{background:rgba(255,82,82,.25)}

/* Score */
.game__score{display:flex;gap:16px;margin-bottom:24px;font-size:13px}
.score-item{background:var(--glass);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:8px 16px}

/* Enter animation */
@keyframes cardEnter{from{opacity:0;transform:translateY(60px) scale(.8)}to{opacity:1;transform:translateY(0) scale(1)}}
.card-wrap{animation:cardEnter .5s cubic-bezier(.34,1.56,.64,1)}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}

/* Burn animation */
.card--burn{animation:cardBurn .6s forwards}
@keyframes cardBurn{to{opacity:0;transform:scale(.5) rotate(10deg) translateY(40px)}}

/* Particles */
.particles{position:fixed;inset:0;pointer-events:none;z-index:999}
.particle{position:absolute;width:6px;height:6px;border-radius:50%;animation:particleFly 1s forwards}
@keyframes particleFly{0%{opacity:1}100%{opacity:0;transform:translate(var(--dx),var(--dy))}}

/* Result screen */
.result{position:fixed;inset:0;z-index:998;background:rgba(20,4,45,.95);backdrop-filter:blur(20px);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;animation:fadeIn .4s}
.result h2{font-size:32px;margin-bottom:16px}
.result p{color:rgba(255,255,255,.5);margin-bottom:24px;line-height:1.6}
.result .btn{margin-top:8px}
</style>
</head>
<body>
<div class="layout">
<div class="game no-select touch-manipulation" id="game">

    <!-- Players bar -->
    <div class="game__players">
        <?php foreach ($players as $i => $p): ?>
        <div class="game__player <?=$i === $currentTurn ? 'game__player--active' : ''?>">
            <?=e($p['name'])?>
            <span class="orient"><?=$p['orientation'] === 'bi' ? 'би' : 'гетеро'?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Score -->
    <div class="game__score">
        <?php foreach ($players as $i => $p):
            $pts = $score[$i] ?? 0; ?>
        <div class="score-item"><?=e($p['name'])?>: <strong><?=$pts?></strong></div>
        <?php endforeach; ?>
    </div>

    <!-- Card -->
    <?php if ($currentCard): ?>
    <div class="card-wrap" id="cardWrap">
        <div class="card" id="card" onclick="flipCard()">
            <div class="card__back"></div>
            <div class="card__front card__front--<?=$isPunishment?'punishment':e($currentCard['level'])?>">
                <?php if ($isPunishment): ?>
                <div class="card__punishment">⚠ НАКАЗАНИЕ</div>
                <?php endif; ?>
                <?php if ($currentCard['image_url']): ?>
                <img src="<?=e($currentCard['image_url'])?>" alt="" class="card__img" loading="lazy">
                <?php endif; ?>
                <div class="card__level card__level--<?=e($currentCard['level'])?>">
                    <?=['green'=>'🟢 Флирт','orange'=>'🟠 Ласки','red'=>'🔴 Секс'][$currentCard['level']]?>
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
                    $targetLabels = ['partner_m'=>'→ партнёру','partner_f'=>'→ партнёрше','self'=>'→ себе','each_other'=>'→ друг другу','all'=>'→ всем'];
                    echo $targetLabels[$currentCard['target']] ?? '';
                    if ($currentCard['category_id'] && isset($allCategories[$currentCard['category_id']])) {
                        echo ' · ' . e($allCategories[$currentCard['category_id']]['name']);
                    }
                    $catSlug = $currentCard['category_id'] ? ($allCategories[$currentCard['category_id']]['slug'] ?? '') : '';
                    ?>
                </div>
                <div class="card__animation"><?php
                $animSvg = [
                    'flirt' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><circle cx="100" cy="140" r="25" style="animation:pulseRing 2s infinite"/><circle cx="100" cy="140" r="25" style="animation:pulseHeart 1.5s infinite"/><path d="M85,180 Q100,160 115,180" style="animation:mouthMove 2s infinite"/></g></svg>',
                    'classic' => '<svg viewBox="0 0 200 280"><g stroke="rgba(255,255,255,.15)" stroke-width="1.5" fill="none"><path d="M60,120 Q60,90 100,90 Q140,90 140,120" style="animation:glowLine 2s infinite"/><path d="M60,120 Q60,160 100,160 Q140,160 140,120" style="animation:glowLine 2s infinite 1s"/><circle cx="100" cy="110" r="8"/></g></svg>',
                    'oral' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><ellipse cx="100" cy="160" rx="30" ry="15" style="animation:mouthMove 1.5s infinite"/><circle cx="100" cy="110" r="10"/><line x1="100" y1="120" x2="100" y2="145"/></g></svg>',
                    'anal' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><circle cx="100" cy="140" r="18" style="animation:pulseRing 1.8s infinite"/><circle cx="100" cy="140" r="10"/><line x1="100" y1="120" x2="100" y2="100"/><line x1="100" y1="145" x2="100" y2="180"/></g></svg>',
                    'masturbation' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><path d="M80,130 Q90,120 100,130 Q110,140 120,130" style="animation:glowLine .8s infinite"/><path d="M80,140 Q90,130 100,140 Q110,150 120,140" style="animation:glowLine .8s infinite .4s"/><circle cx="100" cy="110" r="8"/></g></svg>',
                    'dom_m' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><path d="M50,140 L150,140" style="animation:glowLine 1.5s infinite"/><path d="M60,120 L100,90 L140,120" style="animation:crownPulse 2s infinite"/><circle cx="100" cy="100" r="15"/></g></svg>',
                    'dom_f' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><path d="M50,140 L150,140" style="animation:glowLine 1.5s infinite"/><path d="M80,120 L100,90 L120,120" style="animation:crownPulse 2s infinite"/><circle cx="100" cy="100" r="15"/></g></svg>',
                    'toys' => '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><ellipse cx="100" cy="130" rx="12" ry="30" style="animation:rotateToy 1s infinite alternate"/><rect x="88" y="140" width="24" height="12" rx="3" style="animation:rotateToy 1s infinite alternate"/></g></svg>',
                ];
                echo $animSvg[$catSlug] ?? '<svg viewBox="0 0 200 280"><g fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5"><circle cx="100" cy="130" r="20" style="animation:pulseRing 2s infinite"/><circle cx="85" cy="115" r="4"/><circle cx="115" cy="115" r="4"/><path d="M90,145 Q100,155 110,145" style="animation:mouthMove 2s infinite"/></g></svg>';
                ?></div>
                <div class="card__hint">нажмите, чтобы перевернуть</div>
            </div>
        </div>
        <!-- Timer ring -->
        <div class="timer-ring" id="timerRing" style="display:none">
            <svg viewBox="0 0 60 60">
                <circle class="bg" cx="30" cy="30" r="25"/>
                <circle class="progress" cx="30" cy="30" r="25" id="timerProgress"/>
            </svg>
        </div>
    </div>

    <!-- Actions -->
    <div class="game__actions" id="actions">
        <button class="btn btn--green" onclick="doComplete()">✓ Выполнено</button>
        <button class="btn btn--skip" onclick="doSkip()">✗ Пропустить</button>
        <button class="btn btn--outline" onclick="flipCard()">🔄 Перевернуть</button>
        <button class="btn btn--outline" onclick="doFinish()" style="border-color:rgba(255,82,82,.3);color:var(--red)">⏹ Завершить игру</button>
    </div>
    <?php else: ?>
    <div class="result">
        <h2>🎉 Задания закончились</h2>
        <p>Все доступные карточки выполнены. Вы можете начать новую игру.</p>
        <a href="/start" class="btn btn--glow">Новая игра</a>
    </div>
    <?php endif; ?>

    <!-- Particles container -->
    <div class="particles" id="particles"></div>
</div>

<!-- Result overlay -->
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
const PLAYERS = <?=json_safe($players)?>;
const CURRENT_TURN = <?=$currentTurn?>;
const TOTAL_CARDS = <?=count($availableCards)?>;

let flipped = false;
let timerInterval = null;
let timerSeconds = <?=$currentCard ? ['green'=>90,'orange'=>180,'red'=>300][$currentCard['level']] : 0?>;

function flipCard() {
    if (flipped) return;
    const card = document.getElementById('card');
    card.classList.add('card--flipped');
    flipped = true;
    document.getElementById('timerRing').style.display = 'block';
    startTimer();
}

function startTimer() {
    const circle = document.getElementById('timerProgress');
    const circumference = 2 * Math.PI * 25;
    circle.style.strokeDasharray = circumference;
    let remaining = timerSeconds;
    updateTimerDisplay(circle, circumference, remaining, timerSeconds);
    timerInterval = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(timerInterval);
            document.getElementById('timerRing').style.display = 'none';
        }
        updateTimerDisplay(circle, circumference, remaining, timerSeconds);
    }, 1000);
}

function updateTimerDisplay(circle, circ, rem, max) {
    const offset = circ - (rem / max) * circ;
    circle.style.strokeDashoffset = offset;
}

function doComplete() {
    clearInterval(timerInterval);
    if (CARD_ID !== null) {
        fetch('/game.php?action=complete&code=' + CODE + '&card_id=' + CARD_ID + '&turn=' + CURRENT_TURN, {method:'POST'})
            .then(r => r.json()).then(d => {
                if (d.ok) showParticles();
                setTimeout(() => location.reload(), 800);
            });
    }
}

function doSkip() {
    clearInterval(timerInterval);
    const card = document.getElementById('card');
    card.classList.add('card--burn');
    if (CARD_ID !== null) {
        fetch('/game.php?action=skip&code=' + CODE + '&card_id=' + CARD_ID + '&turn=' + CURRENT_TURN, {method:'POST'})
            .then(r => r.json()).then(d => {
                if (d.score && d.score[CURRENT_TURN] < 0) {
                    setTimeout(() => { location.href = '/game?code=' + CODE + '&punish=1'; }, 600);
                } else {
                    setTimeout(() => location.reload(), 600);
                }
            });
    }
}

function doFinish() {
    if (confirm('Завершить игру?')) {
        window.location.href = '/start';
    }
}

function showParticles() {
    const container = document.getElementById('particles');
    const colors = ['#F663B3','#00A7D1','#BE5AFF','#FF6B35','#4CAF50'];
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = 4 + Math.random() * 6;
        p.style.width = size + 'px';
        p.style.height = size + 'px';
        p.style.background = colors[Math.floor(Math.random() * colors.length)];
        p.style.left = (30 + Math.random() * 40) + '%';
        p.style.top = (30 + Math.random() * 40) + '%';
        p.style.setProperty('--dx', (Math.random() - .5) * 200 + 'px');
        p.style.setProperty('--dy', -(Math.random() * 200 + 100) + 'px');
        p.style.animationDuration = (.6 + Math.random() * .8) + 's';
        container.appendChild(p);
        setTimeout(() => p.remove(), 1500);
    }
}

// Handle AJAX actions
document.addEventListener('DOMContentLoaded', () => {
    // Auto-flip is handled by the server — no localStorage needed
});
</script>
</body>
</html>