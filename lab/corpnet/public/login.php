<?php
// =============================================================
//  UrbanUpC — Page de connexion (standalone)
//  Vulnérabilités intentionnelles préservées :
//   - Open Redirect sur le paramètre ?redirect=
//   - Pas de CSRF token
//   - Pas de rate limiting (brute force possible)
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/app.php';

if (isAuthenticated()) {
    header('Location: /feed.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $result = login($username, $password);
        if ($result['success']) {
            // Pas de validation — Open Redirect intentionnel
            $redirect = $_GET['redirect'] ?? '/feed.php';
            header('Location: ' . $redirect);
            exit;
        }
        $error = $result['message'];
    } else {
        $error = 'Veuillez renseigner vos identifiants.';
    }
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanUpC — Connexion | Université Paris Cité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --upc-red: #8b1a2e; --upc-red-lt: #b52240; }
        body {
            background: linear-gradient(135deg, #8b1a2e 0%, #4a0d18 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header { background: var(--upc-red); padding: 2rem; text-align: center; }
        .login-logo {
            height: 72px; width: auto; max-width: 200px;
            object-fit: contain; margin-bottom: .75rem;
            display: block; margin-left: auto; margin-right: auto;
        }
        .login-header .logo-text { font-size: 1.8rem; font-weight: 700; color: #fff; letter-spacing: 2px; }
        .login-header .logo-sub  { color: rgba(255,255,255,.75); font-size: .85rem; letter-spacing: 2px; text-transform: uppercase; margin-top: .25rem; }
        .login-body { padding: 2.5rem; }
        .form-control:focus { border-color: var(--upc-red); box-shadow: 0 0 0 .2rem rgba(139,26,46,.15); }
        .btn-login { background: var(--upc-red); color: #fff; border: none; padding: .75rem; font-weight: 600; letter-spacing: .5px; }
        .btn-login:hover { background: var(--upc-red-lt); color: #fff; }
        .login-footer { background: #f8f9fa; padding: 1rem 2.5rem; border-top: 1px solid #e9ecef; font-size: .78rem; color: #6c757d; text-align: center; }
        .input-group-text { background: #f8f9fa; border-color: #ced4da; color: #6c757d; }
        .back-link { position: absolute; top: 1.5rem; left: 2rem; color: rgba(255,255,255,.6); text-decoration: none; font-size: .85rem; }
        .back-link:hover { color: #fff; }
    </style>
</head>
<body>
    <a href="/" class="back-link"><i class="fas fa-arrow-left me-1"></i>Accueil</a>

    <div class="login-card">
        <div class="login-header">
            <img src="/assets/images/Upc.jpg" alt="Université Paris Cité" class="login-logo">
            <div class="logo-text">UrbanUpC</div>
            <div class="logo-sub">Université Paris Cité — Intranet</div>
        </div>

        <div class="login-body">
            <h6 class="text-muted mb-4 text-center">Authentification requise</h6>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
            </div>
            <?php endif; ?>

            <!-- Pas de CSRF token (intentionnel) -->
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold text-secondary small">IDENTIFIANT</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="prenom.nom"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold text-secondary small">MOT DE PASSE</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd" tabindex="-1">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label small text-muted" for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="/forgot-password.php" class="small text-muted">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn btn-login w-100 rounded-2">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                </button>
            </form>
        </div>

        <div class="login-footer">
            <i class="fas fa-shield-alt me-1" style="color: var(--upc-red)"></i>
            Accès réservé aux membres de l'Université Paris Cité<br>
            Version <?= APP_VERSION ?> — &copy; <?= date('Y') ?> Université Paris Cité
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePwd').addEventListener('click', function() {
            const pwd  = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    </script>
</body>
</html>
