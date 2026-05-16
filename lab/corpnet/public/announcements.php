<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// Clause de visibilité selon le rôle de l'utilisateur connecté
$visWhere = visibilityClause($user['role'], 'a');

$totalRows  = $pdo->query("SELECT COUNT(*) FROM announcements a WHERE $visWhere")->fetchColumn();
$pagination = paginate($totalRows, $perPage, $page);

$stmt = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name, u.department
     FROM announcements a JOIN users u ON a.author_id = u.id
     WHERE $visWhere
     ORDER BY a.pinned DESC, a.created_at DESC
     LIMIT $perPage OFFSET :offset"
);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$announcements = $stmt->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_ANNOUNCEMENTS', '/announcements.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces — UrbanUpC | Université Paris Cité</title>
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
                    <i class="fas fa-bullhorn text-warning me-2"></i>Annonces
                </h4>
                <?php if (in_array($user['role'], ['manager', 'admin'])): ?>
                <a href="/announcement-form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Nouvelle annonce
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($announcements)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-bullhorn fa-3x mb-3 opacity-25"></i>
                    <p>Aucune annonce pour le moment.</p>
                </div>
            <?php else: ?>

            <div class="row g-3">
                <?php foreach ($announcements as $ann): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm <?= $ann['pinned'] ? 'border-start border-warning border-3' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h5 class="fw-bold mb-1">
                                        <?php if ($ann['pinned']): ?>
                                            <span class="badge bg-warning text-dark me-2">
                                                <i class="fas fa-thumbtack me-1"></i>Épinglé
                                            </span>
                                        <?php endif; ?>
                                        <!-- Titre non échappé — XSS stored intentionnel -->
                                        <?= $ann['title'] ?>
                                    </h5>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-user-circle me-1"></i>
                                        <?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?>
                                        <span class="text-muted">(<?= htmlspecialchars($ann['department'] ?? '') ?>)</span>
                                        &nbsp;&middot;&nbsp;
                                        <i class="fas fa-clock me-1"></i><?= formatDate($ann['created_at']) ?>
                                    </p>
                                    <!-- Extrait non échappé — XSS stored intentionnel -->
                                    <p class="mb-0 text-secondary small">
                                        <?= mb_substr(strip_tags($ann['content']), 0, 200) ?><?= mb_strlen(strip_tags($ann['content'])) > 200 ? '…' : '' ?>
                                    </p>
                                </div>
                                <a href="/announcement-view.php?id=<?= $ann['id'] ?>"
                                   class="btn btn-outline-primary btn-sm ms-3 flex-shrink-0">
                                    Lire <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">‹</a>
                        </li>
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">›</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
