<?php
// =============================================================
//  DashboardController — Tableau de bord via Front Controller
//  Route : GET /dashboard
//  La page legacy /dashboard.php reste fonctionnelle en parallèle
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class DashboardController {

    public function index(): void {
        requireAuth();
        header('Content-Type: text/html; charset=utf-8');

        $user = currentUser();
        $pdo  = getDB();

        // Stats rapides
        $stats  = [];
        $visDoc = visibilityClause($user['role']);
        $stats['docs_total']  = $pdo->query("SELECT COUNT(*) FROM documents WHERE $visDoc")->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE owner_id = ?");
        $stmt->execute([$user['id']]);
        $stats['docs_mine']   = $stmt->fetchColumn();
        $stats['users_total'] = $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
        $stats['logs_today']  = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();

        // Dernières activités (scope selon le rôle)
        if (in_array($user['role'], ['admin', 'manager'])) {
            $recentLogs = $pdo->query(
                "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8"
            )->fetchAll();
            $logScope = 'global';
        } else {
            $stmt3 = $pdo->prepare(
                "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 8"
            );
            $stmt3->execute([$user['id']]);
            $recentLogs = $stmt3->fetchAll();
            $logScope = 'personal';
        }

        // Annonces (filtrées par visibilité)
        $visAnn        = visibilityClause($user['role'], 'a');
        $announcements = $pdo->query(
            "SELECT a.*, u.first_name, u.last_name FROM announcements a
             JOIN users u ON a.author_id = u.id
             WHERE $visAnn
             ORDER BY a.pinned DESC, a.created_at DESC LIMIT 4"
        )->fetchAll();

        // Mes derniers documents
        $stmt2 = $pdo->prepare(
            "SELECT * FROM documents WHERE owner_id = ? ORDER BY updated_at DESC LIMIT 5"
        );
        $stmt2->execute([$user['id']]);
        $myDocs = $stmt2->fetchAll();

        logAudit($user['id'], $user['username'], 'VIEW_DASHBOARD', '/dashboard');

        // Rendu via layout
        $pageTitle = 'Tableau de bord';
        ob_start();
        require __DIR__ . '/../views/dashboard.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layout.php';
    }
}
