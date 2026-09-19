<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

header('Content-Type: application/json');

$cardId = (int)($_GET['card_id'] ?? 0);
if (!$cardId) {
    echo json_safe(['ok' => false, 'error' => 'Укажите ID карточки']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card) {
    echo json_safe(['ok' => false, 'error' => 'Карточка не найдена']);
    exit;
}

// Build prompt from card data
$prompt = trim($card['prompt_override'] ?? '');
if (!$prompt) {
    $prompt = $card['text'];
}

$levelHint = ['green'=>'лёгкая эротика, флирт', 'orange'=>'чувственная эротика, ласки', 'red'=>'откровенная эротика, страсть'][$card['level']] ?? '';
$categoryLabels = [
    1 => 'романтика, флирт', 2 => 'классический секс, близость',
    3 => 'оральные ласки', 4 => 'анальная стимуляция',
    5 => 'мастурбация', 6 => 'доминирование мужчины',
    7 => 'доминирование девушки', 8 => 'секс-игрушки',
];

$fullPrompt = 'Эротическая художественная фотография, ' . $levelHint . ', ' . ($categoryLabels[$card['category_id']] ?? 'эстетика') . '. ' . $prompt . '. Мягкое освещение, натуральные тона, красивая композиция, высокое качество, 4K, фотореализм.';

// Save the prompt and mark as pending
if (!$card['image_url'] || $card['image_url'] === '/img/card_placeholder.svg') {
    $stmt = $pdo->prepare("UPDATE cards SET prompt_override = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$fullPrompt, $cardId]);
}

echo json_safe([
    'ok' => true,
    'prompt' => $fullPrompt,
    'message' => 'Промпт готов. Попроси ассистента сгенерировать картинку.',
]);