<?php
// =============================================================
//  Gestion des services — CRUD
//  VULNÉRABILITÉS intentionnelles :
//   - IDOR : tout manager peut modifier tout service (pas de vérif created_by)
//   - Pas de token CSRF
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('manager');
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$success = '';
$error   = '';
$editService = null;

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name    = trim($_POST['name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $cat     = $_POST['category'] ?? 'autre';
        $icon    = trim($_POST['icon'] ?? 'fa-cogs');
        $cname   = trim($_POST['contact_name'] ?? '');
        $cemail  = trim($_POST['contact_email'] ?? '');
        $status  = $_POST['status'] ?? 'active';

        if ($name) {
            $pdo->prepare(
                "INSERT INTO services (name,description,category,icon,contact_name,contact_email,status,created_by)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([$name, $desc, $cat, $icon, $cname, $cemail, $status, $user['id']]);
            logAudit($user['id'], $user['username'], 'CREATE_SERVICE', '/admin-services.php', 'success', "name=$name");
            $success = "Service \"$name\" créé avec succès.";
        } else {
            $error = 'Le nom du service est obligatoire.';
        }

    } elseif ($action === 'update') {
        // IDOR : pas de vérification created_by == user['id']
        $sid    = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $cat    = $_POST['category'] ?? 'autre';
        $icon   = trim($_POST['icon'] ?? 'fa-cogs');
        $cname  = trim($_POST['contact_name'] ?? '');
        $cemail = trim($_POST['contact_email'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($name && $sid) {
            $pdo->prepare(
                "UPDATE services SET name=?,description=?,category=?,icon=?,contact_name=?,contact_email=?,status=? WHERE id=?"
            )->execute([$name, $desc, $cat, $icon, $cname, $cemail, $status, $sid]);
            logAudit($user['id'], $user['username'], 'UPDATE_SERVICE', '/admin-services.php', 'success', "id=$sid name=$name");
            $success = "Service mis à jour.";
        }

    } elseif ($action === 'delete') {
        $sid = (int)($_POST['id'] ?? 0);
        if ($sid) {
            $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$sid]);
            logAudit($user['id'], $user['username'], 'DELETE_SERVICE', '/admin-services.php', 'success', "id=$sid");
            $success = 'Service supprimé.';
        }

    } elseif ($action === 'toggle') {
        $sid = (int)($_POST['id'] ?? 0);
        if ($sid) {
            $pdo->prepare(
                "UPDATE services SET status = IF(status='active','maintenance','active') WHERE id = ?"
            )->execute([$sid]);
            logAudit($user['id'], $user['username'], 'TOGGLE_SERVICE', '/admin-services.php', 'success', "id=$sid");
            $success = 'Statut du service modifié.';
        }
    }
}

// Mode édition (GET ?action=edit&id=X)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editService = $stmt->fetch();
}

// Liste des services
$services = $pdo->query("SELECT * FROM services ORDER BY category, name")->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_ADMIN_SERVICES', '/admin-services.php', 'success');

$categories = ['informatique', 'rh', 'finance', 'juridique', 'logistique', 'autre'];
$categoryLabels = [
    'informatique' => 'Informatique',
    'rh'           => 'Ressources Humaines',
    'finance'      => 'Finance',
    'juridique'    => 'Juridique',
    'logistique'   => 'Logistique',
    'autre'        => 'Autre',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des services — UrbanUpC | Université Paris Cité</title>
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
                    <i class="fas fa-cogs text-secondary me-2"></i>Gestion des services
                </h4>
                <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formCreate">
                    <i class="fas fa-plus me-2"></i>Ajouter un service
                </button>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-4">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout (collapse) -->
            <div class="collapse <?= ($error && !$editService) ? 'show' : '' ?> mb-4" id="formCreate">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="fw-semibold mb-0"><i class="fas fa-plus-circle text-primary me-2"></i>Nouveau service</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create">
                            <?php include __DIR__ . '/../includes/service-form-fields.php'; ?>
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-save me-2"></i>Créer le service
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Formulaire d'édition -->
            <?php if ($editService): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-primary border-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between">
                    <h6 class="fw-semibold mb-0"><i class="fas fa-edit text-primary me-2"></i>Modifier : <?= htmlspecialchars($editService['name']) ?></h6>
                    <a href="/admin-services.php" class="btn-close"></a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $editService['id'] ?>">
                        <?php include __DIR__ . '/../includes/service-form-fields.php'; ?>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                            <a href="/admin-services.php" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Table des services -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="fw-semibold mb-0">Tous les services (<?= count($services) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Service</th>
                                    <th>Catégorie</th>
                                    <th>Contact</th>
                                    <th>Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $s): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas <?= htmlspecialchars($s['icon']) ?> text-secondary"></i>
                                            <div>
                                                <div class="fw-semibold small"><?= htmlspecialchars($s['name']) ?></div>
                                                <div class="text-muted" style="font-size:.78rem"><?= htmlspecialchars(mb_substr($s['description'] ?? '', 0, 60)) ?>…</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($categoryLabels[$s['category']] ?? $s['category']) ?></span></td>
                                    <td class="small text-muted"><?= htmlspecialchars($s['contact_name'] ?? '') ?></td>
                                    <td>
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php elseif ($s['status'] === 'maintenance'): ?>
                                            <span class="badge bg-warning text-dark">Maintenance</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Hors ligne</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin-services.php?action=edit&id=<?= $s['id'] ?>"
                                               class="btn btn-outline-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-warning"
                                                        title="<?= $s['status'] === 'active' ? 'Passer en maintenance' : 'Remettre en service' ?>">
                                                    <i class="fas <?= $s['status'] === 'active' ? 'fa-tools' : 'fa-check' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Supprimer ce service définitivement ?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
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

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
