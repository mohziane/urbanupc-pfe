<?php
// =============================================================
//  UrbanUpC — Chat API (endpoint AJAX)
//  Vulnérabilités intentionnelles :
//   - Broken Access Control : le canal (channel=) n'est JAMAIS
//     validé contre le rôle de l'utilisateur. Un étudiant peut
//     appeler ?action=fetch&channel=staff et lire tous les messages.
//   - Stored XSS : les messages sont renvoyés bruts dans le JSON
//     et injectés via innerHTML côté client (chat.js).
//   - Pas de token CSRF sur le POST.
// =============================================================

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$user   = currentUser();
$pdo    = getDB();
$action = $_GET['action'] ?? '';

// Liste des canaux valides — existence check UNIQUEMENT, aucune vérif de rôle
$validChannels = ['general', 'students', 'staff'];
$channel = $_GET['channel'] ?? 'general';
if (!in_array($channel, $validChannels, true)) {
    $channel = 'general';
}

// ── GET : récupère les nouveaux messages ──────────────────────────────────────
if ($action === 'fetch' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // VULNERABILITY: Broken Access Control — aucune vérification du rôle pour
    // le canal demandé. Un étudiant (?channel=staff) obtient tous les messages.
    $lastId = (int)($_GET['last_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT m.id, m.message, m.created_at,
                u.first_name, u.last_name, u.role AS user_role
         FROM chat_messages m
         JOIN users u ON m.user_id = u.id
         WHERE m.channel_name = ? AND m.id > ?
         ORDER BY m.created_at ASC
         LIMIT 50"
    );
    $stmt->execute([$channel, $lastId]);
    // VULNERABILITY: Stored XSS — message renvoyé brut, sans htmlspecialchars
    $messages = $stmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'channel'  => $channel,
        'messages' => $messages,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST : envoie un message ──────────────────────────────────────────────────
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de token CSRF (intentionnel)
    // VULNERABILITY: Broken Access Control — tout utilisateur authentifié peut
    // poster dans n'importe quel canal, y compris 'staff'.
    $msg = trim($_POST['message'] ?? '');
    if ($msg === '') {
        echo json_encode(['success' => false, 'error' => 'Message vide']);
        exit;
    }

    $pdo->prepare(
        "INSERT INTO chat_messages (channel_name, user_id, message) VALUES (?, ?, ?)"
    )->execute([$channel, $user['id'], $msg]);

    $newId = (int)$pdo->lastInsertId();
    logAudit($user['id'], $user['username'], 'POST_CHAT', '/api/chat_api.php', 'success', "channel=$channel");

    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action invalide ou méthode incorrecte']);
