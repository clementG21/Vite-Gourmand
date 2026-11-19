<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,       // cookie inaccessible au JavaScript (limite le vol de session via XSS)
        'samesite' => 'Lax',      // limite le CSRF
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Échappement systématique de toute sortie HTML (protection XSS)
function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- CSRF -----------------------------------------------------------
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Requête invalide.');
    }
}

// --- Mot de passe : 10 caractères min, 1 spécial, 1 maj, 1 min, 1 chiffre ---
function password_errors(string $p): array {
    $errors = [];
    if (mb_strlen($p) < 10)          $errors[] = '10 caractères minimum';
    if (!preg_match('/[A-Z]/', $p))  $errors[] = 'une majuscule';
    if (!preg_match('/[a-z]/', $p))  $errors[] = 'une minuscule';
    if (!preg_match('/[0-9]/', $p))  $errors[] = 'un chiffre';
    if (!preg_match('/[^A-Za-z0-9]/', $p)) $errors[] = 'un caractère spécial';
    return $errors;
}

// --- Authentification / rôles ----------------------------------------
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('warning', 'Veuillez vous connecter.'); header('Location: /connexion.php'); exit; }
    // Un compte désactivé (ex : employé parti) perd l'accès tout de suite, même avec une session ouverte
    require_once __DIR__ . '/db.php';
    $s = db()->prepare('SELECT actif FROM utilisateur WHERE utilisateur_id = ?');
    $s->execute([$u['id']]);
    if (!$s->fetchColumn()) {
        unset($_SESSION['user']);
        flash('warning', 'Ce compte est désactivé.');
        header('Location: /connexion.php'); exit;
    }
    return $u;
}
// Les droits sont TOUJOURS vérifiés côté serveur, jamais seulement en masquant un bouton.
function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('Accès refusé.'); }
    return $u;
}

// --- Messages flash ---------------------------------------------------
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
