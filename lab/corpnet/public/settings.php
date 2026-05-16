<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
header('Content-Type: text/html; charset=utf-8');

$user    = currentUser();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sauvegarde fictive des préférences (réalisme suffisant)
    $success = 'Vos paramètres ont été enregistrés.';
    logAudit($user['id'], $user['username'], 'UPDATE_SETTINGS', '/settings.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres — UrbanUpC | Université Paris Cité</title>
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
                <i class="fas fa-cog text-secondary me-2"></i>Paramètres
            </h4>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 mb-4">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-4">

                    <!-- Notifications -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="fw-semibold mb-0">
                                    <i class="fas fa-bell text-warning me-2"></i>Notifications
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notifEmail" name="notif_email" checked>
                                    <label class="form-check-label" for="notifEmail">
                                        Notifications par e-mail
                                        <div class="small text-muted">Recevoir les annonces importantes par e-mail</div>
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notifDoc" name="notif_docs" checked>
                                    <label class="form-check-label" for="notifDoc">
                                        Alertes nouveaux documents
                                        <div class="small text-muted">Être notifié lors d'un nouveau dépôt</div>
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="notifLogin" name="notif_login">
                                    <label class="form-check-label" for="notifLogin">
                                        Alertes de connexion
                                        <div class="small text-muted">Notification lors d'une connexion depuis un nouvel appareil</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Apparence -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="fw-semibold mb-0">
                                    <i class="fas fa-palette text-primary me-2"></i>Apparence
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">LANGUE</label>
                                    <select name="lang" class="form-select">
                                        <option value="fr" selected>Français</option>
                                        <option value="en">English</option>
                                        <option value="de">Deutsch</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">THÈME</label>
                                    <div class="d-flex gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" checked>
                                            <label class="form-check-label" for="themeLight">Clair</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark">
                                            <label class="form-check-label" for="themeDark">Sombre</label>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold text-muted">FORMAT DE DATE</label>
                                    <select name="date_format" class="form-select">
                                        <option value="dmy" selected>JJ/MM/AAAA</option>
                                        <option value="mdy">MM/JJ/AAAA</option>
                                        <option value="ymd">AAAA-MM-JJ</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sécurité -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="fw-semibold mb-0">
                                    <i class="fas fa-shield-alt text-danger me-2"></i>Sécurité
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">DÉLAI D'EXPIRATION DE SESSION</label>
                                    <select name="session_timeout" class="form-select">
                                        <option value="30">30 minutes</option>
                                        <option value="60" selected>1 heure</option>
                                        <option value="240">4 heures</option>
                                        <option value="480">8 heures</option>
                                    </select>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactor" name="two_factor">
                                    <label class="form-check-label" for="twoFactor">
                                        Authentification à deux facteurs
                                        <div class="small text-muted">Fonctionnalité disponible prochainement</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Accessibilité -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="fw-semibold mb-0">
                                    <i class="fas fa-universal-access text-info me-2"></i>Accessibilité
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">TAILLE DE POLICE</label>
                                    <select name="font_size" class="form-select">
                                        <option value="sm">Petite</option>
                                        <option value="md" selected>Normale</option>
                                        <option value="lg">Grande</option>
                                    </select>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="highContrast" name="high_contrast">
                                    <label class="form-check-label" for="highContrast">
                                        Contraste élevé
                                        <div class="small text-muted">Améliorer la lisibilité du texte</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer les paramètres
                    </button>
                    <a href="/dashboard.php" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </form>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/corpnet.js"></script>
</body>
</html>
