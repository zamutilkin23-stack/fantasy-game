<?php
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, string $encoding = 'UTF-8'): string {
        return substr($s, $start, $length);
    }
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_safe(mixed $data): string {
    $result = json_encode($data, JSON_UNESCAPED_UNICODE);
    return $result !== false ? $result : '[]';
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}