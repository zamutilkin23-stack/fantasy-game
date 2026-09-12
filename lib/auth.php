<?php
function startAdminSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function isLoggedIn(): bool {
    startAdminSession();
    return !empty($_SESSION['admin_user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /admin/');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['admin_role'] !== $role && $_SESSION['admin_role'] !== 'admin') {
        http_response_code(403);
        echo 'Доступ запрещён';
        exit;
    }
}

function login(string $login, string $password): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) return false;
    startAdminSession();
    $_SESSION['admin_user_id'] = $user['id'];
    $_SESSION['admin_login'] = $user['login'];
    $_SESSION['admin_role'] = $user['role'];
    return true;
}

function logout(): void {
    startAdminSession();
    session_destroy();
}