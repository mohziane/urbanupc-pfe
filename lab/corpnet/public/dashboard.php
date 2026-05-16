<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();

// Stats rapides
$stats  = [];
$visDoc = visibilityClause($user['role']);
$stats['docs_total']  = $pdo->query("SELECT COUNT(*) FROM documents WHERE $visDoc")->fetchColumn();
$stats['docs_mine']   = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE owner_id = ?");
$stats['docs_mine']->execute([$user['id']]);
$stats['docs_mine']   = $stats['docs_mine']->fetchColumn();
$stats['users_total'] = $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
$stats['logs_today']  = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Dernières activités (scope selon le rôle)
if (in_array($user['role'], ['admin', 'manager'])) {
    $recentLogs = $pdo->query(
        "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8"
    )->fetchAll();
    $logScope = 'global';
} else {
    $stmt3 = $pdo->prepare(
        "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 8"
    );
    $stmt3->execute([$user['id']]);
    $recentLogs = $stmt3->fetchAll();
    $logScope = 'personal';
}

// Annonces (filtrées par visibilité)
$visAnn        = visibilityClause($user['role'], 'a');
$announcements = $pdo->query(
    "SELECT a.*, u.first_name, u.last_name FROM announcements a
     JOIN users u ON a.author_id = u.id
     WHERE $visAnn
     ORDER BY a.pinned DESC, a.created_at DESC LIMIT 4"
)->fetchAll();

// Mes derniers documents
$myDocs = $pdo->prepare(
    "SELECT * FROM documents WHERE owner_id = ? ORDER BY updated_at DESC LIMIT 5"
);
$myDocs->execute([$user['id']]);
$myDocs = $myDocs->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_DASHBOARD', '/dashboard.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — UrbanUpC | Université Paris Cité</title>
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

            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0">
                        Bonjour,
                        <!-- XSS stocké possible si first_name contient du HTML -->
                        <?= $_SESSION['first_name'] ?> <?= $_SESSION['last_name'] ?>
                    </h4>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?= date('l d F Y, H:i') ?>
                        &nbsp;|&nbsp;
                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($user['department']) ?>
                    </small>
                </div>
                <span class="badge bg-dark fs-6 px-3">
                    <?= roleBadge($user['role']) ?>
                </span>
            </div>

            <!-- Actions rapides -->
            <div class="row g-2 mb-4">
                <div class="col-auto">
                    <a href="/document-new.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Nouveau document
                    </a>
                </div>
                <?php if (in_array($user['role'], ['manager', 'admin'])): ?>
                <div class="col-auto">
                    <a href="/announcement-form.php" class="btn btn-warning btn-sm text-dark">
                        <i class="fas fa-bullhorn me-1"></i>Nouvelle annonce
                    </a>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <a href="/upload.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-upload me-1"></i>Déposer un fichier
                    </a>
                </div>
            </div>

            <!-- Widgets de statistiques -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-primary bg-opacity-10 rounded-3 me-3">
                                <i class="fas fa-file-alt text-primary fa-lg"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold"><?= $stats['docs_total'] ?></div>
                                <div class="small text-muted">Documents totaux</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-success bg-opacity-10 rounded-3 me-3">
                                <i class="fas fa-folder-open text-success fa-lg"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold"><?= $stats['docs_mine'] ?></div>
                                <div class="small text-muted">Mes documents</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-info bg-opacity-10 rounded-3 me-3">
                                <i class="fas fa-users text-info fa-lg"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold"><?= $stats['users_total'] ?></div>
                                <div class="small text-muted">Collaborateurs actifs</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-warning bg-opacity-10 rounded-3 me-3">
                                <i class="fas fa-history text-warning fa-lg"></i>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold"><?= $stats['logs_today'] ?></div>
                                <div class="small text-muted">Activités aujourd'hui</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">

                <!-- Annonces -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-bullhorn text-warning me-2"></i>Annonces
                            </h6>
                            <a href="/announcements.php" class="small text-decoration-none">Voir toutes</a>
                        </div>
                        <div class="card-body">
                            <?php foreach ($announcements as $ann): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <a href="/announcement-view.php?id=<?= $ann['id'] ?>" class="text-decoration-none text-dark">
                                        <h6 class="mb-1 fw-semibold">
                                            <?php if ($ann['pinned']): ?>
                                                <i class="fas fa-thumbtack text-danger me-1 small"></i>
                                            <?php endif; ?>
                                            <!-- Contenu non échappé — XSS stocké intentionnel -->
                                            <?= $ann['title'] ?>
                                        </h6>
                                        </a>
                                        <p class="small text-muted mb-1"><?= mb_substr(strip_tags($ann['content']), 0, 120) ?>…</p>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?>
                                    &nbsp;&middot;&nbsp;
                                    <?= formatDate($ann['created_at']) ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Mes derniers documents -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-clock text-primary me-2"></i>Mes documents récents
                            </h6>
                            <a href="/documents.php" class="small text-decoration-none">Voir tout</a>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($myDocs as $doc): ?>
                                <li class="list-group-item border-0 py-3 px-4">
                                    <div class="d-flex align-items-center">
                                        <i class="<?= fileIcon($doc['file_name'] ?? '') ?> me-3 fa-lg"></i>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-semibold text-truncate small">
                                                <?= htmlspecialchars($doc['title']) ?>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <?= classificationBadge($doc['classification']) ?>
                                                <span class="text-muted" style="font-size:.75rem">
                                                    <?= formatDate($doc['updated_at'], 'd/m/Y') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <a href="/document-view.php?id=<?= $doc['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Journal d'activité -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pb-0">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-list-alt text-secondary me-2"></i>
                                <?= $logScope === 'global' ? "Journal d'activité récent" : 'Mes activités récentes' ?>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Utilisateur</th>
                                            <th>Action</th>
                                            <th>Ressource</th>
                                            <th>IP</th>
                                            <th>Statut</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentLogs as $log): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="fw-semibold small">
                                                    <?= htmlspecialchars($log['username'] ?? 'Anonyme') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code class="small"><?= htmlspecialchars($log['action']) ?></code>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($log['resource']) ?></td>
                                            <td><code class="small"><?= htmlspecialchars($log['ip_address']) ?></code></td>
                                            <td>
                                                <?php if ($log['status'] === 'success'): ?>
                                                    <span class="badge bg-success-subtle text-success">OK</span>
                                                <?php elseif ($log['status'] === 'failure'): ?>
                                                    <span class="badge bg-danger-subtle text-danger">ÉCHEC</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning">WARN</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= formatDate($log['created_at']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /row -->
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
