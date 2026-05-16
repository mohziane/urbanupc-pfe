<?php
// =============================================================
//  CorpNet — Configuration base de données
//  Credentials en clair dans le fichier de config (intentionnel)
// =============================================================

define('DB_HOST', getenv('MYSQL_HOST') ?: 'db');
define('DB_NAME', getenv('MYSQL_DB')   ?: 'corpnet_db');
define('DB_USER', getenv('MYSQL_USER') ?: 'corpnet_app');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: 'C0rpN3t@2024!');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');
