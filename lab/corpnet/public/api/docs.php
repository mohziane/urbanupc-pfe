<?php
// =============================================================
//  API Documents
//  VULNÉRABILITÉ IDOR : id= non vérifié contre la session
//  Tout utilisateur authentifié peut accéder à n'importe quel doc
// =============================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();

$user   = currentUser();
$pdo    = getDB();
$action = $_GET['action'] ?? 'download';
$id     = $_GET['id'] ?? null;

if (!$id) {
    jsonResponse(['error' => 'Paramètre id requis'], 400);
}

// IDOR : pas de vérification que owner_id == user['id']
$stmt = $pdo->prepare(
    "SELECT d.*, u.first_name, u.last_name
     FROM documents d JOIN users u ON d.owner_id = u.id
     WHERE d.id = ?"
);
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    jsonResponse(['error' => 'Document introuvable'], 404);
}

logAudit($user['id'], $user['username'], 'ACCESS_DOC_' . strtoupper($action), '/api/docs.php', 'success', "doc_id=$id classification={$doc['classification']}");

if ($action === 'view') {
    jsonResponse([
        'id'             => $doc['id'],
        'title'          => $doc['title'],
        'content'        => $doc['content'],
        'classification' => $doc['classification'],
        'owner'          => $doc['first_name'] . ' ' . $doc['last_name'],
        'file_name'      => $doc['file_name'],
        'created_at'     => $doc['created_at'],
        'updated_at'     => $doc['updated_at'],
    ]);
}

// Action download : retourne les métadonnées (fichier physique simulé)
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'id'         => $doc['id'],
    'file'       => $doc['file_name'],
    'title'      => $doc['title'],
    'size'       => $doc['file_size'] ?? 0,
    'download'   => '/uploads/' . ($doc['file_name'] ?? ''),
    'message'    => 'Accès autorisé',
], JSON_UNESCAPED_UNICODE);
