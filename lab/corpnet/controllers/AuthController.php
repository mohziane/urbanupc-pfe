<?php
// =============================================================
//  AuthController — Gère login / logout via le Front Controller
//  Vulnérabilités intentionnelles préservées :
//   - Open Redirect sur le paramètre ?redirect=
//   - Pas de CSRF token
// =============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class AuthController {

    public function showLogin(): void {
        if (isAuthenticated()) {
            header('Location: /feed.php');
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        $error   = '';
        $success = '';
        require __DIR__ . '/../views/login.php';
    }

    public function handleLogin(): void {
        if (isAuthenticated()) {
            header('Location: /feed.php');
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        $error   = '';
        $success = '';

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

        require __DIR__ . '/../views/login.php';
    }

    public function logout(): void {
        logout(); // fonction de auth.php — redirige vers /index.php
    }
}
