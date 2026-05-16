<?php
// =============================================================
//  UrbanUpC — Téléchargement de fichier transféré
//  VULNÉRABILITÉ intentionnelle :
//   - IDOR : le fichier est servi uniquement sur la base de l'id
//     sans vérifier que l'utilisateur connecté est sender_id
//     ou receiver_id. Tout utilisateur authentifié peut télécharger
//     le fichier d'un autre en incrémentant ?id=.
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$user = currentUser();
$pdo  = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Identifiant manquant.');
}

// VULNERABILITY: IDOR — requête sans filtre sur sender_id ou receiver_id.
// Un attaquant peut itérer les IDs et récupérer tous les fichiers.
$stmt = $pdo->prepare("SELECT * FROM file_transfers WHERE id = ?");
$stmt->execute([$id]);
$transfer = $stmt->fetch();

if (!$transfer) {
    http_response_code(404);
    include __DIR__ . '/errors/404.php';
    exit;
}

$filePath = UPLOAD_DIR . 'transfers/' . basename($transfer['file_path']);

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Fichier introuvable sur le serveur.');
}

logAudit($user['id'], $user['username'], 'DOWNLOAD_TRANSFER', '/download_transfer.php', 'success',
    "transfer_id=$id file={$transfer['file_name']}");

// Servir le fichier
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($transfer['file_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store');
readfile($filePath);
exit;
