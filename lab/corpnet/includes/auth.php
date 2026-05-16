<?php
// =============================================================
//  UrbanUpC — Gestion Authentification & Sessions
//  Vulnérabilités intentionnelles :
//   - Token de session = MD5(user_id + timestamp) → prévisible
//   - Pas de régénération de session après login
//   - Cookie sans HttpOnly ni Secure
//   - Pas de CSRF token
// =============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/app.php';

session_start();

/**
 * Vérifie si l'utilisateur est connecté (via session PHP ou cookie)
 */
function isAuthenticated(): bool {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    // Vérification du cookie de session applicatif (sans HttpOnly)
    if (isset($_COOKIE['corpnet_token'])) {
        return validateToken($_COOKIE['corpnet_token']);
    }
    return false;
}

/**
 * Valide un token de session en base (token prévisible = MD5)
 */
function validateToken(string $token): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT s.user_id, u.username, u.role, u.first_name, u.last_name, u.department
         FROM sessions s
         JOIN users u ON s.user_id = u.id
         WHERE s.session_token = ? AND s.expires_at > NOW() AND u.active = 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['user_id']    = $row['user_id'];
        $_SESSION['username']   = $row['username'];
        $_SESSION['role']       = $row['role'];
        $_SESSION['first_name'] = $row['first_name'];
        $_SESSION['last_name']  = $row['last_name'];
        $_SESSION['department'] = $row['department'];
        return true;
    }
    return false;
}

/**
 * Authentifie un utilisateur (login / password MD5)
 * Pas de rate limiting — brute force possible
 */
function login(string $username, string $password): array {
    $pdo = getDB();
    // Requête avec MD5 côté SQL — mot de passe jamais haché correctement
    $stmt = $pdo->prepare(
        "SELECT id, username, role, first_name, last_name, department
         FROM users
         WHERE username = ? AND password = MD5(?) AND active = 1"
    );
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if (!$user) {
        logAudit(null, $username, 'LOGIN_FAILED', '/login', 'failure');
        return ['success' => false, 'message' => 'Identifiants incorrects.'];
    }

    // Création session — token prévisible
    $token = md5($user['id'] . time());
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    $pdo->prepare(
        "INSERT INTO sessions (session_token, user_id, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $token,
        $user['id'],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $expires
    ]);

    // Mise à jour last_login
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    // Cookie sans HttpOnly ni Secure (intentionnel)
    setcookie('corpnet_token', $token, time() + SESSION_LIFETIME, '/', '', false, false);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    $_SESSION['department'] = $user['department'];

    logAudit($user['id'], $user['username'], 'LOGIN', '/login', 'success');

    return ['success' => true, 'user' => $user, 'token' => $token];
}

/**
 * Déconnexion
 */
function logout(): void {
    if (isset($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'LOGOUT', '/logout', 'success');
        $pdo = getDB();
        if (isset($_COOKIE['corpnet_token'])) {
            $pdo->prepare("DELETE FROM sessions WHERE session_token = ?")->execute([$_COOKIE['corpnet_token']]);
        }
    }
    session_destroy();
    setcookie('corpnet_token', '', time() - 3600, '/');
    header('Location: /login.php');
    exit;
}

/**
 * Récupère l'utilisateur connecté
 */
function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'         => $_SESSION['user_id'],
        'username'   => $_SESSION['username'],
        'role'       => $_SESSION['role'],
        'first_name' => $_SESSION['first_name'],
        'last_name'  => $_SESSION['last_name'],
        'department' => $_SESSION['department'],
    ];
}

/**
 * Redirige vers login si non authentifié
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Vérifie un rôle minimum
 */
function requireRole(string $role): void {
    requireAuth();
    $roles = ['user' => 1, 'manager' => 2, 'admin' => 3];
    $current = $roles[$_SESSION['role']] ?? 0;
    $required = $roles[$role] ?? 99;
    if ($current < $required) {
        http_response_code(403);
        include __DIR__ . '/../public/errors/403.php';
        exit;
    }
}

/**
 * Enregistre une action dans les logs d'audit
 */
function logAudit(?int $userId, ?string $username, string $action, string $resource, string $status = 'success', string $details = ''): void {
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Insertion BDD (comportement existant)
    try {
        $pdo = getDB();
        $pdo->prepare(
            "INSERT INTO audit_logs (user_id, username, action, resource, ip_address, user_agent, status, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $username, $action, $resource, $ip, $userAgent, $status, $details]);
    } catch (Exception $e) {
        // Silently fail — les logs ne doivent pas bloquer l'appli
    }

    // JSON line logging — ingestion SIEM (format NDJSON)
    $entry = json_encode([
        'timestamp'  => date('c'),
        'user_id'    => $userId,
        'username'   => $username,
        'action'     => $action,
        'resource'   => $resource,
        'ip'         => $ip,
        'user_agent' => $userAgent,
        'status'     => $status,
        'details'    => $details,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents('/var/log/corpnet/audit.json', $entry . "\n", FILE_APPEND | LOCK_EX);
}
