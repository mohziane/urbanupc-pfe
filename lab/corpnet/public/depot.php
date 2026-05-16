<?php
// =============================================================
//  UrbanUpC — Dépôt de fichiers P2P (transfert ciblé)
//  VULNÉRABILITÉS intentionnelles :
//   - IDOR sur le téléchargement : download_transfer.php?id=N ne
//     vérifie pas que l'utilisateur est sender ou receiver.
//   - Validation MIME basée uniquement sur Content-Type header (spoofable).
//   - Pas de validation d'extension réelle.
//   - Pas de token CSRF.
//   - Répertoire /uploads/transfers/ accessible directement via HTTP.
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$pdo     = getDB();
$success = '';
$error   = '';

// Répertoire de stockage des fichiers transférés
$transferDir = UPLOAD_DIR . 'transfers/';
if (!is_dir($transferDir)) {
    mkdir($transferDir, 0755, true);
}

// ── POST : envoyer un fichier ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    // Pas de token CSRF (intentionnel)
    $receiverRaw = $_POST['receiver_id'] ?? '';

    // Validation stricte : accepte 'all' (broadcast) ou un entier positif
    if ($receiverRaw === 'all') {
        $receiverId = 'all';
    } elseif (ctype_digit($receiverRaw) && (int)$receiverRaw > 0) {
        $receiverId = (string)(int)$receiverRaw; // normalisé en chaîne propre
    } else {
        $receiverId = null;
    }

    $file = $_FILES['file'];

    if ($receiverId === null) {
        $error = 'Veuillez sélectionner un destinataire valide.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur lors de l\'upload.';
    } else {
        // Validation MIME sur Content-Type uniquement — contournable
        $allowedMime = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/png',
            'image/jpeg',
            'text/plain',
        ];

        $mimeType = $file['type']; // Header non vérifié réellement
        if (!in_array($mimeType, $allowedMime)) {
            $error = 'Type de fichier non autorisé : ' . htmlspecialchars($mimeType);
        } else {
            // Nom de fichier basé sur l'original — path traversal possible
            $originalName = $file['name'];
            $storedName   = 'ft_' . $user['id'] . '_' . time() . '_' . $originalName;
            $destPath     = $transferDir . $storedName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // receiver_id est soit 'all' soit un entier en string — PDO l'insère tel quel (VARCHAR)
                $pdo->prepare(
                    "INSERT INTO file_transfers (sender_id, receiver_id, file_name, file_path)
                     VALUES (?, ?, ?, ?)"
                )->execute([$user['id'], $receiverId, $originalName, $storedName]);

                $newId = $pdo->lastInsertId();
                logAudit($user['id'], $user['username'], 'SEND_FILE', '/depot.php', 'success',
                    "to=$receiverId file=$originalName transfer_id=$newId");
                $success = "Fichier <strong>" . htmlspecialchars($originalName) . "</strong> envoyé avec succès.";
            } else {
                $error = 'Impossible de déplacer le fichier.';
            }
        }
    }
}

// ── GET : fichiers reçus et envoyés ───────────────────────────────────────

// Fichiers reçus : envoyés directement à moi OU en broadcast ('all')
$stmtReceived = $pdo->prepare(
    "SELECT ft.id, ft.file_name, ft.file_path, ft.created_at, ft.receiver_id,
            u.first_name, u.last_name, u.username, u.role AS sender_role
     FROM file_transfers ft
     JOIN users u ON ft.sender_id = u.id
     WHERE (ft.receiver_id = ? OR ft.receiver_id = 'all')
     ORDER BY ft.created_at DESC"
);
$stmtReceived->execute([(string)$user['id']]);
$received = $stmtReceived->fetchAll();

// Fichiers envoyés : LEFT JOIN pour gérer le cas receiver_id = 'all'
$stmtSent = $pdo->prepare(
    "SELECT ft.id, ft.file_name, ft.file_path, ft.created_at, ft.receiver_id,
            u.first_name, u.last_name, u.username, u.role AS receiver_role
     FROM file_transfers ft
     LEFT JOIN users u ON u.id = IF(ft.receiver_id = 'all', NULL, CAST(ft.receiver_id AS UNSIGNED))
     WHERE ft.sender_id = ?
     ORDER BY ft.created_at DESC"
);
$stmtSent->execute([$user['id']]);
$sent = $stmtSent->fetchAll();

