<?php
// =============================================================
//  Recherche employés — VULNÉRABLE : SQLi Union-based + Time-based
//  Requête construite par concaténation directe de $_GET['q']
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$results = [];
$q       = $_GET['q'] ?? '';
$dept    = $_GET['dept'] ?? '';
$error   = '';

// VULNÉRABILITÉ SQLi : concaténation directe sans préparation
// La requête s'exécute toujours — sans filtre elle retourne tous les utilisateurs actifs
$where = "1=1";
if ($q !== '') {
    $where .= " AND (username LIKE '%$q%' OR first_name LIKE '%$q%' OR last_name LIKE '%$q%' OR email LIKE '%$q%' OR skills LIKE '%$q%')";
}
if ($dept !== '') {
    $where .= " AND department = '$dept'";
}

$sql = "SELECT id, username, first_name, last_name, email, role, department, phone, last_login, skills
        FROM users
        WHERE active = 1 AND $where
        ORDER BY last_name, first_name";

try {
    // $pdo->query() utilisé (pas prepare) — SQLi active
    $results = $pdo->query($sql)->fetchAll();
    logAudit($user['id'], $user['username'], 'SEARCH_USERS', '/search.php', 'success', "q=$q dept=$dept");
} catch (PDOException $e) {
    // Affiche l'erreur SQL — fuite de structure BDD intentionnelle
    $error = 'Erreur SQL : ' . $e->getMessage();
}

// Liste des départements pour le filtre
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE active=1 ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annuaire — UrbanUpC | Université Paris Cité</title>
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
                    <i class="fas fa-address-book text-primary me-2"></i>Annuaire des collaborateurs
                </h4>
            </div>

            <!-- Formulaire de recherche -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">RECHERCHE</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                <input type="text"
                                       class="form-control"
                                       name="q"
                                       value="<?= htmlspecialchars($q) ?>"
                                       placeholder="Nom, prénom, identifiant, email...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">DÉPARTEMENT</label>
                            <select name="dept" class="form-select">
                                <option value="">Tous les départements</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>"
                                        <?= $dept === $d ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Rechercher
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Erreur :</strong> <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Résultats -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-semibold mb-0">
                        <?= ($q !== '' || $dept !== '') ? 'Résultats' : 'Annuaire complet' ?>
                        <span class="badge bg-primary ms-2"><?= count($results) ?></span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($results)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
                            <p>Aucun collaborateur trouvé.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Collaborateur</th>
                                    <th>Identifiant</th>
                                    <th>Email</th>
                                    <th>Département</th>
                                    <th>Rôle</th>
                                    <th>Téléphone</th>
                                    <th>Dernière connexion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $u): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2">
                                                <?= mb_strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold small">
                                                    <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code class="small"><?= htmlspecialchars($u['username']) ?></code></td>
                                    <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="small"><?= htmlspecialchars($u['department'] ?? '') ?></td>
                                    <td>
                                        <?= roleBadge($u['role']) ?>
                                        <?php if (!empty($u['skills'])): ?>
                                        <div class="mt-1 d-flex flex-wrap gap-1">
                                            <?php foreach (array_filter(array_map('trim', explode(',', $u['skills']))) as $skill): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle" style="font-size:.65rem">
                                                <?= htmlspecialchars($skill) ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                                    <td class="small text-muted">
                                        <?= $u['last_login'] ? formatDate($u['last_login']) : 'Jamais' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
