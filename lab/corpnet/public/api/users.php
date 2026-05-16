<?php
// =============================================================
//  API Utilisateurs
//  VULNÉRABILITÉS : IDOR sur /api/users.php?id=X (pas de vérif rôle)
//  Exposition d'informations sensibles (email, phone, department)
// =============================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();

$user = currentUser();
$pdo  = getDB();

header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;

if ($id) {
    // IDOR : tout utilisateur peut consulter le profil de n'importe qui via ?id=X
    $stmt = $pdo->prepare(
        "SELECT id, username, first_name, last_name, email, role, department, phone, last_login, created_at
         FROM users WHERE id = ? AND active = 1"
    );
    $stmt->execute([$id]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        jsonResponse(['error' => 'Utilisateur introuvable'], 404);
    }

    logAudit($user['id'], $user['username'], 'VIEW_USER_PROFILE', '/api/users.php', 'success', "target_id=$id");
    jsonResponse($targetUser);
}

// Liste tous les utilisateurs (pas de pagination côté API)
$users = $pdo->query(
    "SELECT id, username, first_name, last_name, email, role, department, phone, last_login
     FROM users WHERE active = 1 ORDER BY last_name, first_name"
)->fetchAll();

logAudit($user['id'], $user['username'], 'LIST_USERS', '/api/users.php', 'success');
jsonResponse(['users' => $users, 'total' => count($users)]);
