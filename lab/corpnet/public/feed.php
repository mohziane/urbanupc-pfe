<?php
// =============================================================
//  UrbanUpC — Mur d'actualités (News Feed)
//  Fusion annonces + ressources, triées par date décroissante
//  Vulnérabilité intentionnelle : Stored XSS
//  ($item['title'] et $item['body'] affichés sans htmlspecialchars)
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$user = currentUser();
$pdo  = getDB();

// Annonces filtrées par visibilité du rôle connecté
$visAnn = visibilityClause($user['role'], 'a');
$ann = $pdo->query(
    "SELECT 'announcement' AS type,
            a.id, a.title, a.content AS body,
            a.pinned,
            a.created_at,
            u.first_name, u.last_name, u.department
     FROM announcements a
     JOIN users u ON a.author_id = u.id
     WHERE $visAnn
     ORDER BY a.created_at DESC
     LIMIT 10"
)->fetchAll();

// Ressources (visibles par tous les utilisateurs authentifiés)
$res = $pdo->query(
    "SELECT 'resource' AS type,
            r.id, r.title, r.file_path AS body,
            0 AS pinned,
            r.created_at,
            u.first_name, u.last_name, u.department
     FROM resources r
     JOIN users u ON r.user_id = u.id
     ORDER BY r.created_at DESC
     LIMIT 10"
)->fetchAll();