// Liste des utilisateurs pour le select destinataire
$allUsers = $pdo->query(
    "SELECT id, first_name, last_name, username, role, department
     FROM users
     WHERE active = 1
     ORDER BY last_name, first_name"
)->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_DEPOT', '/depot.php', 'success');
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

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0">
                        <i class="fas fa-exchange-alt me-2" style="color:#8b1a2e"></i>Dépôt de fichiers
                    </h4>
                    <small class="text-muted">Transfert sécurisé de fichiers entre collaborateurs</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="fas fa-inbox me-1"></i><?= count($received) ?> reçu(s)
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="fas fa-paper-plane me-1"></i><?= count($sent) ?> envoyé(s)
                    </span>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- ── Formulaire d'envoi ── -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-paper-plane text-primary me-2"></i>Envoyer un fichier
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Pas de token CSRF (intentionnel) -->
                            <form method="POST" enctype="multipart/form-data">

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">
                                        DESTINATAIRE <span class="text-danger">*</span>
                                    </label>
                                    <!-- VULNERABILITY : liste complète des utilisateurs exposée -->
                                    <select name="receiver_id" class="form-select" required>
                                        <option value="all">📢 Tous les utilisateurs (Broadcast)</option>
                                        <option value="" disabled>────────────────────</option>
                                        <?php foreach ($allUsers as $u): ?>
                                        <?php if ($u['id'] === $user['id']) continue; ?>
                                        <option value="<?= $u['id'] ?>">
                                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                            (<?= htmlspecialchars($u['username']) ?>
                                            — <?= htmlspecialchars($u['role']) ?>
                                            <?= $u['department'] ? '· ' . htmlspecialchars($u['department']) : '' ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">FICHIER</label>
                                    <div class="border rounded-2 p-3 text-center bg-light" id="dropZone">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2 d-block"></i>
                                        <label class="btn btn-outline-primary btn-sm cursor-pointer mb-0">
                                            <i class="fas fa-folder-open me-1"></i>Parcourir
                                            <input type="file" name="file" id="fileInput" class="d-none" required>
                                        </label>
                                        <p class="text-muted mt-2 mb-0 small" id="fileName"></p>
                                    </div>
                                    <div class="form-text">
                                        PDF, DOCX, XLSX, PPTX, PNG, JPG, TXT — Max 10 Mo
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ── Fichiers reçus + envoyés ── -->
                <div class="col-lg-8">

                    <!-- Fichiers reçus -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-inbox text-success me-2"></i>Fichiers reçus
                            </h6>
                            <span class="badge bg-success"><?= count($received) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($received)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                                <small>Aucun fichier reçu.</small>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Fichier</th>
                                            <th>Expéditeur</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($received as $ft): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <i class="<?= fileIcon($ft['file_name']) ?> me-2"></i>
                                                <span class="small fw-semibold"><?= htmlspecialchars($ft['file_name']) ?></span>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold">
                                                    <?= htmlspecialchars($ft['first_name'] . ' ' . $ft['last_name']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size:.72rem">
                                                    <?= roleBadge($ft['sender_role']) ?>
                                                </div>
                                            </td>
                                            <td class="small text-muted"><?= formatDate($ft['created_at'], 'd/m/Y H:i') ?></td>
                                            <td>
                                                <!-- VULNERABILITY: IDOR — id séquentiel, aucune vérif côté serveur -->
                                                <a href="/download_transfer.php?id=<?= $ft['id'] ?>"
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-download me-1"></i>Télécharger
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Fichiers envoyés -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-semibold mb-0">
                                <i class="fas fa-paper-plane text-primary me-2"></i>Fichiers envoyés
                            </h6>
                            <span class="badge bg-primary"><?= count($sent) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($sent)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-paper-plane fa-2x mb-2 d-block opacity-25"></i>
                                <small>Aucun fichier envoyé.</small>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Fichier</th>
                                            <th>Destinataire</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sent as $ft): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <i class="<?= fileIcon($ft['file_name']) ?> me-2"></i>
                                                <span class="small fw-semibold"><?= htmlspecialchars($ft['file_name']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($ft['receiver_id'] === 'all'): ?>
                                                <span class="badge bg-info text-dark">
                                                    <i class="fas fa-broadcast-tower me-1"></i>Broadcast — Tous
                                                </span>
                                                <?php else: ?>
                                                <div class="small fw-semibold">
                                                    <?= htmlspecialchars($ft['first_name'] . ' ' . $ft['last_name']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size:.72rem">
                                                    <?= roleBadge($ft['receiver_role']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= formatDate($ft['created_at'], 'd/m/Y H:i') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /col -->
            </div><!-- /row -->

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
<script>
    document.getElementById('fileInput').addEventListener('change', function () {
        const name = this.files[0]?.name ?? '';
        document.getElementById('fileName').textContent = name ? '📎 ' + name : '';
    });
</script>
</body>
</html>
