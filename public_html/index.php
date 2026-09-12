<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
$pdo = getDB();
$settings = $pdo->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Эротические фанты для компании 18+</title>
<meta name="description" content="Эротическая игра фанты для двоих и компании. Откровенные задания, настройки под себя, поддержка любых устройств.">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#14042d">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="layout">

<!-- Age Gate -->
<div class="agegate" id="agegate">
    <div class="agegate__card">
        <div class="agegate__icon">18+</div>
        <h2>Вам есть 18 лет?</h2>
        <p>Сайт содержит материалы для взрослых</p>
        <div class="agegate__btns">
            <button class="btn btn--glow" onclick="acceptAge()">Да, мне есть 18</button>
            <button class="btn btn--outline" onclick="window.location='https://ya.ru'">Нет, мне ещё нет 18</button>
        </div>
    </div>
</div>

<!-- Header -->
<header class="header">
    <div class="container">
        <div class="header__grid">
            <a href="/" class="logo">фанты.online</a>
            <nav class="nav" id="nav">
                <a href="#features" class="nav__link">Особенности</a>
                <a href="#categories" class="nav__link">Категории</a>
                <a href="#play" class="nav__link nav__link--play">Играть</a>
            </nav>
            <button class="burger" id="burger" aria-label="Меню">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Intro / Play -->
<section class="hero" id="play">
    <div class="container">
        <div class="hero__content">
            <h1 class="hero__title">Секс фанты <span>для компании</span></h1>
            <p class="hero__desc">Эротическая игра для двоих, троих и четверых. Сотни откровенных заданий, настройка под ваши предпочтения и поддержка любых устройств.</p>
            <div class="hero__links">
                <a href="/start" class="btn btn--glow btn--lg">Начать игру</a>
                <a href="/wmw" class="btn btn--pink">ЖМЖ</a>
                <a href="/mwm" class="btn btn--blue">МЖМ</a>
                <a href="/mwmw" class="btn btn--lilac">МЖМЖ</a>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<?php if ($settings['show_features'] ?? '1' === '1'): ?>
<section class="section" id="features">
    <div class="container">
        <h2 class="section__title">Особенности <span>игры</span></h2>
        <div class="features">
            <div class="feature">
                <div class="feature__icon"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#F663B3" stroke-width="2"/><path d="M16 24l6 6 10-10" stroke="#F663B3" stroke-width="2" fill="none"/></svg></div>
                <h3>Для двоих и компании</h3>
                <p>Играйте вдвоём, втроём или вчетвером — выбирайте любой режим</p>
            </div>
            <div class="feature">
                <div class="feature__icon"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#00A7D1" stroke-width="2"/><path d="M24 14v20M14 24h20" stroke="#00A7D1" stroke-width="2" fill="none"/></svg></div>
                <h3>3 уровня сложности</h3>
                <p>Зелёные (флирт), оранжевые (ласки), красные (секс) — нарастайте градус</p>
            </div>
            <div class="feature">
                <div class="feature__icon"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#BE5AFF" stroke-width="2"/><path d="M14 24h20M24 14v20" stroke="#BE5AFF" stroke-width="2" fill="none"/></svg></div>
                <h3>Настройка категорий</h3>
                <p>Выберите только то, что вам подходит — от классики до игрушек</p>
            </div>
            <div class="feature">
                <div class="feature__icon"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#FF6B35" stroke-width="2"/><path d="M16 24l6-6 10 10" stroke="#FF6B35" stroke-width="2" fill="none"/></svg></div>
                <h3>Учёт ориентации</h3>
                <p>Каждый игрок выбирает свои предпочтения — карточки подбираются индивидуально</p>
            </div>
            <div class="feature">
                <div class="feature__icon"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#4CAF50" stroke-width="2"/><path d="M24 12v24" stroke="#4CAF50" stroke-width="2" fill="none"/><path d="M12 24h24" stroke="#4CAF50" stroke-width="2" fill="none"/></svg></div>
                <h3>Таймер заданий</h3>
                <p>Не застревайте — таймер подскажет, когда пора переходить к следующему</p>
            </div>
            <div class="feature">
                <div class="feature__icon"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#F663B3" stroke-width="2"/><path d="M24 18v6l4 4" stroke="#F663B3" stroke-width="2" fill="none"/></svg></div>
                <h3>Сохранение сессии</h3>
                <p>Уникальный код для продолжения игры с того же места</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Categories Detail -->
