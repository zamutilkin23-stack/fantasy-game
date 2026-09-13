<?php
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
if (!defined('DATA_DIR'))  define('DATA_DIR', APP_ROOT . '/data');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbPath = DATA_DIR . '/app.sqlite';
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
    $isNew = !file_exists($dbPath);

    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    if ($isNew) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            login TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'editor',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text TEXT NOT NULL,
            level TEXT NOT NULL CHECK(level IN ('green','orange','red')),
            category_id INTEGER REFERENCES categories(id),
            performer TEXT NOT NULL CHECK(performer IN ('male','female','any','all')),
            target TEXT NOT NULL CHECK(target IN ('partner_m','partner_f','self','each_other','all')),
            modes TEXT NOT NULL DEFAULT '[]',
            status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','published')),
            author_id INTEGER REFERENCES users(id),
            image_url TEXT,
            prompt_override TEXT,
            is_finale INTEGER NOT NULL DEFAULT 0,
            is_favorite INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            code TEXT PRIMARY KEY,
            players TEXT NOT NULL DEFAULT '[]',
            mode TEXT NOT NULL,
            settings TEXT NOT NULL DEFAULT '{}',
            completed_card_ids TEXT NOT NULL DEFAULT '[]',
            current_turn INTEGER NOT NULL DEFAULT 0,
            score TEXT NOT NULL DEFAULT '{}',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        // Default categories
        $categories = [
            ['Флирт', 'flirt', 0],
            ['Классический секс', 'classic', 1],
            ['Оральный секс', 'oral', 2],
            ['Анальный секс', 'anal', 3],
            ['Мастурбация', 'masturbation', 4],
            ['Мужчина доминирует', 'dom_m', 5],
            ['Девушка доминирует', 'dom_f', 6],
            ['Секс-игрушки', 'toys', 7],
        ];
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)");
        foreach ($categories as $c) $stmt->execute($c);

        // Default settings
        $defaults = [
            'show_screenshots' => '1',
            'show_features' => '1',
            'show_categories_detail' => '1',
            'show_levels_count' => '1',
            'screenshot_placeholder' => '',
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
    }

    return $pdo;
}