<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();
$id   = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /announcements.php');
    exit;
}

// Suppression
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    logAudit($user['id'], $user['username'], 'DELETE_ANNOUNCEMENT', '/announcement-view.php', 'success', "ann_id=$id");
    header('Location: /announcements.php');
    exit;
}

// IDOR : pas de vérification rôle pour lire
$stmt = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name, u.department, u.role as author_role
     FROM announcements a JOIN users u ON a.author_id = u.id
     WHERE a.id = ?"
);
$stmt->execute([$id]);
$ann = $stmt->fetch();

if (!$ann) {
    header('HTTP/1.1 404 Not Found');
    include __DIR__ . '/errors/404.php';
    exit;
}

logAudit($user['id'], $user['username'], 'VIEW_ANNOUNCEMENT', '/announcement-view.php', 'success', "ann_id=$id");

$canEdit = ($user['id'] == $ann['author_id'] || in_array($user['role'], ['manager', 'admin']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ann['title']) ?> — UrbanUpC | Université Paris Cité</title>
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
                    <li class="breadcrumb-item"><a href="/announcements.php" class="text-decoration-none">Annonces</a></li>
                    <li class="breadcrumb-item active text-truncate" style="max-width:300px">
                        <?= htmlspecialchars($ann['title']) ?>
                    </li>
                </ol>
            </nav>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 p-md-5">

                            <!-- En-tête -->
                            <div class="mb-4">
                                <?php if ($ann['pinned']): ?>
                                <span class="badge bg-warning text-dark mb-2">
                                    <i class="fas fa-thumbtack me-1"></i>Épinglé
                                </span>
                                <?php endif; ?>
                                <!-- Titre non échappé — XSS stored intentionnel -->
                                <h2 class="fw-bold mb-3"><?= $ann['title'] ?></h2>

                                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle">
                                            <?= mb_strtoupper(mb_substr($ann['first_name'], 0, 1) . mb_substr($ann['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold small">
                                                <?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.8rem">
                                                <?= htmlspecialchars($ann['department'] ?? '') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-clock me-1"></i><?= formatDate($ann['created_at']) ?>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Contenu — non échappé — XSS stored intentionnel -->
                            <div class="announcement-content" style="line-height:1.8; font-size:.95rem; white-space:pre-wrap;">
                                <?= $ann['content'] ?>
                            </div>

                            <hr class="mt-4">

                            <!-- Actions -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                                <a href="/announcements.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left me-2"></i>Retour aux annonces
                                </a>
                                <?php if ($canEdit): ?>
                                <div class="d-flex gap-2">
                                    <a href="/announcement-form.php?id=<?= $ann['id'] ?>"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i>Modifier
                                    </a>
                                    <a href="/announcement-view.php?id=<?= $ann['id'] ?>&action=delete"
                                       class="btn btn-outline-danger btn-sm"
                                       onclick="return confirm('Supprimer cette annonce définitivement ?')">
                                        <i class="fas fa-trash me-1"></i>Supprimer
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>

                        </div>
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
