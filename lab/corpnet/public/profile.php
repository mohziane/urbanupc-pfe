<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$success = '';
$error   = '';

// Récupérer les infos complètes
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Génération de clé API ──────────────────────────────────────────────
    if ($action === 'generate_api_key') {
        $newKey = bin2hex(random_bytes(16)); // 32 hex chars
        $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?")
            ->execute([$newKey, $user['id']]);
        logAudit($user['id'], $user['username'], 'GENERATE_API_KEY', '/profile.php', 'success');
        header('Location: /profile.php?key_generated=1');
        exit;
    }

    // ── Mise à jour du profil ──────────────────────────────────────────────
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $dept       = trim($_POST['department'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $skills     = trim($_POST['skills'] ?? '');

    // Pas de re-authentification pour les changements sensibles — CSRF possible (pas de token vérifié)
    $pdo->prepare(
        "UPDATE users SET email = ?, phone = ?, department = ?, first_name = ?, last_name = ?, skills = ? WHERE id = ?"
    )->execute([$email, $phone, $dept, $first_name, $last_name, $skills, $user['id']]);

    // Mettre à jour la session
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name']  = $last_name;

    logAudit($user['id'], $user['username'], 'UPDATE_PROFILE', '/profile.php', 'success');
    $success = 'Profil mis à jour avec succès.';

    // Rechargement du profil
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
}

// Mes dernières activités
$myLogs = $pdo->prepare(
    "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10"
);
$myLogs->execute([$user['id']]);
$myLogs = $myLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil — UrbanUpC | Université Paris Cité</title>
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

            <h4 class="fw-bold mb-4">
                <i class="fas fa-user-circle text-primary me-2"></i>Mon profil
            </h4>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-5">
                            <div class="avatar-circle-lg mx-auto mb-3">
                                <?= mb_strtoupper(mb_substr($profile['first_name'],0,1) . mb_substr($profile['last_name'],0,1)) ?>
                            </div>
                            <!-- Nom affiché sans échappement — XSS si modifiable -->
                            <h5 class="fw-bold mb-0"><?= $profile['first_name'] ?> <?= $profile['last_name'] ?></h5>
                            <p class="text-muted small mb-2">@<?= htmlspecialchars($profile['username']) ?></p>
                            <?= roleBadge($profile['role']) ?>
                            <hr class="my-3">
                            <p class="small text-muted mb-1">
                                <i class="fas fa-building me-2"></i><?= htmlspecialchars($profile['department'] ?? '') ?>
                            </p>
                            <p class="small text-muted mb-1">
                                <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($profile['email']) ?>
                            </p>
                            <p class="small text-muted mb-0">
                                <i class="fas fa-phone me-2"></i><?= htmlspecialchars($profile['phone'] ?? '') ?>
                            </p>
                            <?php if (!empty($profile['skills'])): ?>
                            <div class="mt-3 d-flex flex-wrap gap-1 justify-content-center">
                                <?php foreach (array_filter(array_map('trim', explode(',', $profile['skills']))) as $skill): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle" style="font-size:.72rem">
                                    <?= htmlspecialchars($skill) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">Modifier mes informations</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success py-2">
                                    <i class="fas fa-check me-2"></i><?= $success ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <!-- Pas de token CSRF — CSRF possible -->
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">PRÉNOM</label>
                                        <input type="text" name="first_name" class="form-control"
                                               value="<?= htmlspecialchars($profile['first_name']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">NOM</label>
                                        <input type="text" name="last_name" class="form-control"
                                               value="<?= htmlspecialchars($profile['last_name']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">EMAIL</label>
                                        <input type="email" name="email" class="form-control"
                                               value="<?= htmlspecialchars($profile['email']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">TÉLÉPHONE</label>
                                        <input type="text" name="phone" class="form-control"
                                               value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">DÉPARTEMENT</label>
                                        <input type="text" name="department" class="form-control"
                                               value="<?= htmlspecialchars($profile['department'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">COMPÉTENCES</label>
                                        <input type="text" name="skills" class="form-control"
                                               value="<?= htmlspecialchars($profile['skills'] ?? '') ?>"
                                               placeholder="#Python, #RedTeam, #Reverse, #OSINT...">
                                        <div class="form-text">Séparez par des virgules. Ex : #Python, #BlueTeam, #OSINT</div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Clé API développeur -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-code text-info me-2"></i>Clé API développeur
                            </h6>
                            <?php if (isset($_GET['key_generated'])): ?>
                            <span class="badge bg-success-subtle text-success small">
                                <i class="fas fa-check me-1"></i>Clé générée
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($profile['api_key'])): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">VOTRE CLÉ API</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-key text-warning"></i>
                                    </span>
                                    <input type="text" class="form-control font-monospace small bg-light"
                                           value="<?= htmlspecialchars($profile['api_key']) ?>"
                                           readonly id="apiKeyInput">
                                    <button class="btn btn-outline-secondary btn-sm"
                                            type="button"
                                            onclick="navigator.clipboard.writeText(document.getElementById('apiKeyInput').value)"
                                            title="Copier">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-terminal me-1"></i>
                                <strong>Exemple d'utilisation :</strong><br>
                                <code>GET /api/v1/my_grades.php?api_key=<?= htmlspecialchars(substr($profile['api_key'], 0, 8)) ?>…&amp;student_id=<?= $user['id'] ?></code>
                            </div>
                            <?php else: ?>
                            <p class="small text-muted mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Aucune clé API générée. Générez-en une pour accéder à l'API de notes programmatiquement.
                            </p>
                            <?php endif; ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="generate_api_key">
                                <button type="submit" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    <?= empty($profile['api_key']) ? 'Générer une clé API' : 'Régénérer la clé' ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Historique d'activité personnelle -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">Mes dernières activités</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Action</th>
                                        <th>Ressource</th>
                                        <th>IP</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myLogs as $log): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <code class="small"><?= htmlspecialchars($log['action']) ?></code>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($log['resource']) ?></td>
                                        <td><code class="small"><?= htmlspecialchars($log['ip_address']) ?></code></td>
                                        <td class="small text-muted"><?= formatDate($log['created_at']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
