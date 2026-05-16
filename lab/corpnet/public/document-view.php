<?php
// =============================================================
//  Visionneuse de document
//  VULNÉRABILITÉ IDOR : id= non vérifié contre owner_id
//  Tout utilisateur authentifié peut voir n'importe quel document
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$id) {
    header('Location: /documents.php');
    exit;
}

// IDOR : pas de vérification que owner_id == user['id']
$stmt = $pdo->prepare(
    "SELECT d.*, u.first_name, u.last_name, u.department, u.email
     FROM documents d JOIN users u ON d.owner_id = u.id
     WHERE d.id = ?"
);
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    header('HTTP/1.1 404 Not Found');
    include __DIR__ . '/errors/404.php';
    exit;
}

logAudit($user['id'], $user['username'], 'VIEW_DOCUMENT', '/document-view.php', 'success', "doc_id=$id classification={$doc['classification']}");

$classColors = [
    'public'       => 'success',
    'internal'     => 'info',
    'confidential' => 'warning',
    'secret'       => 'danger',
];
$classColor = $classColors[$doc['classification']] ?? 'secondary';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc['title']) ?> — UrbanUpC | Université Paris Cité</title>
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

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="/dashboard.php" class="text-decoration-none">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="/documents.php" class="text-decoration-none">Documents</a></li>
                    <li class="breadcrumb-item active text-truncate" style="max-width:300px">
                        <?= htmlspecialchars($doc['title']) ?>
                    </li>
                </ol>
            </nav>

            <!-- En-tête document -->
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="<?= fileIcon($doc['file_name'] ?? 'file.txt') ?> me-2"></i><?= htmlspecialchars($doc['title']) ?>
                    </h4>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?= classificationBadge($doc['classification']) ?>
                        <span class="text-muted small">
                            <i class="fas fa-folder me-1"></i><?= htmlspecialchars($doc['category'] ?? 'Général') ?>
                        </span>
                        <span class="text-muted small">
                            <i class="fas fa-calendar me-1"></i><?= formatDate($doc['created_at']) ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($doc['owner_id'] == $user['id'] || in_array($user['role'], ['manager','admin'])): ?>
                    <a href="/document-edit.php?id=<?= $doc['id'] ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>Modifier
                    </a>
                    <?php endif; ?>
                    <a href="/documents.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Retour aux documents
                    </a>
                </div>
            </div>

            <?php if ($doc['classification'] === 'secret'): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 py-2">
                <i class="fas fa-lock fa-lg"></i>
                <div>
                    <strong>Document SECRET</strong> — Accès restreint. Toute divulgation non autorisée est passible de sanctions disciplinaires et pénales.
                </div>
            </div>
            <?php elseif ($doc['classification'] === 'confidential'): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4 py-2">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Document CONFIDENTIEL</strong> — Usage interne uniquement. Ne pas diffuser à l'extérieur de Université Paris Cité.
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Contenu principal -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-file-alt text-primary me-2"></i>Contenu du document
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($doc['content']): ?>
                                <div class="document-content" style="white-space: pre-wrap; font-family: inherit; line-height: 1.7; font-size: .9rem;">
                                    <?= htmlspecialchars($doc['content']) ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-file fa-3x mb-3 opacity-25"></i>
                                    <p class="small">Aucun contenu textuel disponible pour ce document.</p>
                                    <?php if ($doc['file_name']): ?>
                                        <a href="/uploads/<?= htmlspecialchars($doc['file_name']) ?>"
                                           class="btn btn-outline-primary btn-sm" target="_blank">
                                            <i class="fas fa-download me-2"></i>Télécharger le fichier
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Métadonnées -->
                <div class="col-lg-4">

                    <!-- Propriétaire -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-user text-secondary me-2"></i>Propriétaire
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar-circle me-3">
                                    <?= mb_strtoupper(mb_substr($doc['first_name'], 0, 1) . mb_substr($doc['last_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold small">
                                        <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($doc['department'] ?? '') ?>
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted mb-0">
                                <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($doc['email']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Métadonnées fichier -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-info-circle text-secondary me-2"></i>Informations
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">ID</td>
                                        <td class="pe-3 small"><code><?= $doc['id'] ?></code></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">Classification</td>
                                        <td class="pe-3"><?= classificationBadge($doc['classification']) ?></td>
                                    </tr>
                                    <?php if ($doc['category']): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">Catégorie</td>
                                        <td class="pe-3 small"><?= htmlspecialchars($doc['category']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($doc['file_name']): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">Fichier</td>
                                        <td class="pe-3 small text-truncate" style="max-width:150px">
                                            <code title="<?= htmlspecialchars($doc['file_name']) ?>">
                                                <?= htmlspecialchars($doc['file_name']) ?>
                                            </code>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($doc['file_size']) && $doc['file_size']): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">Taille</td>
                                        <td class="pe-3 small"><?= formatSize($doc['file_size']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">Créé le</td>
                                        <td class="pe-3 small"><?= formatDate($doc['created_at']) ?></td>
                                    </tr>
                                    <?php if ($doc['updated_at'] && $doc['updated_at'] !== $doc['created_at']): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small fw-semibold">Modifié le</td>
                                        <td class="pe-3 small"><?= formatDate($doc['updated_at']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($doc['file_name']): ?>
                        <div class="card-footer bg-white border-0 pt-0">
                            <a href="/uploads/<?= htmlspecialchars($doc['file_name']) ?>"
                               class="btn btn-outline-primary btn-sm w-100" target="_blank">
                                <i class="fas fa-download me-2"></i>Télécharger
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
