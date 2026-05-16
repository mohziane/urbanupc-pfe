<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('manager');
header('Content-Type: text/html; charset=utf-8');

$user  = currentUser();
$pdo   = getDB();
$id    = (int)($_GET['id'] ?? 0);
$ann   = null;
$error = '';

// Charger l'annonce existante (mode édition)
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $ann = $stmt->fetch();
    if (!$ann) {
        header('Location: /announcements.php');
        exit;
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de token CSRF — CSRF possible (intentionnel)
    $title      = trim($_POST['title'] ?? '');
    $content    = $_POST['content'] ?? '';
    $pinned     = isset($_POST['pinned']) ? 1 : 0;
    $visibility = $_POST['visibility'] ?? 'all';

    if ($title === '') {
        $error = 'Le titre est obligatoire.';
    } else {
        if ($id) {
            // Mise à jour — IDOR : pas de vérification author_id == user
            $pdo->prepare(
                "UPDATE announcements SET title = ?, content = ?, pinned = ?, visibility = ? WHERE id = ?"
            )->execute([$title, $content, $pinned, $visibility, $id]);
            logAudit($user['id'], $user['username'], 'UPDATE_ANNOUNCEMENT', '/announcement-form.php', 'success', "ann_id=$id");
        } else {
            // Création — contenu non sanitisé : XSS stored intentionnel
            $stmt = $pdo->prepare(
                "INSERT INTO announcements (author_id, title, content, pinned, visibility) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user['id'], $title, $content, $pinned, $visibility]);
            $id = $pdo->lastInsertId();
            logAudit($user['id'], $user['username'], 'CREATE_ANNOUNCEMENT', '/announcement-form.php', 'success', "ann_id=$id");
        }
        header("Location: /announcement-view.php?id=$id");
        exit;
    }
}

$isEdit   = (bool)$ann;
$pageTitle = $isEdit ? 'Modifier l\'annonce' : 'Nouvelle annonce';
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

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="/dashboard.php" class="text-decoration-none">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="/announcements.php" class="text-decoration-none">Annonces</a></li>
                    <li class="breadcrumb-item active"><?= $pageTitle ?></li>
                </ol>
            </nav>

            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0">
                            <i class="fas fa-<?= $isEdit ? 'edit' : 'plus-circle' ?> text-warning me-2"></i><?= $pageTitle ?>
                        </h4>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST">
                                <!-- Pas de token CSRF — CSRF possible (intentionnel) -->

                                <div class="mb-4">
                                    <label for="title" class="form-label fw-semibold">
                                        Titre <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                           id="title"
                                           name="title"
                                           class="form-control form-control-lg"
                                           placeholder="Titre de l'annonce..."
                                           value="<?= htmlspecialchars($ann['title'] ?? '') ?>"
                                           required>
                                </div>

                                <div class="mb-4">
                                    <label for="content" class="form-label fw-semibold">Contenu</label>
                                    <textarea id="content"
                                              name="content"
                                              class="form-control"
                                              rows="10"
                                              placeholder="Rédigez votre annonce ici..."><?= htmlspecialchars($ann['content'] ?? '') ?></textarea>
                                    <div class="form-text">Le contenu sera affiché tel quel (HTML supporté).</div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="pinned"
                                               name="pinned"
                                               <?= (!empty($ann['pinned'])) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="pinned">
                                            <i class="fas fa-thumbtack text-warning me-1"></i>Épingler cette annonce
                                            <div class="small text-muted fw-normal">Les annonces épinglées apparaissent en haut de la liste.</div>
                                        </label>
                                    </div>
                                </div>

                                <?php $vis = $ann['visibility'] ?? ($_POST['visibility'] ?? 'all'); ?>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Visibilité</label>
                                    <select name="visibility" class="form-select">
                                        <option value="all"     <?= $vis === 'all'     ? 'selected' : '' ?>>Tous les utilisateurs</option>
                                        <option value="manager" <?= $vis === 'manager' ? 'selected' : '' ?>>Professeurs / Managers</option>
                                        <option value="user"    <?= $vis === 'user'    ? 'selected' : '' ?>>Étudiants</option>
                                        <option value="admin"   <?= $vis === 'admin'   ? 'selected' : '' ?>>Administrateurs uniquement</option>
                                    </select>
                                    <div class="form-text">
                                        <i class="fas fa-eye me-1 text-muted"></i>
                                        Détermine quels utilisateurs voient cette annonce dans leur tableau de bord.
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?= $isEdit ? 'Enregistrer les modifications' : 'Publier l\'annonce' ?>
                                    </button>
                                    <a href="<?= $isEdit ? '/announcement-view.php?id=' . $id : '/announcements.php' ?>"
                                       class="btn btn-outline-secondary">
                                        Annuler
                                    </a>
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
