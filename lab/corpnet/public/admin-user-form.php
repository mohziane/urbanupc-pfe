<?php
// =============================================================
//  Création / Édition d'un utilisateur
//  VULNÉRABILITÉS intentionnelles :
//   - IDOR : tout manager peut modifier tout utilisateur (pas de vérif)
//   - Pas de token CSRF
//   - MD5 password à la création et au changement de mot de passe
//  Accès création : réservé admin (UI) — backend requireRole('manager') = IDOR intentionnel
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('manager');

// Création réservée à l'admin (vérification côté backend)
// VULNERABILITY: IDOR — requireRole('manager') permet aux managers de créer des utilisateurs ;
// la restriction "admin uniquement" n'est appliquée qu'en UI (bouton masqué).
// Un manager authentifié peut accéder directement à cette URL et soumettre le formulaire.
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$id      = (int)($_GET['id'] ?? 0);
$target  = null;
$error   = '';
$success = '';

// Charger l'utilisateur cible (mode édition — IDOR : pas de restriction)
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        header('Location: /admin.php');
        exit;
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de CSRF token (intentionnel)
    $mode = $_POST['mode'] ?? 'create';

    if ($mode === 'create') {
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $email      = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $role       = $_POST['role'] ?? 'user';
        $department = trim($_POST['department'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        if (!$username || !$password || !$email) {
            $error = 'Identifiant, mot de passe et email sont obligatoires.';
        } else {
            // Vérifier si username déjà pris
            $existing = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $existing->execute([$username]);
            if ($existing->fetch()) {
                $error = "L'identifiant \"$username\" est déjà utilisé.";
            } else {
                // MD5 password — intentionnellement faible
                $pdo->prepare(
                    "INSERT INTO users (username, password, email, first_name, last_name, role, department, phone, active)
                     VALUES (?, MD5(?), ?, ?, ?, ?, ?, ?, 1)"
                )->execute([$username, $password, $email, $first_name, $last_name, $role, $department, $phone]);
                logAudit($user['id'], $user['username'], 'CREATE_USER', '/admin-user-form.php', 'success', "username=$username");
                header('Location: /admin.php?created=1');
                exit;
            }
        }
    } else {
        // Édition — IDOR : pas de vérification (tout manager édite tout utilisateur)
        $email      = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $role       = $_POST['role'] ?? 'user';
        $department = trim($_POST['department'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $active     = isset($_POST['active']) ? 1 : 0;

        $pdo->prepare(
            "UPDATE users SET email=?, first_name=?, last_name=?, role=?, department=?, phone=?, active=? WHERE id=?"
        )->execute([$email, $first_name, $last_name, $role, $department, $phone, $active, $id]);
        logAudit($user['id'], $user['username'], 'UPDATE_USER', '/admin-user-form.php', 'success', "target_id=$id");

        // Changement de mot de passe optionnel (champ non vide uniquement)
        // VULNERABILITY: IDOR — accessible à tout manager, pas seulement l'admin
        $newpass = $_POST['new_password'] ?? '';
        if ($newpass !== '') {
            // MD5 password — intentionnellement faible
            $pdo->prepare("UPDATE users SET password = MD5(?) WHERE id = ?")->execute([$newpass, $id]);
            logAudit($user['id'], $user['username'], 'CHANGE_PASSWORD', '/admin-user-form.php', 'success', "target_id=$id");
        }

        header('Location: /admin.php?updated=1');
        exit;
    }
}

$isEdit    = (bool)$target;
$pageTitle = $isEdit ? 'Modifier : ' . htmlspecialchars($target['username']) : 'Ajouter un utilisateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — UrbanUpC | Université Paris Cité</title>
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

            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="/dashboard.php" class="text-decoration-none">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="/admin.php" class="text-decoration-none">Administration</a></li>
                    <li class="breadcrumb-item active"><?= $isEdit ? 'Modifier utilisateur' : 'Ajouter utilisateur' ?></li>
                </ol>
            </nav>

            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="d-flex align-items-center mb-4">
                        <?php if ($isEdit): ?>
                        <div class="avatar-circle-lg me-3">
                            <?= mb_strtoupper(mb_substr($target['first_name'], 0, 1) . mb_substr($target['last_name'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-<?= $isEdit ? 'user-edit' : 'user-plus' ?> text-primary me-2"></i><?= $pageTitle ?>
                            </h4>
                            <?php if ($isEdit): ?>
                            <small class="text-muted">ID : <?= $target['id'] ?> &nbsp;·&nbsp; Créé le <?= formatDate($target['created_at'], 'd/m/Y') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="mode" value="<?= $isEdit ? 'edit' : 'create' ?>">

                                <div class="row g-3">

                                    <!-- Identifiant (création seulement) -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">
                                            IDENTIFIANT <?= !$isEdit ? '<span class="text-danger">*</span>' : '' ?>
                                        </label>
                                        <?php if ($isEdit): ?>
                                        <input type="text" class="form-control"
                                               value="<?= htmlspecialchars($target['username']) ?>"
                                               readonly disabled>
                                        <div class="form-text">L'identifiant ne peut pas être modifié.</div>
                                        <?php else: ?>
                                        <input type="text" name="username" class="form-control"
                                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                               placeholder="ex: j.dupont"
                                               required>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Mot de passe (création seulement) -->
                                    <?php if (!$isEdit): ?>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">MOT DE PASSE <span class="text-danger">*</span></label>
                                        <input type="password" name="password" class="form-control"
                                               placeholder="Mot de passe initial"
                                               required>
                                        <div class="form-text text-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Stocké en MD5 (non sécurisé).
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">STATUT DU COMPTE</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" id="active" name="active"
                                                   <?= $target['active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="active">
                                                Compte actif
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Changer le mot de passe (édition) -->
                                    <!-- VULNERABILITY: IDOR — accessible à tout manager, pas seulement l'admin -->
                                    <div class="col-12">
                                        <hr class="my-1">
                                        <label class="form-label small fw-semibold text-muted">
                                            NOUVEAU MOT DE PASSE
                                            <span class="text-muted fw-normal">(laisser vide pour ne pas modifier)</span>
                                        </label>
                                        <input type="password" name="new_password" class="form-control"
                                               placeholder="Laisser vide = inchangé"
                                               autocomplete="new-password">
                                        <div class="form-text text-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Stocké en MD5 (non sécurisé).
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Prénom -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">PRÉNOM</label>
                                        <input type="text" name="first_name" class="form-control"
                                               value="<?= htmlspecialchars($target['first_name'] ?? $_POST['first_name'] ?? '') ?>">
                                    </div>

                                    <!-- Nom -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">NOM</label>
                                        <input type="text" name="last_name" class="form-control"
                                               value="<?= htmlspecialchars($target['last_name'] ?? $_POST['last_name'] ?? '') ?>">
                                    </div>

                                    <!-- Email -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">EMAIL <?= !$isEdit ? '<span class="text-danger">*</span>' : '' ?></label>
                                        <input type="email" name="email" class="form-control"
                                               value="<?= htmlspecialchars($target['email'] ?? $_POST['email'] ?? '') ?>"
                                               <?= !$isEdit ? 'required' : '' ?>>
                                    </div>

                                    <!-- Téléphone -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">TÉLÉPHONE</label>
                                        <input type="text" name="phone" class="form-control"
                                               value="<?= htmlspecialchars($target['phone'] ?? $_POST['phone'] ?? '') ?>">
                                    </div>

                                    <!-- Département -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">DÉPARTEMENT</label>
                                        <input type="text" name="department" class="form-control"
                                               value="<?= htmlspecialchars($target['department'] ?? $_POST['department'] ?? '') ?>"
                                               placeholder="ex: Informatique, RH, Finance...">
                                    </div>

                                    <!-- Rôle -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">RÔLE</label>
                                        <select name="role" class="form-select">
                                            <?php
                                            $roles = ['user' => 'Utilisateur', 'manager' => 'Manager', 'admin' => 'Administrateur'];
                                            $currentRole = $target['role'] ?? $_POST['role'] ?? 'user';
                                            foreach ($roles as $rv => $rl):
                                            ?>
                                            <option value="<?= $rv ?>" <?= $currentRole === $rv ? 'selected' : '' ?>><?= $rl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>

                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?= $isEdit ? 'Enregistrer les modifications' : 'Créer l\'utilisateur' ?>
                                    </button>
                                    <a href="/admin.php" class="btn btn-outline-secondary">Annuler</a>
                                </div>
                            </form>
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
