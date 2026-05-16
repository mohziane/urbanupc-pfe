<!-- En-tête -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            Bonjour,
            <!-- XSS stocké possible si first_name contient du HTML (intentionnel) -->
            <?= $_SESSION['first_name'] ?> <?= $_SESSION['last_name'] ?>
        </h4>
        <small class="text-muted">
            <i class="fas fa-clock me-1"></i><?= date('l d F Y, H:i') ?>
            &nbsp;|&nbsp;
            <i class="fas fa-building me-1"></i><?= htmlspecialchars($user['department']) ?>
        </small>
    </div>
    <span class="badge bg-dark fs-6 px-3"><?= roleBadge($user['role']) ?></span>
</div>

<!-- Actions rapides -->
<div class="row g-2 mb-4">
    <div class="col-auto">
        <a href="/document-new.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Nouveau document
        </a>
    </div>
    <?php if (in_array($user['role'], ['manager', 'admin'])): ?>
    <div class="col-auto">
        <a href="/announcement-form.php" class="btn btn-warning btn-sm text-dark">
            <i class="fas fa-bullhorn me-1"></i>Nouvelle annonce
        </a>
    </div>
    <?php endif; ?>
    <div class="col-auto">
        <a href="/upload.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-upload me-1"></i>Déposer un fichier
        </a>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['docs_total',  'fa-file-alt',      'text-primary',  'bg-primary',  'Documents totaux'],
        ['docs_mine',   'fa-folder-open',   'text-success',  'bg-success',  'Mes documents'],
        ['users_total', 'fa-users',         'text-info',     'bg-info',     'Collaborateurs actifs'],
        ['logs_today',  'fa-history',       'text-warning',  'bg-warning',  "Activités aujourd'hui"],
    ] as [$key, $icon, $textClass, $bgClass, $label]): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon <?= $bgClass ?> bg-opacity-10 rounded-3 me-3">
                    <i class="fas <?= $icon ?> <?= $textClass ?> fa-lg"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $stats[$key] ?></div>
                    <div class="small text-muted"><?= $label ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <!-- Annonces -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0"><i class="fas fa-bullhorn text-warning me-2"></i>Annonces</h6>
                <a href="/announcements.php" class="small text-decoration-none">Voir toutes</a>
            </div>
            <div class="card-body">
                <?php foreach ($announcements as $ann): ?>
                <div class="border-bottom pb-3 mb-3">
                    <a href="/announcement-view.php?id=<?= $ann['id'] ?>" class="text-decoration-none text-dark">
                        <h6 class="mb-1 fw-semibold">
                            <?php if ($ann['pinned']): ?><i class="fas fa-thumbtack text-danger me-1 small"></i><?php endif; ?>
                            <?= $ann['title'] /* XSS stocké intentionnel */ ?>
                        </h6>
                    </a>
                    <p class="small text-muted mb-1"><?= mb_substr(strip_tags($ann['content']), 0, 120) ?>…</p>
                    <small class="text-muted">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?>
                        &nbsp;&middot;&nbsp;<?= formatDate($ann['created_at']) ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Mes derniers documents -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0"><i class="fas fa-clock text-primary me-2"></i>Mes documents récents</h6>
                <a href="/documents.php" class="small text-decoration-none">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($myDocs as $doc): ?>
                    <li class="list-group-item border-0 py-3 px-4">
                        <div class="d-flex align-items-center">
                            <i class="<?= fileIcon($doc['file_name'] ?? '') ?> me-3 fa-lg"></i>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-semibold text-truncate small"><?= htmlspecialchars($doc['title']) ?></div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <?= classificationBadge($doc['classification']) ?>
                                    <span class="text-muted" style="font-size:.75rem"><?= formatDate($doc['updated_at'], 'd/m/Y') ?></span>
                                </div>
                            </div>
                            <a href="/document-view.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Journal d'activité -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0">
                    <i class="fas fa-list-alt text-secondary me-2"></i>
                    <?= $logScope === 'global' ? "Journal d'activité récent" : 'Mes activités récentes' ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Utilisateur</th>
                                <th>Action</th>
                                <th>Ressource</th>
                                <th>IP</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="ps-4"><span class="fw-semibold small"><?= htmlspecialchars($log['username'] ?? 'Anonyme') ?></span></td>
                                <td><code class="small"><?= htmlspecialchars($log['action']) ?></code></td>
                                <td class="small text-muted"><?= htmlspecialchars($log['resource']) ?></td>
                                <td><code class="small"><?= htmlspecialchars($log['ip_address']) ?></code></td>
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success-subtle text-success">OK</span>
                                    <?php elseif ($log['status'] === 'failure'): ?>
                                        <span class="badge bg-danger-subtle text-danger">ÉCHEC</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning">WARN</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= formatDate($log['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div><!-- /row -->
