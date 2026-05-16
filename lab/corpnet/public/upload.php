<?php
// =============================================================
//  Upload de fichiers
//  VULNÉRABILITÉS intentionnelles :
//   - Validation MIME basée sur Content-Type header uniquement
//   - Pas de vérification de l'extension réelle
//   - Fichier stocké avec nom original (path traversal possible)
//   - Répertoire /uploads/ accessible directement via HTTP (webshell)
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur lors de l\'upload.';
    } else {
        // Validation MIME uniquement sur Content-Type — contournable
        $allowedMime = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/png',
            'image/jpeg',
            'text/plain',
        ];

        $mimeType = $file['type']; // Header Content-Type — non vérifié réellement
        if (!in_array($mimeType, $allowedMime)) {
            $error = 'Type de fichier non autorisé : ' . htmlspecialchars($mimeType);
        } else {
            // Nom de fichier : basé sur le nom original (path traversal possible si .. dans le nom)
            $originalName = $file['name'];
            $storedName   = $originalName; // Intentionnellement pas de renommage aléatoire
            $destPath     = UPLOAD_DIR . $storedName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Enregistrement en base
                $pdo->prepare(
                    "INSERT INTO uploads (uploader_id, original_name, stored_name, mime_type, file_size, upload_path)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $user['id'],
                    $originalName,
                    $storedName,
                    $mimeType,
                    $file['size'],
                    UPLOAD_URL . $storedName
                ]);

                $success = "Fichier <strong>" . htmlspecialchars($originalName) . "</strong> déposé avec succès.";
                logAudit($user['id'], $user['username'], 'UPLOAD_FILE', '/upload.php', 'success', "file=$originalName size={$file['size']}");
            } else {
                $error = 'Impossible de déplacer le fichier.';
            }
        }
    }
}

// Mes uploads récents
$myUploads = $pdo->prepare("SELECT * FROM uploads WHERE uploader_id = ? ORDER BY created_at DESC LIMIT 10");
$myUploads->execute([$user['id']]);
$myUploads = $myUploads->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dépôt de fichiers — UrbanUpC | Université Paris Cité</title>
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
                <i class="fas fa-upload text-primary me-2"></i>Dépôt de documents
            </h4>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">Déposer un fichier</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <!-- Pas de token CSRF -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">FICHIER</label>
                                    <div class="border rounded-2 p-4 text-center bg-light" id="dropZone">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted small mb-2">
                                            Glissez votre fichier ici ou
                                        </p>
                                        <label class="btn btn-outline-primary btn-sm cursor-pointer">
                                            <i class="fas fa-folder-open me-1"></i>Parcourir
                                            <input type="file" name="file" id="fileInput" class="d-none" required>
                                        </label>
                                        <p class="text-muted mt-2 mb-0" id="fileName" style="font-size:.8rem"></p>
                                    </div>
                                </div>

                                <div class="alert alert-info small py-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Formats acceptés : PDF, DOCX, XLSX, PPTX, PNG, JPG, TXT — Max 10 Mo
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-upload me-2"></i>Déposer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="fw-semibold mb-0">Mes dépôts récents</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($myUploads)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 opacity-25"></i>
                                    <p class="small">Aucun fichier déposé.</p>
                                </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($myUploads as $up): ?>
                                <li class="list-group-item border-0 py-3 px-4">
                                    <div class="d-flex align-items-center">
                                        <i class="<?= fileIcon($up['original_name']) ?> me-3"></i>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-semibold small text-truncate">
                                                <?= htmlspecialchars($up['original_name']) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= formatSize($up['file_size'] ?? 0) ?>
                                                &middot; <?= formatDate($up['created_at']) ?>
                                            </small>
                                        </div>
                                        <!-- Lien direct vers le fichier dans /uploads/ -->
                                        <a href="<?= htmlspecialchars($up['upload_path']) ?>"
                                           class="btn btn-sm btn-outline-secondary ms-2"
                                           target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
<script>
    document.getElementById('fileInput').addEventListener('change', function() {
        const name = this.files[0]?.name ?? '';
        document.getElementById('fileName').textContent = name ? '📎 ' + name : '';
    });
</script>
</body>
</html>
