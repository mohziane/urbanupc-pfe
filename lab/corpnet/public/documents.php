<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();

$filter  = $_GET['filter'] ?? 'mine';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Clause de visibilité selon le rôle de l'utilisateur connecté
$visWhere = visibilityClause($user['role'], 'd');

// Construction de la requête selon le filtre
// Note : filter=all accessible à tous (pas de vérification de rôle ici — IDOR logique)
if ($filter === 'all') {
    $countSql  = "SELECT COUNT(*) FROM documents d
                  JOIN users u ON d.owner_id = u.id
                  WHERE $visWhere";
    $listSql   = "SELECT d.*, u.first_name, u.last_name, u.department
                  FROM documents d JOIN users u ON d.owner_id = u.id
                  WHERE $visWhere
                  ORDER BY d.updated_at DESC LIMIT $perPage OFFSET :offset";
    $totalRows = $pdo->query($countSql)->fetchColumn();
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE owner_id = ?");
    $countStmt->execute([$user['id']]);
    $totalRows = $countStmt->fetchColumn();
    $listSql   = "SELECT d.*, u.first_name, u.last_name, u.department
                  FROM documents d JOIN users u ON d.owner_id = u.id
                  WHERE d.owner_id = {$user['id']}
                  ORDER BY d.updated_at DESC LIMIT $perPage OFFSET :offset";
}

$pagination = paginate($totalRows, $perPage, $page);
$stmt = $pdo->prepare($listSql);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_DOCUMENTS', '/documents.php', 'success', "filter=$filter page=$page");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents — UrbanUpC | Université Paris Cité</title>
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

            <?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-lock me-2"></i>Accès refusé. Vous n'avez pas les droits pour effectuer cette action.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">
                    <i class="fas fa-folder-open text-warning me-2"></i>Gestion documentaire
                </h4>
                <?php if (in_array($user['role'], ['admin', 'manager'])): ?>
                <div class="d-flex gap-2">
                    <a href="/document-new.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Nouveau document
                    </a>
                    <a href="/upload.php" class="btn btn-outline-secondary">
                        <i class="fas fa-upload me-2"></i>Déposer un fichier
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filtres -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'mine' ? 'active' : '' ?>"
                       href="?filter=mine">
                        <i class="fas fa-user me-1"></i>Mes documents
                    </a>
                </li>
                <li class="nav-item">
                    <!-- Accessible à tous les utilisateurs — pas de contrôle de rôle -->
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>"
                       href="?filter=all">
                        <i class="fas fa-globe me-1"></i>Tous les documents
                    </a>
                </li>
            </ul>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                    <span class="small text-muted">
                        <?= $totalRows ?> document(s) — Page <?= $pagination['current_page'] ?>/<?= max(1,$pagination['total_pages']) ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($documents)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                            <p>Aucun document trouvé.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Document</th>
                                    <th>Classification</th>
                                    <th>Propriétaire</th>
                                    <th>Département</th>
                                    <th>Modifié le</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <i class="<?= fileIcon($doc['file_name'] ?? '') ?> me-3 fa-lg"></i>
                                            <div>
                                                <div class="fw-semibold small">
                                                    <?= htmlspecialchars($doc['title']) ?>
                                                </div>
                                                <?php if ($doc['file_name']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($doc['file_name']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= classificationBadge($doc['classification']) ?></td>
                                    <td class="small">
                                        <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($doc['department'] ?? '') ?></td>
                                    <td class="small text-muted"><?= formatDate($doc['updated_at'], 'd/m/Y H:i') ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- IDOR : id= non vérifié côté page -->
                                            <a href="/document-view.php?id=<?= $doc['id'] ?>"
                                               class="btn btn-outline-primary" title="Consulter">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="d-flex justify-content-center py-3">
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $page-1 ?>">‹</a>
                                </li>
                                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $page+1 ?>">›</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
