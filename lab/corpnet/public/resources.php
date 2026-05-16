<?php
// =============================================================
//  Student Resource Hub — Plateforme de ressources étudiantes
//  VULNÉRABILITÉS intentionnelles :
//   1. Stored XSS    : les commentaires sont affichés sans htmlspecialchars
//   2. Unrestricted File Upload : aucune vérification d'extension
//      ou de MIME réel — les webshells .php sont acceptés
//   3. Pas de token CSRF
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$success = '';
$error   = '';

// Créer le répertoire de destination si nécessaire
$uploadDir = UPLOAD_DIR . 'resources/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── POST : dépôt de ressource ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $file  = $_FILES['resource_file'] ?? null;

    if (!$title) {
        $error = 'Le titre est obligatoire.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur lors de l\'envoi du fichier (code ' . ($file['error'] ?? '?') . ').';
    } else {
        // VULNERABILITY: Unrestricted File Upload
        // Aucune validation d'extension ni de type MIME réel.
        // Un fichier .php (webshell) sera accepté et sera accessible directement
        // via HTTP sur /uploads/resources/<nom>.php → RCE possible.
        $originalName = $file['name'];
        $destPath     = $uploadDir . $originalName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $pdo->prepare(
                "INSERT INTO resources (user_id, title, file_path) VALUES (?, ?, ?)"
            )->execute([$user['id'], $title, 'resources/' . $originalName]);
            logAudit($user['id'], $user['username'], 'UPLOAD_RESOURCE', '/resources.php', 'success', "file=$originalName size={$file['size']}");
            $success = 'Ressource <strong>' . htmlspecialchars($originalName) . '</strong> partagée avec succès.';
        } else {
            $error = 'Impossible de déplacer le fichier. Vérifiez les permissions du répertoire.';
        }
    }
}

// ── POST : ajout de commentaire ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'comment') {
    $resourceId = (int)($_POST['resource_id'] ?? 0);
    // VULNERABILITY: Stored XSS
    // Le commentaire est inséré tel quel — pas de strip_tags ni htmlspecialchars.
    // Le HTML/JavaScript soumis sera rendu directement dans le navigateur de chaque visiteur.
    $comment = $_POST['comment'] ?? '';

    if ($resourceId && $comment !== '') {
        $pdo->prepare(
            "INSERT INTO resource_comments (resource_id, user_id, comment) VALUES (?, ?, ?)"
        )->execute([$resourceId, $user['id'], $comment]);
        logAudit($user['id'], $user['username'], 'ADD_COMMENT', '/resources.php', 'success', "resource_id=$resourceId");
        header('Location: /resources.php?commented=1#resource-' . $resourceId);
        exit;
    }
}

// ── GET : chargement des données ───────────────────────────────────────────
$resources = $pdo->query(
    "SELECT r.*, u.first_name, u.last_name,
            COUNT(rc.id) AS comment_count
     FROM resources r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN resource_comments rc ON rc.resource_id = r.id
     GROUP BY r.id
     ORDER BY r.created_at DESC"
)->fetchAll();

$allComments = $pdo->query(
    "SELECT rc.*, u.first_name, u.last_name
     FROM resource_comments rc
     JOIN users u ON rc.user_id = u.id
     ORDER BY rc.created_at ASC"
)->fetchAll();

$commentsByResource = [];
foreach ($allComments as $c) {
    $commentsByResource[$c['resource_id']][] = $c;
}

logAudit($user['id'], $user['username'], 'VIEW_RESOURCES', '/resources.php', 'success');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ressources étudiantes — UrbanUpC | Université Paris Cité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/corpnet.css">
</head>
<body class="corpnet-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">
                    <i class="fas fa-book-open text-primary me-2"></i>Ressources étudiantes
                </h4>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload me-1"></i>Partager une ressource
                </button>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['commented'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-comment me-2"></i>Commentaire ajouté.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (empty($resources)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                    <p class="mb-3">Aucune ressource partagée pour l'instant.</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-plus me-1"></i>Être le premier à partager
                    </button>
                </div>
            </div>
            <?php else: ?>

            <div class="row g-3">
                <?php foreach ($resources as $res): ?>
                <div class="col-12" id="resource-<?= $res['id'] ?>">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <div class="me-3 mt-1">
                                    <i class="<?= fileIcon($res['file_path']) ?> fa-2x"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <h6 class="fw-semibold mb-1"><?= htmlspecialchars($res['title']) ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-user-circle me-1"></i>
                                                <?= htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) ?>
                                                &middot; <?= formatDate($res['created_at'], 'd/m/Y H:i') ?>
                                                &middot; <i class="fas fa-comments me-1"></i><?= $res['comment_count'] ?>
                                            </small>
                                        </div>
                                        <a href="/uploads/<?= htmlspecialchars($res['file_path']) ?>"
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-download me-1"></i>Télécharger
                                        </a>
                                    </div>

                                    <!-- Fil de commentaires -->
                                    <div class="mt-3">
                                        <?php if (!empty($commentsByResource[$res['id']])): ?>
                                        <div class="mb-3">
                                            <?php foreach ($commentsByResource[$res['id']] as $c): ?>
                                            <div class="d-flex gap-2 mb-2">
                                                <div class="avatar-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                     style="width:28px;height:28px;font-size:.68rem;color:#fff;min-width:28px;">
                                                    <?= mb_strtoupper(mb_substr($c['first_name'],0,1) . mb_substr($c['last_name'],0,1)) ?>
                                                </div>
                                                <div class="bg-light rounded px-3 py-2 small flex-grow-1">
                                                    <span class="fw-semibold"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></span>
                                                    <span class="text-muted ms-2" style="font-size:.7rem"><?= formatDate($c['created_at']) ?></span>
                                                    <div class="mt-1 text-break">
                                                        <?= $c['comment'] /* VULNERABILITY: Stored XSS — rendu brut sans htmlspecialchars */ ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Formulaire commentaire (pas de CSRF token — intentionnel) -->
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="action" value="comment">
                                            <input type="hidden" name="resource_id" value="<?= $res['id'] ?>">
                                            <input type="text" name="comment" class="form-control form-control-sm"
                                                   placeholder="Ajouter un commentaire…" required>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Modal : partager une ressource -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="uploadModalLabel">
                    <i class="fas fa-upload text-primary me-2"></i>Partager une ressource
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <!-- Pas de CSRF token (intentionnel) -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">
                            TITRE <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control"
                               placeholder="ex: TD Sécurité S2 – Corrigé" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">
                            FICHIER <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="resource_file" class="form-control" required>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1 text-muted"></i>PDF, ZIP, DOCX recommandés.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload me-1"></i>Déposer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
