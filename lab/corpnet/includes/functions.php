<?php
// =============================================================
//  UrbanUpC — Fonctions utilitaires
// =============================================================

/**
 * Formate une date en français
 */
function formatDate(string $date, string $format = 'd/m/Y H:i'): string {
    return date($format, strtotime($date));
}

/**
 * Formate une taille de fichier
 */
function formatSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' Ko';
    return $bytes . ' octets';
}

/**
 * Badge de classification
 */
function classificationBadge(string $classification): string {
    $badges = [
        'public'       => '<span class="badge bg-success">Public</span>',
        'internal'     => '<span class="badge bg-info">Interne</span>',
        'confidential' => '<span class="badge bg-warning text-dark">Confidentiel</span>',
        'secret'       => '<span class="badge bg-danger">Secret</span>',
    ];
    return $badges[$classification] ?? '<span class="badge bg-secondary">Inconnu</span>';
}

/**
 * Badge de rôle utilisateur
 */
function roleBadge(string $role): string {
    $badges = [
        'user'    => '<span class="badge bg-secondary">Utilisateur</span>',
        'manager' => '<span class="badge bg-primary">Manager</span>',
        'admin'   => '<span class="badge bg-danger">Administrateur</span>',
    ];
    return $badges[$role] ?? '<span class="badge bg-secondary">' . htmlspecialchars($role) . '</span>';
}

/**
 * Icône de fichier selon extension
 */
function fileIcon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf'  => 'fa-file-pdf text-danger',
        'xlsx' => 'fa-file-excel text-success',
        'docx' => 'fa-file-word text-primary',
        'pptx' => 'fa-file-powerpoint text-warning',
        'txt'  => 'fa-file-alt text-secondary',
        'zip'  => 'fa-file-archive text-dark',
        'png'  => 'fa-file-image text-info',
        'jpg'  => 'fa-file-image text-info',
    ];
    return 'fas ' . ($icons[$ext] ?? 'fa-file text-secondary');
}

/**
 * Pagination helper
 */
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = (int) ceil($total / $perPage);
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => ($currentPage - 1) * $perPage,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

/**
 * Réponse JSON API
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Génère un token CSRF (intentionnellement faible — basé sur session_id)
 * Vulnérable si session_id prévisible
 */
function csrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        // Token dérivé du session_id — pas de génération aléatoire sécurisée
        $_SESSION['csrf_token'] = md5(session_id() . 'meridian_csrf_salt');
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide un token CSRF (vérification désactivée sur certains endpoints — intentionnel)
 */
function validateCsrf(?string $token): bool {
    // Note : la validation est omise sur les endpoints API (cf. api/*.php)
    return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
}

/**
 * Retourne un fragment SQL booléen pour filtrer par visibilité selon le rôle.
 * Admin → voit tout (1=1). Manager → 'all'|'manager'. User → 'all'|'user'.
 * N'utilise que des chaînes codées en dur — aucun risque d'injection SQL.
 */
function visibilityClause(string $role, string $alias = ''): string {
    $col = $alias ? "{$alias}.visibility" : 'visibility';
    if ($role === 'admin')   return '1=1';
    if ($role === 'manager') return "$col IN ('all','manager')";
    return "$col IN ('all','user')";
}

/**
 * Badge HTML pour une valeur de visibilité.
 */
function visibilityBadge(string $vis): string {
    $map = [
        'all'     => '<span class="badge bg-secondary">Tous</span>',
        'admin'   => '<span class="badge bg-danger">Admins</span>',
        'manager' => '<span class="badge bg-primary">Managers</span>',
        'user'    => '<span class="badge bg-info text-dark">Étudiants</span>',
    ];
    return $map[$vis] ?? '<span class="badge bg-secondary">' . htmlspecialchars($vis) . '</span>';
}
