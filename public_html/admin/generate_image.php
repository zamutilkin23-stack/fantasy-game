<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
requireLogin();

header('Content-Type: application/json');

$cardId = (int)($_GET['card_id'] ?? 0);
if (!$cardId) {
    echo json_safe(['ok' => false, 'error' => 'No card ID']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card) {
    echo json_safe(['ok' => false, 'error' => 'Card not found']);
    exit;
}

// Build prompt: use prompt_override or card text
$prompt = trim($card['prompt_override'] ?? '');
if (!$prompt) {
    $prompt = $card['text'];
}

// Artistic style prefix
$stylePrefix = 'Эротическая иллюстрация, мягкий свет, пастельные тона, художественный стиль, без порнографии, намёк, чувственная атмосфера, крупный план. ';
$fullPrompt = $stylePrefix . $prompt;

// We'll write a placeholder - actual generation happens via external AI tool
// The URL will be filled when the image is generated
$imageUrl = '/img/card_placeholder.svg';

// Save placeholder image_url
$stmt = $pdo->prepare("UPDATE cards SET image_url = ?, updated_at = datetime('now') WHERE id = ?");
$stmt->execute([$imageUrl, $cardId]);

echo json_safe([
    'ok' => true,
    'image_url' => $imageUrl,
    'prompt' => $fullPrompt,
    'message' => 'Для генерации реального изображения используйте внешний AI-сервис. Промпт готов.',
]);