<?php if ($settings['show_categories_detail'] ?? '1' === '1'): ?>
<section class="section section--dark" id="categories">
    <div class="container">
        <h2 class="section__title">Разнообразие <span>заданий</span></h2>
        <div class="cats">
            <?php $descriptions = [
                'flirt' => 'Лёгкие и игривые задания для начала игры и раскрепощения',
                'classic' => 'Традиционный секс, новые позы, техники и эксперименты',
                'oral' => 'Оральные ласки, минет, кунилингус и всё, что с ними связано',
                'anal' => 'Анальные игры для опытных пар и новичков',
                'masturbation' => 'Самостимуляция на глазах у партнёра и совместная мастурбация',
                'dom_m' => 'Парень берёт инициативу — доминирование и подчинение',
                'dom_f' => 'Девушка управляет процессом — власть и контроль',
                'toys' => 'Использование секс-игрушек для новых ощущений',
            ]; foreach ($categories as $cat): $desc = $descriptions[$cat['slug']] ?? ''; ?>
            <div class="cat">
                <h3><?=e($cat['name'])?></h3>
                <p><?=e($desc)?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Levels Count -->
<?php if ($settings['show_levels_count'] ?? '1' === '1'): ?>
<section class="section">
    <div class="container">
        <h2 class="section__title">Количество <span>заданий</span></h2>
        <div class="levels">
            <div class="level level--green">
                <div class="level__num">500+</div>
                <h3>Зелёные</h3>
                <p>Флирт и лёгкие ласки</p>
            </div>
            <div class="level level--orange">
                <div class="level__num">1500+</div>
                <h3>Оранжевые</h3>
                <p>Эротические ласки</p>
            </div>
            <div class="level level--red">
                <div class="level__num">1000+</div>
                <h3>Красные</h3>
                <p>Секс и откровенность</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Screenshots -->
<?php if ($settings['show_screenshots'] ?? '1' === '1'): ?>
<section class="section section--dark">
    <div class="container">
        <h2 class="section__title">Игровой <span>интерфейс</span></h2>
        <?php if ($placeholder = $settings['screenshot_placeholder'] ?? ''): ?>
        <p class="placeholder"><?=e($placeholder)?></p>
        <?php else: ?>
        <div class="screenshots">
            <div class="screenshot"><div class="screenshot__inner"><div class="screenshot__badge green">Зелёный</div><p>Задания-флирт</p></div></div>
            <div class="screenshot"><div class="screenshot__inner"><div class="screenshot__badge orange">Оранжевый</div><p>Задания-ласки</p></div></div>
            <div class="screenshot"><div class="screenshot__inner"><div class="screenshot__badge red">Красный</div><p>Задания-секс</p></div></div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__col">
                <a href="/" class="logo">фанты.online</a>
                <p class="footer__copy">© 2026 — Игра для взрослых 18+</p>
            </div>
            <div class="footer__col">
                <h4>Игры</h4>
                <a href="/start">Фанты для двоих</a>
                <a href="/wmw">Фанты ЖМЖ</a>
                <a href="/mwm">Фанты МЖМ</a>
                <a href="/mwmw">Фанты МЖМЖ</a>
            </div>
        </div>
    </div>
</footer>

</div>

<script>
// Age gate
(function(){
    if (document.cookie.includes('age_verified=1')) {
        document.getElementById('agegate').style.display = 'none';
    }
})();
function acceptAge() {
    document.cookie = 'age_verified=1;path=/;max-age=' + (86400 * 365);
    document.getElementById('agegate').style.display = 'none';
}

// Burger menu
document.getElementById('burger').addEventListener('click', function(){
    this.classList.toggle('active');
    document.getElementById('nav').classList.toggle('nav--open');
});

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e){
        e.preventDefault();
        const el = document.querySelector(this.getAttribute('href'));
        if (el) el.scrollIntoView({behavior:'smooth'});
        document.getElementById('nav').classList.remove('nav--open');
        document.getElementById('burger').classList.remove('active');
    });
});

// Intersection Observer for scroll animations
(function(){
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.feature, .cat').forEach(el => observer.observe(el));
})();
</script>
</body>
</html>