// Fusion + tri par date décroissante
$feed = array_merge($ann, $res);
usort($feed, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$feed = array_slice($feed, 0, 10);

// Prochains événements (5 à venir — XSS intentionnel sur description)
$upcomingEvents = $pdo->query(
    "SELECT id, title, description, event_date
     FROM events
     WHERE event_date >= NOW()
     ORDER BY event_date ASC
     LIMIT 5"
)->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_FEED', '/feed.php');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mur d'actualités — UrbanUpC | Université Paris Cité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/corpnet.css">
    <style>
        .event-strip {
            border-left: 3px solid #8b1a2e;
            border-radius: 0 10px 10px 0;
            background: linear-gradient(135deg, #fff5f7 0%, #fff 100%);
        }
        .event-strip .ev-dt { font-size: .72rem; color: #8b1a2e; font-weight: 700; }
        .event-strip .ev-title { font-size: .9rem; font-weight: 700; line-height: 1.3; }
        .event-strip .ev-desc { font-size: .78rem; color: #666; }
        .feed-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            transition: box-shadow .2s, transform .2s;
            overflow: hidden;
        }
        .feed-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.1); transform: translateY(-2px); }
        .feed-card .card-header { border-bottom: none; padding: 1.25rem 1.5rem .5rem; background: transparent; }
        .feed-card .card-body   { padding: .5rem 1.5rem 1.25rem; }
        .feed-card .card-footer { background: #f8f9fa; border-top: 1px solid #f1f3f5; padding: .75rem 1.5rem; font-size: .8rem; }
        .type-badge-ann { background: rgba(139,26,46,.1); color: #8b1a2e; border-radius: 6px; padding: .25rem .65rem; font-size: .7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .type-badge-res { background: rgba(13,110,253,.1); color: #0d6efd; border-radius: 6px; padding: .25rem .65rem; font-size: .7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .feed-title { font-size: 1.05rem; font-weight: 700; margin: .5rem 0 0; line-height: 1.4; }
        .feed-body  { color: #555; font-size: .9rem; line-height: 1.6; margin-top: .5rem; }
        .feed-meta  { color: #999; }
        .feed-timeline { position: relative; }
        .feed-timeline::before {
            content: '';
            position: absolute;
            left: 19px; top: 0; bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #e9ecef 0%, transparent 100%);
        }
        .feed-item { position: relative; padding-left: 50px; margin-bottom: 1.5rem; }
        .feed-dot {
            position: absolute;
            left: 8px; top: 20px;
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .65rem;
            z-index: 1;
        }
        .dot-ann { background: #8b1a2e; color: #fff; }
        .dot-res { background: #0d6efd; color: #fff; }
        .pinned-indicator { color: #e6a817; font-size: .8rem; }
    </style>
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
                        <i class="fas fa-stream me-2" style="color:#8b1a2e"></i>Mur d'actualités
                    </h4>
                    <small class="text-muted">Dernières annonces et ressources partagées</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <i class="fas fa-circle me-1" style="font-size:.5rem"></i>Annonce
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="fas fa-circle me-1" style="font-size:.5rem"></i>Ressource
                    </span>
                </div>
            </div>

            <?php if (!empty($upcomingEvents)): ?>
            <!-- ── Prochains Événements ── -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8 col-xl-7">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold mb-0" style="font-size:.8rem;letter-spacing:.5px;text-transform:uppercase;color:#8b1a2e">
                            <i class="fas fa-calendar-alt me-2"></i>Prochains Événements
                        </h6>
                        <a href="/agenda.php" class="small text-muted text-decoration-none">
                            Voir tout <i class="fas fa-arrow-right ms-1" style="font-size:.65rem"></i>
                        </a>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($upcomingEvents as $ev):
                            $ts      = strtotime($ev['event_date']);
                            $hasTime = (date('H:i', $ts) !== '00:00');
                            $diff    = (int)floor(($ts - time()) / 86400);
                            if ($diff < 0) $diff = 0;
                            $isToday = (date('Y-m-d', $ts) === date('Y-m-d'));
                        ?>
                        <div class="card border-0 shadow-sm event-strip px-3 py-2">
                            <div class="d-flex align-items-start gap-3">
                                <!-- Mini date badge — date only -->
                                <div class="text-center flex-shrink-0" style="min-width:40px">
                                    <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;color:#8b1a2e"><?= date('M', $ts) ?></div>
                                    <div style="font-size:1.3rem;font-weight:800;line-height:1;color:#212529"><?= date('d', $ts) ?></div>
                                    <div style="font-size:.58rem;color:#aaa"><?= date('Y', $ts) ?></div>
                                </div>
                                <!-- Details -->
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="ev-title">
                                            <?= htmlspecialchars($ev['title']) ?>
                                            <?php if ($hasTime): ?>
                                            <span class="text-muted fw-normal" style="font-size:.78rem">— à <?= date('H\hi', $ts) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($isToday): ?>
                                        <span class="badge bg-danger" style="font-size:.62rem">Aujourd'hui</span>
                                        <?php elseif ($diff <= 7): ?>
                                        <span class="badge bg-warning text-dark" style="font-size:.62rem">Dans <?= $diff ?> j</span>
                                        <?php else: ?>
                                        <span class="badge bg-light text-muted border" style="font-size:.62rem">Dans <?= $diff ?> j</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($ev['description'])): ?>
                                    <!-- VULNERABILITY: Stored XSS — description sans htmlspecialchars (intentionnel) -->
                                    <div class="ev-desc mt-1"><?= $ev['description'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($feed)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-stream fa-3x mb-3 opacity-25"></i>
                <p>Aucune actualité pour le moment.</p>
            </div>
            <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-7">
                    <div class="feed-timeline">
                        <?php foreach ($feed as $item): ?>
                        <div class="feed-item">

                            <!-- Pastille de type -->
                            <div class="feed-dot <?= $item['type'] === 'announcement' ? 'dot-ann' : 'dot-res' ?>">
                                <i class="fas <?= $item['type'] === 'announcement' ? 'fa-bullhorn' : 'fa-file' ?>"></i>
                            </div>

                            <div class="feed-card card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($item['type'] === 'announcement'): ?>
                                            <span class="type-badge-ann"><i class="fas fa-bullhorn me-1"></i>Annonce</span>
                                        <?php else: ?>
                                            <span class="type-badge-res"><i class="fas fa-file-upload me-1"></i>Ressource</span>
                                        <?php endif; ?>
                                        <span class="text-muted" style="font-size:.78rem">
                                            <i class="fas fa-clock me-1"></i><?= formatDate($item['created_at']) ?>
                                        </span>
                                    </div>

                                    <!-- VULNERABILITY: Stored XSS — titre affiché sans htmlspecialchars (intentionnel) -->
                                    <h5 class="feed-title">
                                        <?php if (!empty($item['pinned'])): ?>
                                            <span class="pinned-indicator me-1"><i class="fas fa-thumbtack"></i></span>
                                        <?php endif; ?>
                                        <?= $item['title'] ?>
                                    </h5>
                                </div>

                                <div class="card-body">
                                    <?php if ($item['type'] === 'announcement'): ?>
                                        <!-- VULNERABILITY: Stored XSS — contenu affiché sans htmlspecialchars (intentionnel) -->
                                        <p class="feed-body"><?= mb_substr(strip_tags($item['body']), 0, 280) ?><?= mb_strlen(strip_tags($item['body'])) > 280 ? '…' : '' ?></p>
                                        <a href="/announcement-view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger mt-1">
                                            Lire l'annonce <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <!-- VULNERABILITY: Stored XSS — file_path affiché sans htmlspecialchars (intentionnel) -->
                                        <p class="feed-body text-muted">
                                            <i class="fas fa-paperclip me-1"></i><?= $item['body'] ?>
                                        </p>
                                        <a href="/resources.php#resource-<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary mt-1">
                                            Voir la ressource <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="card-footer d-flex align-items-center gap-2 feed-meta">
                                    <i class="fas fa-user-circle"></i>
                                    <?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>
                                    <?php if ($item['department']): ?>
                                        <span class="text-muted">&mdash; <?= htmlspecialchars($item['department']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center mt-2 mb-4">
                        <a href="/announcements.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-bullhorn me-1"></i>Toutes les annonces
                        </a>
                        <a href="/resources.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-book-open me-1"></i>Toutes les ressources
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
