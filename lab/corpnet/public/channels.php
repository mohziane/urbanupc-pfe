<?php
// =============================================================
//  UrbanUpC — Discussions / Channels (Forum interne)
//  Vulnérabilités intentionnelles :
//   - Broken Access Control : le canal sélectionné via ?channel=
//     n'est JAMAIS validé côté serveur → un étudiant peut lire/écrire
//     dans le canal 'staff' en changeant l'URL manuellement
//   - Stored XSS : messages affichés sans htmlspecialchars
//   - Pas de token CSRF sur le formulaire de message
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user = currentUser();
$pdo  = getDB();

// Définition des canaux — utilisée UNIQUEMENT pour l'affichage UI
// Le contrôle d'accès n'est délibérément pas appliqué lors des requêtes
$channels = [
    'general'  => [
        'label' => 'Général',
        'desc'  => 'Canal ouvert à toute la promotion',
        'icon'  => 'fa-globe',
        'color' => 'success',
        'roles' => ['user', 'manager', 'admin'],
    ],
    'students' => [
        'label' => 'Étudiants',
        'desc'  => 'Espace réservé aux étudiants',
        'icon'  => 'fa-user-graduate',
        'color' => 'primary',
        'roles' => ['user'],
    ],
    'staff'    => [
        'label' => 'Staff',
        'desc'  => 'Canal confidentiel — enseignants & administration',
        'icon'  => 'fa-chalkboard-teacher',
        'color' => 'danger',
        'roles' => ['manager', 'admin'],
    ],
];

// VULNERABILITY: Broken Access Control
// Le canal est lu depuis l'URL sans aucune vérification du rôle de l'utilisateur
// Un étudiant peut accéder à ?channel=staff et lire/poster dans le canal staff
$channel = $_GET['channel'] ?? 'general';
if (!array_key_exists($channel, $channels)) {
    $channel = 'general';
}

// Traitement POST — insertion du message
// VULNERABILITY: aucune vérification que $user['role'] est autorisé pour $channel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de CSRF token (intentionnel)
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        // VULNERABILITY: n'importe quel utilisateur authentifié peut poster dans n'importe quel canal
        $pdo->prepare(
            "INSERT INTO chat_messages (channel_name, user_id, message) VALUES (?, ?, ?)"
        )->execute([$channel, $user['id'], $msg]);
        logAudit($user['id'], $user['username'], 'POST_MESSAGE', '/channels.php', 'success', "channel=$channel");
    }
    header("Location: /channels.php?channel=$channel");
    exit;
}

// Récupération des messages — VULNERABILITY: pas de vérification du rôle pour la lecture
$stmt = $pdo->prepare(
    "SELECT m.id, m.message, m.created_at,
            u.first_name, u.last_name, u.role AS user_role, u.department
     FROM chat_messages m
     JOIN users u ON m.user_id = u.id
     WHERE m.channel_name = ?
     ORDER BY m.created_at ASC
     LIMIT 100"
);
$stmt->execute([$channel]);
$messages = $stmt->fetchAll();

logAudit($user['id'], $user['username'], 'VIEW_CHANNEL', '/channels.php', 'success', "channel=$channel");

