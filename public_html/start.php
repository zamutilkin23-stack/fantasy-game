<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
$pdo = getDB();

$mode = $_GET['mode'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? $mode;
    $players = [];

    $names = $_POST['names'] ?? [];
    $genders = $_POST['genders'] ?? [];

    for ($i = 0; $i < count($names); $i++) {
        $name = trim($names[$i] ?? '');
        if ($name === '') continue;
        $players[] = [
            'name' => $name,
            'gender' => $genders[$i] ?? 'male',
            'orientation' => $_POST['orientation'][$i] ?? 'hetero',
        ];
    }

    if (count($players) >= 2) {
        $code = bin2hex(random_bytes(8));
        $settings_json = json_safe($_POST['game_settings'] ?? []);
        $stmt = $pdo->prepare("INSERT INTO sessions (code, players, mode, settings) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, json_safe($players), $mode, $settings_json]);
        setcookie('last_game_code', $code, time()+86400*30, '/');
        redirect("/game?code=$code");
    }
    $error = 'Заполните имена всех игроков';
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Начать игру — Тайная комната искушений</title>
<meta name="theme-color" content="#0a0a0a">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="/css/game.css">
</head>
<body>
<div class="layout">
<header class="header">
    <div class="container">
        <div class="header__grid">
<a href="/" class="logo">Тайная комната</a>
        </div>
    </div>
</header>

<main class="main">
    <div class="container">
        <!-- Age Gate Block -->
        <div class="agegate-block" id="agegateBlock">
            <div class="agegate-block__card">
                <div class="agegate-block__icon">18+</div>
                <h2>Контент для взрослых</h2>
                <p>Игра содержит откровенные эротические задания и материалы для лиц старше 18 лет.</p>
                <button class="btn btn--glow btn--lg" onclick="acceptAgeStart()">Мне есть 18 лет</button>
                <button class="btn btn--outline" onclick="window.location='https://ya.ru'" style="margin-top:8px">Нет, мне ещё нет 18</button>
            </div>
        </div>

        <h1 class="form__title">Настройка <span>игры</span></h1>

        <?php
        // Show last session code if exists
        $lastCode = $_GET['continue'] ?? '';
        if (!$lastCode && isset($_COOKIE['last_game_code'])) {
            $lastCode = $_COOKIE['last_game_code'];
        }
        $hasSession = false;
        if ($lastCode) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE code=?");
            $stmt->execute([$lastCode]);
            $hasSession = $stmt->fetchColumn() > 0;
        }
        ?>

        <?php if ($hasSession): ?>
        <div style="text-align:center;margin-bottom:16px">
            <a href="/game?code=<?=e($lastCode)?>" class="btn btn--glow" style="margin-bottom:8px">▶ Продолжить игру</a>
            <div style="font-size:12px;color:rgba(255,255,255,.3)">Код: <?=e($lastCode)?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?><div class="msg msg--err"><?=e($error)?></div><?php endif; ?>

        <form method="post" class="form" id="gameForm">
            <!-- Mode Selection -->
            <div class="form__block">
                <h2>Режим игры</h2>
                <div class="mode-select">
                    <label class="mode-opt">
                        <input type="radio" name="mode" value="couple" onchange="updatePlayers()">
                        <span class="mode-opt__icon">♂♀</span>
                        <span class="mode-opt__label">Пара (М+Ж)</span>
                        <span class="mode-opt__desc">Для двоих</span>
                    </label>
                    <label class="mode-opt">
                        <input type="radio" name="mode" value="wmw" onchange="updatePlayers()">
                        <span class="mode-opt__icon">♀♂♀</span>
                        <span class="mode-opt__label">ЖМЖ</span>
                        <span class="mode-opt__desc">Две девушки + парень</span>
                    </label>
                    <label class="mode-opt">
                        <input type="radio" name="mode" value="mwm" onchange="updatePlayers()">
                        <span class="mode-opt__icon">♂♀♂</span>
                        <span class="mode-opt__label">МЖМ</span>
                        <span class="mode-opt__desc">Два парня + девушка</span>
                    </label>
                    <label class="mode-opt">
                        <input type="radio" name="mode" value="mwmw" onchange="updatePlayers()">
                        <span class="mode-opt__icon">♂♀♂♀</span>
                        <span class="mode-opt__label">МЖМЖ</span>
                        <span class="mode-opt__desc">Две пары</span>
                    </label>
                </div>
            </div>

            <!-- Players -->
            <div class="form__block" id="playersBlock">
                <h2>Игроки</h2>
                <div class="players" id="playersContainer"></div>
            </div>

            <!-- Categories -->
            <div class="form__block">
                <h2>Категории заданий</h2>
                <div class="cats-grid">
                    <?php foreach ($categories as $cat): ?>
                    <label class="cat-check">
                        <input type="checkbox" name="game_settings[categories][]" value="<?=$cat['id']?>" checked>
                        <span><?=e($cat['name'])?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Levels -->
            <div class="form__block">
                <h2>Уровень сложности</h2>
                <div class="level-select">
                    <label class="level-opt"><input type="checkbox" name="game_settings[levels][]" value="green" checked><span>🟢 Зелёный</span></label>
                    <label class="level-opt"><input type="checkbox" name="game_settings[levels][]" value="orange" checked><span>🟠 Оранжевый</span></label>
                    <label class="level-opt"><input type="checkbox" name="game_settings[levels][]" value="purple" checked><span>🟣 Фиолетовый</span></label>
                    <label class="level-opt"><input type="checkbox" name="game_settings[levels][]" value="red" checked><span>🔴 Красный</span></label>
                </div>
            </div>

            <!-- Duration -->
            <div class="form__block">
                <h2>Длительность</h2>
                <div class="dur-select">
                    <label class="dur-opt"><input type="radio" name="game_settings[duration]" value="15" checked><span>15 мин</span></label>
                    <label class="dur-opt"><input type="radio" name="game_settings[duration]" value="30"><span>30 мин</span></label>
                    <label class="dur-opt"><input type="radio" name="game_settings[duration]" value="60"><span>60 мин</span></label>
                    <label class="dur-opt"><input type="radio" name="game_settings[duration]" value="0"><span>Безлимит</span></label>
                </div>
            </div>

            <button type="submit" class="btn btn--glow btn--lg" style="width:100%;max-width:400px;margin:0 auto;display:flex">Начать игру</button>
        </form>
    </div>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__col"><a href="/" class="logo">Тайная комната</a><p class="footer__copy">© 2026 — 18+</p></div>
        </div>
    </div>
</footer>
</div>

<script>
const MODE_CONFIG = {
    couple: [
        { gender: 'male', label: 'Мужчина' },
        { gender: 'female', label: 'Девушка' },
    ],
    wmw: [
        { gender: 'male', label: 'Мужчина' },
        { gender: 'female', label: 'Девушка 1' },
        { gender: 'female', label: 'Девушка 2' },
    ],
    mwm: [
        { gender: 'male', label: 'Мужчина 1' },
        { gender: 'female', label: 'Девушка' },
        { gender: 'male', label: 'Мужчина 2' },
    ],
    mwmw: [
        { gender: 'male', label: 'Мужчина 1' },
        { gender: 'female', label: 'Девушка 1' },
        { gender: 'male', label: 'Мужчина 2' },
        { gender: 'female', label: 'Девушка 2' },
    ],
};

function updatePlayers() {
    const mode = document.querySelector('input[name="mode"]:checked').value;
    const config = MODE_CONFIG[mode];
    const container = document.getElementById('playersContainer');
    container.innerHTML = '';
    config.forEach((p, i) => {
        const div = document.createElement('div');
        div.className = 'player';
        div.innerHTML = `
            <input type="hidden" name="genders[]" value="${p.gender}">
            <div class="player__avatar player__avatar--${p.gender}">${p.gender === 'male' ? '♂' : '♀'}</div>
            <div class="player__fields">
                <input type="text" name="names[]" placeholder="${p.label}" required class="player__input" autocomplete="off">
                <div class="player__orient">
                    <label class="orient-opt"><input type="radio" name="orientation[${i}]" value="hetero" checked><span>Гетеро</span></label>
                    <label class="orient-opt"><input type="radio" name="orientation[${i}]" value="bi"><span>Би</span></label>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
    // Re-check mode radio active state
    document.querySelectorAll('.mode-opt').forEach(el => el.classList.remove('active'));
    const selected = document.querySelector(`.mode-opt input[value="${mode}"]`);
    if (selected) selected.closest('.mode-opt').classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    updatePlayers();
    document.querySelectorAll('input[name="mode"]').forEach(el => el.addEventListener('change', updatePlayers));
});

// Age gate on start page
(function(){
    if (document.cookie.includes('age_accepted_start=1')) {
        document.getElementById('agegateBlock').style.display = 'none';
    }
})();
function acceptAgeStart() {
    document.cookie = 'age_accepted_start=1;path=/;max-age=' + (86400 * 365);
    document.getElementById('agegateBlock').style.display = 'none';
}
// Secret admin entry: click title 5 times
let titleClicks = 0;
document.querySelector('.form__title')?.addEventListener('click', function() {
    titleClicks++;
    if (titleClicks >= 5) {
        window.location.href = '/admin/';
        titleClicks = 0;
    }
});
</script>
</body>
</html>