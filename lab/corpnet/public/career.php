<?php
// =============================================================
//  Career Center — Offres de stage & candidatures
//  VULNÉRABILITÉS intentionnelles :
//   - IDOR : view_cv.php?app_id=N sans contrôle d'autorisation
//   - Pas de token CSRF
//   - Upload de CV sans validation d'extension
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$success = '';
$error   = '';

// Créer le répertoire CVs si nécessaire
$cvDir = UPLOAD_DIR . 'cvs/';
if (!is_dir($cvDir)) {
    mkdir($cvDir, 0755, true);
}

// ── POST : publier une offre (tous les rôles) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_internship') {
    $title       = trim($_POST['title'] ?? '');
    $company     = trim($_POST['company'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Sécurité — statut déterminé STRICTEMENT depuis le rôle en session.
    // $_POST['status'] est délibérément ignoré (protection mass assignment).
    $status = in_array($user['role'], ['admin', 'manager']) ? 'approved' : 'pending';

    if (!$title || !$company) {
        $error = 'Le titre et l\'entreprise sont obligatoires.';
    } else {
        $pdo->prepare(
            "INSERT INTO internships (title, description, company, status) VALUES (?, ?, ?, ?)"
        )->execute([$title, $description, $company, $status]);
        logAudit($user['id'], $user['username'], 'CREATE_INTERNSHIP', '/career.php', 'success',
            "title=$title company=$company status=$status");
        header($status === 'pending' ? 'Location: /career.php?submitted=1' : 'Location: /career.php?created=1');
        exit;
    }
}

// ── POST : postuler à une offre ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    $internshipId = (int)($_POST['internship_id'] ?? 0);
    $cvFile       = $_FILES['cv_file'] ?? null;

    if (!$internshipId || !$cvFile || $cvFile['error'] !== UPLOAD_ERR_OK) {
        $error = 'Offre invalide ou fichier manquant.';
    } else {
        $existing = $pdo->prepare("SELECT id FROM applications WHERE internship_id = ? AND student_id = ?");
        $existing->execute([$internshipId, $user['id']]);
        if ($existing->fetch()) {
            $error = 'Vous avez déjà postulé à cette offre.';
        } else {
            // Upload du CV — pas de validation d'extension (intentionnel)
            $originalName = $cvFile['name'];
            $storedName   = 'cv_u' . $user['id'] . '_i' . $internshipId . '_' . $originalName;
            $destPath     = $cvDir . $storedName;

            if (move_uploaded_file($cvFile['tmp_name'], $destPath)) {
                $pdo->prepare(
                    "INSERT INTO applications (internship_id, student_id, cv_file_path) VALUES (?, ?, ?)"
                )->execute([$internshipId, $user['id'], $storedName]);
                logAudit($user['id'], $user['username'], 'APPLY_INTERNSHIP', '/career.php', 'success', "internship_id=$internshipId file=$originalName");
                header('Location: /career.php?applied=1');
                exit;
            } else {
                $error = 'Impossible de déplacer le fichier CV.';
            }
        }
    }
}

// ── POST : approuver une offre (admin/manager uniquement) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_internship') {
    if (!in_array($user['role'], ['admin', 'manager'])) {
        http_response_code(403); exit('Accès refusé.');
    }
    $internshipId = (int)($_POST['internship_id'] ?? 0);
    if ($internshipId) {
        $pdo->prepare("UPDATE internships SET status = 'approved' WHERE id = ? AND status = 'pending'")
            ->execute([$internshipId]);
        logAudit($user['id'], $user['username'], 'APPROVE_INTERNSHIP', '/career.php', 'success', "id=$internshipId");
    }
    header('Location: /career.php?approved=1');
    exit;
}

// ── POST : rejeter une offre (admin/manager uniquement) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_internship') {
    if (!in_array($user['role'], ['admin', 'manager'])) {
        http_response_code(403); exit('Accès refusé.');
    }
    $internshipId = (int)($_POST['internship_id'] ?? 0);
    if ($internshipId) {
        $pdo->prepare("DELETE FROM internships WHERE id = ? AND status = 'pending'")
            ->execute([$internshipId]);
        logAudit($user['id'], $user['username'], 'REJECT_INTERNSHIP', '/career.php', 'success', "id=$internshipId");
    }
    header('Location: /career.php?rejected=1');
    exit;
}

// ── GET : liste des offres approuvées uniquement ───────────────────────────
$internships = $pdo->query(
    "SELECT i.*, COUNT(a.id) AS applicant_count
     FROM internships i
     LEFT JOIN applications a ON a.internship_id = i.id
     WHERE i.status = 'approved'
     GROUP BY i.id
     ORDER BY i.created_at DESC"
)->fetchAll();

