<?php
// =============================================================
//  UrbanUpC Intranet — Université Paris Cité
//  Configuration globale de l'application
// =============================================================
mb_internal_encoding('UTF-8');

define('APP_NAME',    'UrbanUpC Intranet');
define('APP_COMPANY', 'Université Paris Cité');
define('APP_VERSION', '3.2.1');
define('APP_ENV',     getenv('APP_ENV') ?: 'production');

// Base URL
define('BASE_URL',    '');
define('UPLOAD_DIR',  '/var/www/html/corpnet/uploads/');
define('UPLOAD_URL',  '/uploads/');

// Session
define('SESSION_LIFETIME', 3600); // 1 heure

// Rôles
define('ROLE_USER',    'user');
define('ROLE_MANAGER', 'manager');
define('ROLE_ADMIN',   'admin');
