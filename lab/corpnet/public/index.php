<?php
// =============================================================
//  UrbanUpC — Landing Page publique
//  Université Paris Cité — Master Cybersécurité
//  Vulnérabilité intentionnelle : SQL Injection sur le formulaire newsletter
//  (entrée utilisateur concaténée directement dans la requête INSERT)
// =============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';

$subscribed = false;
$subError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $pdo = getDB();
    // VULNERABILITY: SQL Injection — $_POST['email'] non sanitisé, concaténé directement
    $email = $_POST['email'];
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        $pdo->query("INSERT INTO subscribers (email, ip_address) VALUES ('$email', '$ip')");
        $subscribed = true;
    } catch (Exception $e) {
        $subError = 'Une erreur est survenue.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UrbanUpC — Université Paris Cité | Master Cybersécurité</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --upc-red: #8b1a2e; --upc-red-lt: #b52240; --upc-dark: #4a0d18; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; overflow-x: hidden; }

        /* ── Hero ────────────────────────────────────────────── */
        .hero {
            background: linear-gradient(135deg, var(--upc-dark) 0%, var(--upc-red) 60%, #c0392b 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -120px; right: -120px;
            width: 500px; height: 500px;
            background: rgba(255,255,255,.04);
            border-radius: 50%;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -80px;
            width: 380px; height: 380px;
            background: rgba(255,255,255,.03);
            border-radius: 50%;
        }
        .hero-nav {
            padding: 1.5rem 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative; z-index: 2;
        }
        .hero-brand { display: flex; align-items: center; gap: 1rem; text-decoration: none; }
        .hero-brand img { height: 48px; border-radius: 6px; }
        .hero-brand-text { color: #fff; }
        .hero-brand-text .name { font-size: 1.25rem; font-weight: 700; letter-spacing: 1px; }
        .hero-brand-text .sub  { font-size: .7rem; opacity: .7; letter-spacing: 2px; text-transform: uppercase; }
        .hero-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4rem 2rem;
            position: relative; z-index: 2;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.9);
            font-size: .75rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            padding: .4rem 1.2rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.2);
            margin-bottom: 1.5rem;
        }
        .hero h1 {
            font-size: clamp(2.4rem, 6vw, 4rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 1.5rem;
        }
        .hero h1 span { color: rgba(255,255,255,.6); }
        .hero p.lead {
            color: rgba(255,255,255,.75);
            font-size: 1.15rem;
            max-width: 600px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }
        .btn-intranet {
            background: #fff;
            color: var(--upc-red);
            font-weight: 700;
            font-size: 1rem;
            padding: .85rem 2.2rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            transition: all .2s;
            box-shadow: 0 8px 24px rgba(0,0,0,.25);
        }
        .btn-intranet:hover {
            background: var(--upc-red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0,0,0,.35);
        }
        .btn-intranet-outline {
            background: transparent;
            color: rgba(255,255,255,.85);
            font-weight: 600;
            font-size: 1rem;
            padding: .85rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.35);
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            transition: all .2s;
        }
        .btn-intranet-outline:hover { background: rgba(255,255,255,.1); color: #fff; }
        .hero-scroll { padding: 2rem; text-align: center; position: relative; z-index: 2; }
        .hero-scroll i { color: rgba(255,255,255,.4); font-size: .85rem; }

        /* ── Stats bar ───────────────────────────────────────── */
        .stats-bar { background: #fff; border-bottom: 1px solid #e9ecef; padding: 1.5rem 3rem; }
        .stat-item { text-align: center; }
        .stat-item .num { font-size: 1.75rem; font-weight: 800; color: var(--upc-red); }
        .stat-item .lbl { font-size: .78rem; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; }

        /* ── Features ────────────────────────────────────────── */
        .section-features { padding: 6rem 0; background: #f8f9fa; }
        .feature-card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            text-align: center;
            border: 1px solid #e9ecef;
            height: 100%;
            transition: all .2s;
        }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.08); }
        .feature-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }
        .feature-card h4 { font-weight: 700; font-size: 1.1rem; margin-bottom: .75rem; }
        .feature-card p  { color: #6c757d; font-size: .9rem; line-height: 1.6; }

        /* ── Newsletter ──────────────────────────────────────── */
        .section-newsletter {
            background: linear-gradient(135deg, var(--upc-dark) 0%, var(--upc-red) 100%);
            padding: 6rem 0;
        }
        .newsletter-box {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 20px;
            padding: 3rem;
            max-width: 680px;
            margin: 0 auto;
        }
        .newsletter-box h3  { color: #fff; font-weight: 800; font-size: 1.75rem; margin-bottom: .5rem; }
        .newsletter-box p   { color: rgba(255,255,255,.7); margin-bottom: 1.75rem; }
        .newsletter-box .form-control {
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            padding: .75rem 1.25rem;
            border-radius: 8px 0 0 8px;
        }
        .newsletter-box .form-control::placeholder { color: rgba(255,255,255,.4); }
        .newsletter-box .form-control:focus {
            background: rgba(255,255,255,.15);
            border-color: rgba(255,255,255,.4);
            box-shadow: none; color: #fff;
        }
        .btn-sub {
            background: #fff; color: var(--upc-red);
            font-weight: 700; border: none;
            padding: .75rem 1.5rem;
            border-radius: 0 8px 8px 0;
            white-space: nowrap;
            cursor: pointer;
        }
        .btn-sub:hover { background: #f1f3f5; }
        .vuln-note { margin-top: .75rem; font-size: .72rem; color: rgba(255,255,255,.3); }

        /* ── Footer ──────────────────────────────────────────── */
        .site-footer { background: #111; color: rgba(255,255,255,.4); text-align: center; padding: 2rem; font-size: .8rem; }
        .site-footer a { color: rgba(255,255,255,.5); text-decoration: none; }
        .site-footer a:hover { color: #fff; }
    </style>
</head>
<body>

<!-- ════════ HERO ════════ -->
<section class="hero">
    <nav class="hero-nav">
        <a class="hero-brand" href="/">
            <img src="/assets/images/Upc.jpg" alt="UPC">
            <div class="hero-brand-text">
                <div class="name">UrbanUpC</div>
                <div class="sub">Université Paris Cité</div>
            </div>
        </a>
        <a href="/login.php" class="btn-intranet" style="font-size:.875rem; padding:.6rem 1.5rem;">
            <i class="fas fa-sign-in-alt"></i>Connexion
        </a>
    </nav>

    <div class="hero-body">
        <div>
            <div class="hero-badge">
                <i class="fas fa-shield-alt me-2"></i>Master Cybersécurité — Promotion 2025-2026
            </div>
            <h1>
                Bienvenue sur<br>
                <span>l'Intranet</span> UrbanUpC
            </h1>
            <p class="lead">
                Plateforme collaborative officielle de l'Université Paris Cité.
                Accédez à vos documents, annonces, ressources pédagogiques et
                outils de gestion en toute sécurité.
            </p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="/login.php" class="btn-intranet">
                    <i class="fas fa-lock-open"></i>Accès Intranet
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="#features" class="btn-intranet-outline">
                    <i class="fas fa-info-circle"></i>En savoir plus
                </a>
            </div>
        </div>
    </div>

    <div class="hero-scroll">
        <i class="fas fa-chevron-down fa-bounce"></i>
    </div>
</section>

<!-- ════════ STATS ════════ -->
<div class="stats-bar">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num">36</div><div class="lbl">Étudiants</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num">12</div><div class="lbl">Enseignants</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num">4</div><div class="lbl">Spécialisations</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num">2025</div><div class="lbl">Promotion</div></div></div>
        </div>
    </div>
</div>

<!-- ════════ FEATURES ════════ -->
<section class="section-features" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold" style="font-size:2rem;">Tout ce dont vous avez besoin</h2>
            <p class="text-muted">Une plateforme intégrée pour la communauté universitaire</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon bg-danger bg-opacity-10"><i class="fas fa-folder-open text-danger"></i></div>
                    <h4>Espace Documentaire</h4>
                    <p>Gérez, partagez et consultez tous vos documents de cours, travaux et ressources pédagogiques de manière centralisée.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon bg-primary bg-opacity-10"><i class="fas fa-bullhorn text-primary"></i></div>
                    <h4>Annonces &amp; Actualités</h4>
                    <p>Restez informé des dernières nouvelles du département, des événements importants et des communications officielles.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon bg-success bg-opacity-10"><i class="fas fa-users text-success"></i></div>
                    <h4>Portail Collaborateurs</h4>
                    <p>Annuaire des membres, career center, dépôt de CV, offres de stage et outils de collaboration pour toute la promotion.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon bg-warning bg-opacity-10"><i class="fas fa-shield-virus text-warning"></i></div>
                    <h4>Ressources Cyber</h4>
                    <p>Supports de cours, labs pratiques, CTF internes, et ressources spécialisées en cybersécurité offensive et défensive.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon bg-info bg-opacity-10"><i class="fas fa-code text-info"></i></div>
                    <h4>API Développeur</h4>
                    <p>Accès programmatique à vos données via notre portail API. Générez une clé depuis votre profil et intégrez vos outils.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(139,26,46,.1)"><i class="fas fa-chart-line" style="color:var(--upc-red)"></i></div>
                    <h4>Tableau de Bord</h4>
                    <p>Vue d'ensemble personnalisée de vos activités, documents récents, annonces et statistiques de la plateforme.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════ NEWSLETTER (SQLi) ════════ -->
<section class="section-newsletter" id="newsletter">
    <div class="container">
        <div class="newsletter-box text-center">
            <i class="fas fa-envelope-open-text fa-2x text-white opacity-75 mb-3"></i>
            <h3>Restez informé</h3>
            <p>Inscrivez-vous pour recevoir les actualités du Master Cybersécurité directement dans votre boîte mail.</p>

            <?php if ($subscribed): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>Inscription confirmée ! Vous recevrez nos prochaines actualités.
            </div>
            <?php elseif ($subError): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= $subError ?>
            </div>
            <?php else: ?>
            <!-- VULNERABILITY: Stored SQL Injection — champ email non sanitisé (intentionnel) -->
            <form method="POST" action="#newsletter">
                <div class="input-group">
                    <input type="text"
                           class="form-control"
                           name="email"
                           placeholder="votre.email@etu.u-paris.fr"
                           autocomplete="off">
                    <button type="submit" class="btn-sub">
                        <i class="fas fa-paper-plane me-1"></i>S'inscrire
                    </button>
                </div>
            </form>
            <div class="vuln-note">Vos données sont utilisées uniquement pour les communications du Master.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ════════ FOOTER ════════ -->
<footer class="site-footer">
    <div class="container">
        <div class="mb-2">
            <strong style="color:rgba(255,255,255,.6)">UrbanUpC</strong> —
            Université Paris Cité &nbsp;|&nbsp; Master Cybersécurité &nbsp;|&nbsp;
            <a href="/login.php">Accès Intranet</a>
        </div>
        <div>
            Apache/<?= defined('APACHE_VERSION') ? APACHE_VERSION : '2.4.49' ?> &nbsp;&middot;&nbsp;
            PHP <?= PHP_VERSION ?> &nbsp;&middot;&nbsp;
            &copy; <?= date('Y') ?> Université Paris Cité
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
