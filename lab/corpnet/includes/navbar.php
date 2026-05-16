<?php
$user = currentUser();
$pdo  = getDB();

// Dernières annonces pour le dropdown notifications
$notifs = $pdo->query(
    "SELECT a.id, a.title, a.created_at, u.first_name, u.last_name
     FROM announcements a JOIN users u ON a.author_id = u.id
     ORDER BY a.pinned DESC, a.created_at DESC LIMIT 4"
)->fetchAll();
?>
<nav class="navbar navbar-expand-lg navbar-dark corpnet-navbar sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="/dashboard.php">
            <img src="/assets/images/Upc.jpg" alt="UPC" class="brand-logo-img me-2">
            <div>
                <span class="brand-name">UrbanUpC</span>
                <span class="brand-tagline d-none d-md-inline">Intranet</span>
            </div>
        </a>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <!-- Notifications avec dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light position-relative"
                        data-bs-toggle="dropdown"
                        title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if (count($notifs) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">
                        <?= count($notifs) ?>
                    </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:320px">
                    <li><div class="dropdown-header fw-semibold">Annonces récentes</div></li>
                    <li><hr class="dropdown-divider my-0"></li>
                    <?php if (empty($notifs)): ?>
                    <li><span class="dropdown-item text-muted small">Aucune annonce</span></li>
                    <?php else: ?>
                    <?php foreach ($notifs as $n): ?>
                    <li>
                        <a class="dropdown-item py-2" href="/announcement-view.php?id=<?= $n['id'] ?>">
                            <div class="small fw-semibold text-truncate"><?= htmlspecialchars($n['title']) ?></div>
                            <div class="text-muted" style="font-size:.75rem">
                                <?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?>
                                &middot; <?= date('d/m/Y', strtotime($n['created_at'])) ?>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider my-0"></li>
                    <li><a class="dropdown-item text-center small text-primary py-2" href="/announcements.php">Voir toutes les annonces</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Utilisateur connecté -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2"
                        data-bs-toggle="dropdown">
                    <div class="avatar-circle-sm">
                        <?= mb_strtoupper(mb_substr($_SESSION['first_name'] ?? 'U', 0, 1) . mb_substr($_SESSION['last_name'] ?? '', 0, 1)) ?>
                    </div>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['first_name'] ?? '') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($_SESSION['department'] ?? '') ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/profile.php"><i class="fas fa-user me-2 text-primary"></i>Mon profil</a></li>
                    <li><a class="dropdown-item" href="/settings.php"><i class="fas fa-cog me-2 text-secondary"></i>Paramètres</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<?php include __DIR__ . '/chat_widget.php'; ?>
