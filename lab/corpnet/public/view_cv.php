<?php
// =============================================================
//  Téléchargement d'un CV de candidature
//  VULNERABILITY: IDOR (Insecure Direct Object Reference)
//   - Aucune vérification que l'utilisateur connecté est admin
//     ou propriétaire de la candidature (student_id).
//   - N'importe quel utilisateur authentifié peut incrémenter
//     app_id pour accéder aux CVs de tous les autres candidats.
//   - Pattern d'attaque : GET /view_cv.php?app_id=1, ?app_id=2 …
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$user = currentUser();
$pdo  = getDB();

$appId = (int)($_GET['app_id'] ?? 0);

if (!$appId) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p>Paramètre <code>app_id</code> manquant.</p>');
}

// VULNERABILITY: IDOR — la requête ne filtre PAS sur student_id = $user['id']
// ni ne vérifie que $user['role'] === 'admin'.
// Toute valeur numérique de app_id est acceptée.
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p>Candidature introuvable (id=' . $appId . ').</p>');
}

$filePath = UPLOAD_DIR . 'cvs/' . basename($app['cv_file_path']);

if (!file_exists($filePath)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p>Fichier CV introuvable sur le serveur.</p>');
}

// Audit — enregistre $user (l'attaquant potentiel) + $app['student_id'] (la victime)
logAudit(
    $user['id'],
    $user['username'],
    'DOWNLOAD_CV',
    '/view_cv.php',
    'success',
    "app_id=$appId owner_id={$app['student_id']}"
);

// Servir le fichier
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath) ?: 'application/octet-stream';
$fileName = basename($app['cv_file_path']);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
