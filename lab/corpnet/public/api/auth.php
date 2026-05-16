<?php
// =============================================================
//  API Authentification
//  VULNÉRABILITÉS : pas de rate limiting, réponse différenciée
//  (user inexistant vs mauvais mot de passe), token prévisible
// =============================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? $_POST['username'] ?? '');
$password = $body['password'] ?? $_POST['password'] ?? '';

if (!$username || !$password) {
    jsonResponse(['error' => 'Paramètres manquants'], 400);
}

// Réponse différenciée — user inexistant vs mauvais mot de passe (user enumeration)
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND active = 1");
$stmt->execute([$username]);
$exists = $stmt->fetch();

if (!$exists) {
    // Message différent → enumération utilisateurs possible
    logAudit(null, $username, 'API_LOGIN_FAILED', '/api/auth.php', 'failure', 'user_not_found');
    jsonResponse(['error' => 'Utilisateur introuvable', 'code' => 'USER_NOT_FOUND'], 401);
}

$result = login($username, $password);
if (!$result['success']) {
    jsonResponse(['error' => 'Mot de passe incorrect', 'code' => 'BAD_PASSWORD'], 401);
}

jsonResponse([
    'success' => true,
    'token'   => $result['token'],
    'user'    => [
        'id'         => $result['user']['id'],
        'username'   => $result['user']['username'],
        'role'       => $result['user']['role'],
        'first_name' => $result['user']['first_name'],
        'last_name'  => $result['user']['last_name'],
    ],
    'expires_in' => SESSION_LIFETIME,
]);
