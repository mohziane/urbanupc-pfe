<?php
// =============================================================
//  Édition d'un document existant
//  VULNÉRABILITÉS intentionnelles :
//   - IDOR : pas de vérification owner_id == user['id']
//   - Pas de token CSRF
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user  = currentUser();
$pdo   = getDB();

// Restriction : seuls admin et manager peuvent modifier des documents
// Note : l'IDOR (pas de vérification owner_id) est préservé intentionnellement
if ($user['role'] === 'user') {
    header('Location: /documents.php?error=forbidden');
    exit;
}

$id    = (int)($_GET['id'] ?? 0);
$error = '';

if (!$id) {
    header('Location: /documents.php');
    exit;
}

// IDOR : pas de vérification owner_id
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    header('HTTP/1.1 404 Not Found');
    include __DIR__ . '/errors/404.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de token CSRF (intentionnel)
    $title          = trim($_POST['title'] ?? '');
    $classification = $_POST['classification'] ?? 'internal';
    $category       = trim($_POST['category'] ?? '');
    $content        = $_POST['content'] ?? '';
    $visibility     = $_POST['visibility'] ?? ($doc['visibility'] ?? 'all');

    if ($title === '') {
        $error = 'Le titre est obligatoire.';
    } else {
        // IDOR : UPDATE sans vérifier owner_id
        $pdo->prepare(
            "UPDATE documents SET title=?, classification=?, category=?, content=?, visibility=?, updated_at=NOW() WHERE id=?"
        )->execute([$title, $classification, $category, $content, $visibility, $id]);
        logAudit($user['id'], $user['username'], 'EDIT_DOCUMENT', '/document-edit.php', 'success', "doc_id=$id");
        header("Location: /document-view.php?id=$id");
        exit;
    }

    // En cas d'erreur, mettre à jour $doc avec les valeurs saisies
    $doc = array_merge($doc, [
        'title'          => $title,
        'classification' => $classification,
        'category'       => $category,
        'content'        => $content,
    ]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier : <?= htmlspecialchars($doc['title']) ?> — UrbanUpC | Université Paris Cité</title>
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
                    <li class="breadcrumb-item"><a href="/document-view.php?id=<?= $id ?>" class="text-decoration-none text-truncate">
                        <?= htmlspecialchars($doc['title']) ?>
                    </a></li>
                    <li class="breadcrumb-item active">Modifier</li>
                </ol>
            </nav>

            <div class="row justify-content-center">
                <div class="col-lg-9">

                    <h4 class="fw-bold mb-4">
                        <i class="fas fa-edit text-primary me-2"></i>Modifier le document
                    </h4>

                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST">
                                <!-- Pas de token CSRF — IDOR possible (intentionnel) -->

                                <div class="row g-3 mb-4">
                                    <div class="col-12">
                                        <label for="title" class="form-label fw-semibold">
                                            Titre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               id="title"
                                               name="title"
                                               class="form-control form-control-lg"
                                               value="<?= htmlspecialchars($doc['title']) ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Classification</label>
                                        <select name="classification" class="form-select">
                                            <?php foreach (['public','internal','confidential','secret'] as $cls): ?>
                                            <option value="<?= $cls ?>" <?= $doc['classification'] === $cls ? 'selected' : '' ?>>
                                                <?= ucfirst($cls === 'secret' ? 'Secret' : ($cls === 'confidential' ? 'Confidentiel' : ($cls === 'internal' ? 'Interne' : 'Public'))) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Catégorie</label>
                                        <input type="text"
                                               name="category"
                                               class="form-control"
                                               value="<?= htmlspecialchars($doc['category'] ?? '') ?>"
                                               placeholder="ex: RH, Finance, Projet Phoenix...">
                                    </div>
                                    <?php $vis = $doc['visibility'] ?? 'all'; ?>
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
                                              style="font-family: inherit; font-size: .9rem"><?= htmlspecialchars($doc['content'] ?? '') ?></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                    </button>
                                    <a href="/document-view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Annuler</a>
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
