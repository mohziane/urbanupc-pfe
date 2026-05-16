<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$user = currentUser();
?>
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block corpnet-sidebar">
    <div class="position-sticky pt-3">

        <div class="sidebar-section-title">PRINCIPAL</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'feed.php' ? 'active' : '' ?>"
                   href="/feed.php">
                    <i class="fas fa-stream me-2"></i>Mur d'actualités
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
                   href="/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'documents.php' ? 'active' : '' ?>"
                   href="/documents.php">
                    <i class="fas fa-folder-open me-2"></i>Documents
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'announcements.php' ? 'active' : '' ?>"
                   href="/announcements.php">
                    <i class="fas fa-bullhorn me-2"></i>Annonces
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'depot.php' ? 'active' : '' ?>"
                   href="/depot.php">
                    <i class="fas fa-exchange-alt me-2"></i>Dépôt de fichiers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'resources.php' ? 'active' : '' ?>"
                   href="/resources.php">
                    <i class="fas fa-book-open me-2"></i>Ressources étudiantes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'career.php' ? 'active' : '' ?>"
                   href="/career.php">
                    <i class="fas fa-briefcase me-2"></i>Career Center
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'agenda.php' ? 'active' : '' ?>"
                   href="/agenda.php">
                    <i class="fas fa-calendar-alt me-2"></i>Agenda
                </a>
            </li>

        </ul>

        <div class="sidebar-section-title mt-3">COLLABORATEURS</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'search.php' ? 'active' : '' ?>"
                   href="/search.php">
                    <i class="fas fa-search me-2"></i>Annuaire
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>"
                   href="/profile.php">
                    <i class="fas fa-user me-2"></i>Mon profil
                </a>
            </li>
        </ul>

        <div class="sidebar-section-title mt-3">SERVICES</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'services.php' ? 'active' : '' ?>"
                   href="/services.php">
                    <i class="fas fa-th-large me-2"></i>Catalogue de services
                </a>
            </li>
        </ul>

        <div class="sidebar-section-title mt-3">ESPACE FORMATION</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link sidebar-link" href="/maformation/" target="_self">
                    <i class="fas fa-graduation-cap me-2"></i>MaFormation
                    <small class="d-block text-muted ms-4" style="font-size:.7rem">Portail étudiant</small>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link" href="/candidat/" target="_self">
                    <i class="fas fa-file-signature me-2"></i>MaCandidature
                    <small class="d-block text-muted ms-4" style="font-size:.7rem">Espace candidat</small>
                </a>
            </li>
        </ul>

        <?php if (in_array($user['role'], ['manager', 'admin'])): ?>
        <div class="sidebar-section-title mt-3">ADMINISTRATION</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'admin.php' ? 'active' : '' ?>"
                   href="/admin.php">
                    <i class="fas fa-users-cog me-2"></i>Utilisateurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'admin-services.php' ? 'active' : '' ?>"
                   href="/admin-services.php">
                    <i class="fas fa-cogs me-2"></i>Gestion services
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>"
                   href="/settings.php">
                    <i class="fas fa-cog me-2"></i>Paramètres
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <div class="sidebar-section-title mt-3">SYSTÈME</div>
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link sidebar-link text-danger" href="/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </li>
        </ul>

        <!-- Infos serveur — fuite d'info intentionnelle -->
        <div class="sidebar-footer">
            <div class="small text-muted opacity-75">
                <div>Apache/<?= defined('APACHE_VERSION') ? APACHE_VERSION : '2.4.49' ?></div>
                <div>PHP <?= PHP_VERSION ?></div>
                <div><?= date('H:i') ?> — <?= htmlspecialchars($user['username']) ?></div>
            </div>
        </div>

    </div>
</nav>
