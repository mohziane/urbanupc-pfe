<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();

$services = $pdo->query(
    "SELECT * FROM services WHERE status != 'offline' ORDER BY category, name"
)->fetchAll();

// Grouper par catégorie
$grouped = [];
foreach ($services as $s) {
    $grouped[$s['category']][] = $s;
}

$categoryLabels = [
    'informatique' => ['Informatique', 'fa-laptop-code', 'primary'],
    'rh'           => ['Ressources Humaines', 'fa-users', 'success'],
    'finance'      => ['Finance', 'fa-euro-sign', 'warning'],
    'juridique'    => ['Juridique', 'fa-balance-scale', 'danger'],
    'logistique'   => ['Logistique', 'fa-truck', 'info'],
    'autre'        => ['Autres Services', 'fa-cogs', 'secondary'],
];

logAudit($user['id'], $user['username'], 'VIEW_SERVICES', '/services.php');

$requestDone = isset($_GET['requested']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue de services — UrbanUpC | Université Paris Cité</title>
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
                    <i class="fas fa-th-large text-primary me-2"></i>Catalogue de services
                </h4>
                <?php if (in_array($user['role'], ['manager', 'admin'])): ?>
                <a href="/admin-services.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-cogs me-2"></i>Gérer les services
                </a>
                <?php endif; ?>
            </div>

            <?php if ($requestDone): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>Votre demande a bien été transmise au service concerné.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php foreach ($grouped as $catKey => $catServices): ?>
            <?php $catInfo = $categoryLabels[$catKey] ?? ['Autres', 'fa-cogs', 'secondary']; ?>
            <div class="mb-5">
                <h5 class="fw-semibold mb-3 pb-2 border-bottom">
                    <i class="fas <?= $catInfo[1] ?> text-<?= $catInfo[2] ?> me-2"></i><?= $catInfo[0] ?>
                    <span class="badge bg-<?= $catInfo[2] ?> bg-opacity-25 text-<?= $catInfo[2] ?> ms-2" style="font-size:.8rem">
                        <?= count($catServices) ?>
                    </span>
                </h5>
                <div class="row g-3">
                    <?php foreach ($catServices as $s): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="rounded-3 p-2 bg-<?= $catInfo[2] ?> bg-opacity-10 me-3 flex-shrink-0">
                                        <i class="fas <?= htmlspecialchars($s['icon']) ?> fa-lg text-<?= $catInfo[2] ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($s['name']) ?></h6>
                                        <?php if ($s['status'] === 'maintenance'): ?>
                                        <span class="badge bg-warning text-dark" style="font-size:.7rem">
                                            <i class="fas fa-tools me-1"></i>Maintenance
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-success-subtle text-success" style="font-size:.7rem">
                                            <i class="fas fa-circle me-1" style="font-size:.5rem"></i>Disponible
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="small text-muted mb-3"><?= htmlspecialchars($s['description'] ?? '') ?></p>
                                <?php if ($s['contact_name'] || $s['contact_email']): ?>
                                <div class="small text-muted mb-3">
                                    <?php if ($s['contact_name']): ?>
                                    <div><i class="fas fa-user me-1"></i><?= htmlspecialchars($s['contact_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($s['contact_email']): ?>
                                    <div><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($s['contact_email']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white border-0 pt-0">
                                <form method="POST" action="/services.php?requested=1">
                                    <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-<?= $catInfo[2] ?> w-100 <?= $s['status'] === 'maintenance' ? 'disabled' : '' ?>"
                                            <?= $s['status'] === 'maintenance' ? 'disabled' : '' ?>>
                                        <i class="fas fa-paper-plane me-1"></i>
                                        <?= $s['status'] === 'maintenance' ? 'Indisponible' : 'Demander l\'accès' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($grouped)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-th-large fa-3x mb-3 opacity-25"></i>
                <p>Aucun service disponible pour le moment.</p>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
<script>
// Gestion du formulaire de demande d'accès
document.querySelectorAll('form[action*="requested"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const serviceId = this.querySelector('[name=service_id]').value;
        // Log fictif + toast — pas de vraie soumission
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = 9999;
        toast.innerHTML = `<div class="toast show align-items-center text-bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="fas fa-check-circle me-2"></i>Demande transmise avec succès.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div></div>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    });
});
</script>
</body>
</html>