$ch = $channels[$channel];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussions — UrbanUpC | Université Paris Cité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/corpnet.css">
    <style>
        .channel-nav .channel-btn {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .65rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            font-size: .9rem;
            transition: all .15s;
            border: 1px solid transparent;
            margin-bottom: .4rem;
        }
        .channel-nav .channel-btn:hover { background: #f1f3f5; color: #222; }
        .channel-nav .channel-btn.active { background: #fff; border-color: #dee2e6; color: #212529; font-weight: 600; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .channel-nav .channel-btn .ch-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .8rem; flex-shrink: 0; }
        .channel-nav .channel-btn .ch-meta { font-size: .72rem; color: #999; font-weight: 400; }

        .messages-area {
            height: calc(100vh - 340px);
            min-height: 300px;
            overflow-y: auto;
            padding: 1rem 0;
        }
        .msg-row { display: flex; gap: .75rem; margin-bottom: 1rem; }
        .msg-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .85rem;
            flex-shrink: 0;
            color: #fff;
        }
        .msg-bubble { flex: 1; }
        .msg-meta { font-size: .75rem; color: #999; margin-bottom: .25rem; }
        .msg-meta .name { font-weight: 600; color: #444; margin-right: .4rem; }
        .msg-content {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0 10px 10px 10px;
            padding: .6rem .9rem;
            font-size: .9rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .msg-row.mine .msg-bubble { display: flex; flex-direction: column; align-items: flex-end; }
        .msg-row.mine .msg-content {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
            border-radius: 10px 10px 0 10px;
        }
        .msg-row.mine .msg-meta { text-align: right; }

        .post-form { border-top: 1px solid #e9ecef; padding-top: 1rem; margin-top: auto; }
        .post-form .form-control { border-radius: 8px 0 0 8px; resize: none; }
        .post-form .btn-send { border-radius: 0 8px 8px 0; }

        .channel-header { padding: .75rem 1.25rem; border-bottom: 1px solid #e9ecef; background: #fff; border-radius: 12px 12px 0 0; }
        .messages-card { border-radius: 12px; border: 1px solid #e9ecef; background: #fff; display: flex; flex-direction: column; }
    </style>
</head>
<body class="corpnet-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">
                    <i class="fas fa-comments me-2" style="color:#8b1a2e"></i>Discussions
                </h4>
            </div>

            <div class="row g-3">

                <!-- ── Panneau canaux ── -->
                <div class="col-md-3 col-lg-2">
                    <div class="card border-0 shadow-sm p-3">
                        <div class="small fw-semibold text-muted text-uppercase mb-2" style="letter-spacing:1px">Canaux</div>
                        <nav class="channel-nav">
                            <?php foreach ($channels as $key => $c): ?>
                            <?php
                            // UI : n'affiche que les canaux autorisés pour le rôle de l'utilisateur
                            // VULNERABILITY : mais rien n'empêche d'accéder à ?channel=staff directement
                            $isAllowed = in_array($user['role'], $c['roles']);
                            if (!$isAllowed) continue;
                            ?>
                            <a href="?channel=<?= $key ?>"
                               class="channel-btn <?= $channel === $key ? 'active' : '' ?>">
                                <div class="ch-icon bg-<?= $c['color'] ?> bg-opacity-10 text-<?= $c['color'] ?>">
                                    <i class="fas <?= $c['icon'] ?>"></i>
                                </div>
                                <div>
                                    <div><?= htmlspecialchars($c['label']) ?></div>
                                    <div class="ch-meta"><?= htmlspecialchars($c['desc']) ?></div>
                                </div>
                            </a>
                            <?php endforeach; ?>

                            <?php if ($user['role'] === 'user'): ?>
                            <!-- Note : canal 'staff' non affiché dans l'UI mais accessible via ?channel=staff -->
                            <div class="mt-3 p-2 rounded" style="background:#fff3cd; border:1px solid #ffc107">
                                <small class="text-warning-emphasis" style="font-size:.72rem">
                                    <i class="fas fa-info-circle me-1"></i>
                                    D'autres canaux existent sur cette plateforme.
                                </small>
                            </div>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>

                <!-- ── Zone messages ── -->
                <div class="col-md-9 col-lg-10">
                    <div class="messages-card shadow-sm">

                        <!-- En-tête canal -->
                        <div class="channel-header d-flex align-items-center gap-2">
                            <div class="ch-icon bg-<?= $ch['color'] ?> bg-opacity-10 text-<?= $ch['color'] ?>"
                                 style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center">
                                <i class="fas <?= $ch['icon'] ?>"></i>
                            </div>
                            <div>
                                <div class="fw-bold"># <?= htmlspecialchars($ch['label']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($ch['desc']) ?></div>
                            </div>
                            <span class="ms-auto badge bg-<?= $ch['color'] ?>-subtle text-<?= $ch['color'] ?> border border-<?= $ch['color'] ?>-subtle">
                                <?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <!-- Messages -->
                        <div class="messages-area px-3" id="msgArea">
                            <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3 opacity-25"></i>
                                <p>Aucun message dans ce canal. Soyez le premier à écrire !</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <?php $isMine = ($msg['first_name'] . ' ' . $msg['last_name'] === $user['first_name'] . ' ' . $user['last_name']); ?>
                            <div class="msg-row <?= $isMine ? 'mine' : '' ?>">
                                <?php if (!$isMine): ?>
                                <div class="msg-avatar bg-<?= $ch['color'] ?>">
                                    <?= strtoupper(mb_substr($msg['first_name'], 0, 1) . mb_substr($msg['last_name'], 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                                <div class="msg-bubble">
                                    <div class="msg-meta">
                                        <span class="name"><?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?></span>
                                        <?= roleBadge($msg['user_role']) ?>
                                        <span class="ms-1"><?= formatDate($msg['created_at'], 'd/m H:i') ?></span>
                                    </div>
                                    <!-- VULNERABILITY: Stored XSS — message affiché sans htmlspecialchars (intentionnel) -->
                                    <div class="msg-content"><?= $msg['message'] ?></div>
                                </div>
                                <?php if ($isMine): ?>
                                <div class="msg-avatar bg-secondary">
                                    <?= strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Formulaire de message -->
                        <!-- Pas de CSRF token (intentionnel) -->
                        <div class="post-form px-3 pb-3">
                            <form method="POST">
                                <div class="input-group">
                                    <textarea name="message"
                                              class="form-control"
                                              rows="2"
                                              placeholder="Écrire un message dans #<?= htmlspecialchars($ch['label']) ?>…"
                                              required></textarea>
                                    <button type="submit" class="btn btn-primary btn-send">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>

            </div><!-- /row -->
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
<script>
    // Scroll automatique vers le bas des messages
    const area = document.getElementById('msgArea');
    if (area) area.scrollTop = area.scrollHeight;
</script>
</body>
</html>
