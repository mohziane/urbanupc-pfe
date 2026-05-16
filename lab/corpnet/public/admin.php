<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('manager'); // managers et admins uniquement
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();

// Activer/désactiver un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'toggle') {
    $tid = (int)$_GET['id'];
    $pdo->prepare("UPDATE users SET active = NOT active WHERE id = ?")->execute([$tid]);
    logAudit($user['id'], $user['username'], 'TOGGLE_USER', '/admin.php', 'success', "target_id=$tid");
    header('Location: /admin.php');
    exit;
}

// Changer le mot de passe d'un utilisateur
// VULNERABILITY: IDOR — seul requireRole('manager') est vérifié ; un manager peut changer
// le mot de passe de n'importe quel utilisateur, y compris un admin, sans contrôle supplémentaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'change_password') {
    $tid     = (int)$_GET['id'];
    $newpass = $_POST['new_password'] ?? '';
    if ($tid && $newpass !== '') {
        // MD5 password — intentionnellement faible (cohérence avec le système legacy)
        $pdo->prepare("UPDATE users SET password = MD5(?) WHERE id = ?")->execute([$newpass, $tid]);
        logAudit($user['id'], $user['username'], 'CHANGE_PASSWORD', '/admin.php', 'success', "target_id=$tid");
    }
    header('Location: /admin.php?pwd_changed=1');
    exit;
}

$users = $pdo->query(
    "SELECT u.*, COUNT(d.id) as doc_count
     FROM users u LEFT JOIN documents d ON u.id = d.owner_id
     GROUP BY u.id ORDER BY u.role DESC, u.last_name"
)->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_ADMIN_USERS', '/admin.php', 'success');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — UrbanUpC | Université Paris Cité</title>
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
                    <i class="fas fa-users-cog text-danger me-2"></i>Administration — Utilisateurs
                </h4>
                <span class="badge bg-danger">
                    <i class="fas fa-shield-alt me-1"></i>Accès restreint
                </span>
            </div>

            <?php if (isset($_GET['pwd_changed'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3" role="alert">
                <i class="fas fa-check-circle me-2"></i>Mot de passe mis à jour avec succès.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3" role="alert">
                <i class="fas fa-user-plus me-2"></i>Utilisateur créé avec succès.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                    <h6 class="fw-semibold mb-0">Tous les collaborateurs (<?= count($users) ?>)</h6>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin-user-form.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-plus me-2"></i>Ajouter un utilisateur
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Identifiant</th>
                                    <th>Nom complet</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Département</th>
                                    <th>Documents</th>
                                    <th>Dernière connexion</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $u['id'] ?></td>
                                    <td>
                                        <a href="/admin-user-form.php?id=<?= $u['id'] ?>" class="text-decoration-none">
                                            <code class="small"><?= htmlspecialchars($u['username']) ?></code>
                                        </a>
                                    </td>
                                    <td class="fw-semibold small">
                                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= roleBadge($u['role']) ?></td>
                                    <td class="small"><?= htmlspecialchars($u['department'] ?? '') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= $u['doc_count'] ?></span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= $u['last_login'] ? formatDate($u['last_login']) : 'Jamais' ?>
                                    </td>
                                    <td>
                                        <?php if ($u['active']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin-user-form.php?id=<?= $u['id'] ?>"
                                               class="btn btn-outline-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Bouton "Changer le mot de passe" — visible à tous les managers (IDOR intentionnel) -->
                                            <button type="button"
                                                    class="btn btn-outline-danger"
                                                    title="Changer le mot de passe"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#pwdModal<?= $u['id'] ?>">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <form method="POST" action="/admin.php?action=toggle&id=<?= $u['id'] ?>" class="d-inline">
                                                <button type="submit"
                                                        class="btn btn-sm <?= $u['active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                                        title="<?= $u['active'] ? 'Désactiver' : 'Activer' ?>">
                                                    <i class="fas <?= $u['active'] ? 'fa-ban' : 'fa-check' ?>"></i>
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

<!-- Modals "Changer le mot de passe" — un modal par utilisateur -->
<?php foreach ($users as $u): ?>
<div class="modal fade" id="pwdModal<?= $u['id'] ?>" tabindex="-1" aria-labelledby="pwdModalLabel<?= $u['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="pwdModalLabel<?= $u['id'] ?>">
                    <i class="fas fa-key text-danger me-2"></i>Changer le mot de passe
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin.php?action=change_password&id=<?= $u['id'] ?>">
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Utilisateur : <strong><?= htmlspecialchars($u['username']) ?></strong>
                        (<?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>)
                    </p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">NOUVEAU MOT DE PASSE</label>
                        <input type="password" name="new_password" class="form-control form-control-sm"
                               placeholder="Nouveau mot de passe" required autocomplete="new-password">
                    </div>
                    <div class="form-text text-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>Stocké en MD5 (non sécurisé).
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-save me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
