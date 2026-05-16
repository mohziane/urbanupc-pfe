<?php
require_once __DIR__ . '/../config/app.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — UrbanUpC | Université Paris Cité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --meridian-blue: #1a3a5c;
            --meridian-gold: #c8a84b;
        }
        body {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2035 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
        }
        .card-header-corp {
            background: var(--meridian-blue);
            padding: 2rem;
            text-align: center;
        }
        .logo-text { font-size: 1.8rem; font-weight: 700; color: #fff; letter-spacing: 2px; }
        .logo-sub  { color: var(--meridian-gold); font-size: .85rem; letter-spacing: 3px; text-transform: uppercase; margin-top: .25rem; }
        .card-footer-corp {
            background: #f8f9fa;
            padding: 1rem 2.5rem;
            border-top: 1px solid #e9ecef;
            font-size: .78rem;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header-corp">
            <div class="logo-text">
                <i class="fas fa-building me-2" style="color:var(--meridian-gold)"></i>UrbanUpC
            </div>
            <div class="logo-sub">Université Paris Cité — Intranet</div>
        </div>

        <div class="p-4">
            <div class="text-center mb-4">
                <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px">
                    <i class="fas fa-key fa-2x" style="color:var(--meridian-gold)"></i>
                </div>
                <h5 class="fw-bold">Réinitialisation du mot de passe</h5>
                <p class="text-muted small">Vous avez oublié votre mot de passe d'accès à UrbanUpC ?</p>
            </div>

            <div class="alert alert-info d-flex align-items-start gap-3">
                <i class="fas fa-info-circle mt-1 text-info"></i>
                <div>
                    <strong>Contactez le service IT</strong><br>
                    <span class="small">La réinitialisation de mot de passe s'effectue uniquement via le service informatique de Université Paris Cité.</span>
                </div>
            </div>

            <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-envelope text-primary me-2"></i>
                    <div>
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold small">it-support@meridian-sa.local</div>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-phone text-success me-2"></i>
                    <div>
                        <div class="small text-muted">Téléphone</div>
                        <div class="fw-semibold small">Poste interne #4200</div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock text-warning me-2"></i>
                    <div>
                        <div class="small text-muted">Horaires</div>
                        <div class="fw-semibold small">Lun–Ven, 8h00–18h00</div>
                    </div>
                </div>
            </div>

            <a href="/index.php" class="btn btn-outline-secondary w-100">
                <i class="fas fa-arrow-left me-2"></i>Retour à la connexion
            </a>
        </div>

        <div class="card-footer-corp">
            <i class="fas fa-shield-alt me-1" style="color:var(--meridian-gold)"></i>
            Version <?= APP_VERSION ?> — &copy; <?= date('Y') ?> Université Paris Cité
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