// Admin/Manager : offres en attente de validation
$pendingInternships = [];
if (in_array($user['role'], ['admin', 'manager'])) {
    $pendingInternships = $pdo->query(
        "SELECT * FROM internships WHERE status = 'pending' ORDER BY created_at ASC"
    )->fetchAll();
}

// Admin/Manager : toutes les candidatures
$allApplications = [];
if (in_array($user['role'], ['admin', 'manager'])) {
    $allApplications = $pdo->query(
        "SELECT a.*, i.title AS internship_title, i.company,
                u.first_name, u.last_name, u.username
         FROM applications a
         JOIN internships i ON a.internship_id = i.id
         JOIN users u ON a.student_id = u.id
         ORDER BY a.created_at DESC"
    )->fetchAll();
}

// Étudiant : ses propres candidatures
$myApplications = [];
$appliedIds = [];
if (!in_array($user['role'], ['admin', 'manager'])) {
    $stmt = $pdo->prepare(
        "SELECT a.*, i.title AS internship_title, i.company
         FROM applications a
         JOIN internships i ON a.internship_id = i.id
         WHERE a.student_id = ?
         ORDER BY a.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $myApplications = $stmt->fetchAll();
    $appliedIds = array_column($myApplications, 'internship_id');
}

logAudit($user['id'], $user['username'], 'VIEW_CAREER', '/career.php', 'success');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Center — UrbanUpC | Université Paris Cité</title>
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
                <div>
                    <h4 class="fw-bold mb-0">
                        <i class="fas fa-briefcase text-warning me-2"></i>Career Center
                    </h4>
                    <small class="text-muted">Offres de stage — Université Paris Cité</small>
                </div>
                <button class="btn btn-warning btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#postModal">
                    <i class="fas fa-plus me-1"></i>Proposer une offre
                </button>
            </div>

            <?php if (isset($_GET['applied'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-check-circle me-2"></i>Votre candidature a été envoyée avec succès.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-briefcase me-2"></i>Offre publiée avec succès.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['submitted'])): ?>
            <div class="alert alert-info alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-clock me-2"></i>Votre offre a été soumise et est en attente de validation par un responsable.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['approved'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-check-circle me-2"></i>Offre approuvée et publiée.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['rejected'])): ?>
            <div class="alert alert-warning alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-times-circle me-2"></i>Offre rejetée et supprimée.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Section modération — Offres en attente (admin/manager uniquement) -->
            <?php if (in_array($user['role'], ['admin', 'manager']) && !empty($pendingInternships)): ?>
            <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-header bg-warning-subtle border-0 d-flex justify-content-between align-items-center py-3">
                    <h6 class="fw-semibold mb-0">
                        <i class="fas fa-clock text-warning me-2"></i>Offres en attente de validation
                    </h6>
                    <span class="badge bg-warning text-dark"><?= count($pendingInternships) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Offre</th>
                                    <th>Entreprise</th>
                                    <th>Soumis le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingInternships as $pending): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold small"><?= htmlspecialchars($pending['title']) ?></div>
                                        <?php if ($pending['description']): ?>
                                        <div class="text-muted" style="font-size:.75rem">
                                            <?= htmlspecialchars(mb_substr($pending['description'], 0, 90)) . (mb_strlen($pending['description']) > 90 ? '…' : '') ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($pending['company'] ?? '') ?></td>
                                    <td class="small text-muted"><?= formatDate($pending['created_at'], 'd/m/Y H:i') ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="approve_internship">
                                                <input type="hidden" name="internship_id" value="<?= (int)$pending['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check me-1"></i>Approuver
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Rejeter et supprimer cette offre ?')">
                                                <input type="hidden" name="action" value="reject_internship">
                                                <input type="hidden" name="internship_id" value="<?= (int)$pending['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-times me-1"></i>Rejeter
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Grille des offres -->
            <div class="row g-3 mb-4">
                <?php if (empty($internships)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5 text-muted">
                            <i class="fas fa-briefcase fa-3x mb-3 d-block opacity-25"></i>
                            <p>Aucune offre disponible pour l'instant.</p>
                            <button class="btn btn-warning btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#postModal">
                                <i class="fas fa-plus me-1"></i>Proposer la première offre
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($internships as $internship): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start mb-3">
                                <div class="stat-icon bg-warning bg-opacity-10 rounded-3 me-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:42px;height:42px;">
                                    <i class="fas fa-briefcase text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold mb-0"><?= htmlspecialchars($internship['title']) ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($internship['company']) ?>
                                    </small>
                                </div>
                            </div>
                            <?php if ($internship['description']): ?>
                            <p class="small text-muted flex-grow-1 mb-3">
                                <?= htmlspecialchars(mb_substr($internship['description'], 0, 150)) . (mb_strlen($internship['description']) > 150 ? '…' : '') ?>
                            </p>
                            <?php else: ?>
                            <div class="flex-grow-1"></div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i><?= $internship['applicant_count'] ?> candidat(s)
                                </small>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <?php if (in_array($internship['id'], $appliedIds)): ?>
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="fas fa-check me-1"></i>Postulé
                                    </span>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-warning text-dark"
                                            data-bs-toggle="modal"
                                            data-bs-target="#applyModal<?= $internship['id'] ?>">
                                        <i class="fas fa-paper-plane me-1"></i>Postuler
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Tableau de candidatures -->
            <?php if (in_array($user['role'], ['admin', 'manager']) && !empty($allApplications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                    <h6 class="fw-semibold mb-0">
                        <i class="fas fa-folder-open text-danger me-2"></i>Toutes les candidatures
                    </h6>
                    <span class="badge bg-secondary"><?= count($allApplications) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Étudiant</th>
                                    <th>Offre</th>
                                    <th>Entreprise</th>
                                    <th>Date</th>
                                    <th>CV</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allApplications as $app): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $app['id'] ?></td>
                                    <td class="fw-semibold small">
                                        <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>
                                        <div class="text-muted fw-normal" style="font-size:.72rem">@<?= htmlspecialchars($app['username']) ?></div>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($app['internship_title']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($app['company']) ?></td>
                                    <td class="small text-muted"><?= formatDate($app['created_at'], 'd/m/Y') ?></td>
                                    <td>
                                        <!-- VULNERABILITY: IDOR — lien avec app_id séquentiel, accessible par tout utilisateur connecté -->
                                        <a href="/view_cv.php?app_id=<?= $app['id'] ?>"
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i>CV
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php elseif (!empty($myApplications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="fw-semibold mb-0">
                        <i class="fas fa-paper-plane text-primary me-2"></i>Mes candidatures
                    </h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($myApplications as $app): ?>
                        <li class="list-group-item border-0 py-3 px-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($app['internship_title']) ?></div>
                                    <small class="text-muted">
                                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($app['company']) ?>
                                        &middot; <?= formatDate($app['created_at'], 'd/m/Y') ?>
                                    </small>
                                </div>
                                <span class="badge bg-success-subtle text-success">
                                    <i class="fas fa-check me-1"></i>Envoyée
                                </span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Modals : postuler -->
<?php foreach ($internships as $internship): ?>
<?php if (!in_array($internship['id'], $appliedIds) && $user['role'] !== 'admin'): ?>
<div class="modal fade" id="applyModal<?= $internship['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">
                    <i class="fas fa-paper-plane text-warning me-2"></i>
                    Postuler — <?= htmlspecialchars($internship['title']) ?>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <!-- Pas de CSRF token (intentionnel) -->
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="internship_id" value="<?= $internship['id'] ?>">
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3">
                        <div class="stat-icon bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center"
                             style="width:36px;height:36px;">
                            <i class="fas fa-briefcase text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($internship['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($internship['company']) ?></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">
                            VOTRE CV <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="cv_file" class="form-control" required>
                        <div class="form-text">PDF recommandé. Max 5 Mo.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark">
                        <i class="fas fa-paper-plane me-1"></i>Envoyer ma candidature
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<!-- Modal : proposer une offre (tous les rôles) -->
<div class="modal fade" id="postModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">
                    <i class="fas fa-briefcase text-warning me-2"></i>Publier une offre de stage
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="post_internship">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">
                            INTITULÉ DU POSTE <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control"
                               placeholder="ex: Stage Développeur Full Stack — 6 mois" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">
                            ENTREPRISE <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="company" class="form-control"
                               placeholder="ex: Orange Cyberdéfense, Thales, ANSSI…" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">DESCRIPTION</label>
                        <textarea name="description" class="form-control" rows="4"
                                  placeholder="Missions, compétences requises, durée, rémunération…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark">
                        <i class="fas fa-plus me-1"></i>
                        <?= in_array($user['role'], ['admin', 'manager']) ? 'Publier l\'offre' : 'Soumettre pour validation' ?>
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
