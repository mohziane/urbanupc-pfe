<?php
// =============================================================
//  Création d'un nouveau document texte
//  VULNÉRABILITÉ : pas de CSRF token
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user  = currentUser();
$pdo   = getDB();
$error = '';

// Restriction : seuls admin et manager peuvent créer des documents
if ($user['role'] === 'user') {
    header('Location: /documents.php?error=forbidden');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de token CSRF (intentionnel)
    $title          = trim($_POST['title'] ?? '');
    $classification = $_POST['classification'] ?? 'internal';
    $category       = trim($_POST['category'] ?? '');
    $content        = $_POST['content'] ?? '';
    $visibility     = $_POST['visibility'] ?? 'all';

    if ($title === '') {
        $error = 'Le titre est obligatoire.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO documents (owner_id, title, content, classification, category, visibility)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user['id'], $title, $content, $classification, $category, $visibility]);
        $newId = $pdo->lastInsertId();
        logAudit($user['id'], $user['username'], 'CREATE_DOCUMENT', '/document-new.php', 'success', "doc_id=$newId");
        header("Location: /document-view.php?id=$newId");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau document — UrbanUpC | Université Paris Cité</title>
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
                    <li class="breadcrumb-item"><a href="/documents.php" class="text-decoration-none">Documents</a></li>
                    <li class="breadcrumb-item active">Nouveau document</li>
                </ol>
            </nav>

            <div class="row justify-content-center">
                <div class="col-lg-9">

                    <h4 class="fw-bold mb-4">
                        <i class="fas fa-plus-circle text-primary me-2"></i>Nouveau document
                    </h4>

                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST">
                                <!-- Pas de token CSRF (intentionnel) -->

                                <div class="row g-3 mb-4">
                                    <div class="col-12">
                                        <label for="title" class="form-label fw-semibold">
                                            Titre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               id="title"
                                               name="title"
                                               class="form-control form-control-lg"
                                               placeholder="Titre du document..."
                                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Classification</label>
                                        <select name="classification" class="form-select">
                                            <option value="public" <?= (($_POST['classification'] ?? '') === 'public') ? 'selected' : '' ?>>
                                                Public
                                            </option>
                                            <option value="internal" <?= (($_POST['classification'] ?? 'internal') === 'internal') ? 'selected' : '' ?>>
                                                Interne
                                            </option>
                                            <option value="confidential" <?= (($_POST['classification'] ?? '') === 'confidential') ? 'selected' : '' ?>>
                                                Confidentiel
                                            </option>
                                            <option value="secret" <?= (($_POST['classification'] ?? '') === 'secret') ? 'selected' : '' ?>>
                                                Secret
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Catégorie</label>
                                        <input type="text"
                                               name="category"
                                               class="form-control"
                                               placeholder="ex: RH, Finance, Projet Phoenix..."
                                               value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
                                    </div>
                                    <?php $vis = $_POST['visibility'] ?? 'all'; ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Visibilité</label>
                                        <select name="visibility" class="form-select">
                                            <option value="all"     <?= $vis === 'all'     ? 'selected' : '' ?>>Tous les utilisateurs</option>
                                            <option value="manager" <?= $vis === 'manager' ? 'selected' : '' ?>>Professeurs / Managers</option>
                                            <option value="user"    <?= $vis === 'user'    ? 'selected' : '' ?>>Étudiants</option>
                                            <option value="admin"   <?= $vis === 'admin'   ? 'selected' : '' ?>>Administrateurs uniquement</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="content" class="form-label fw-semibold">Contenu du document</label>
                                    <textarea id="content"
                                              name="content"
                                              class="form-control"
                                              rows="14"
                                              placeholder="Rédigez le contenu de votre document..."
                                              style="font-family: inherit; font-size: .9rem"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Créer le document
                                    </button>
                                    <a href="/documents.php" class="btn btn-outline-secondary">Annuler</a>
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
