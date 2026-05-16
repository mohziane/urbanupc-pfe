<?php
// =============================================================
//  UrbanUpC — Connexion base de données (PDO)
//  Note : emulate_prepares = true intentionnellement (SQLi possible
//         malgré PDO quand on utilise query() directement)
// =============================================================

require_once __DIR__ . '/../config/db.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // emulate_prepares ON → les vraies requêtes PDO::query() restent vulnérables
            PDO::ATTR_EMULATE_PREPARES   => true,
            // Force UTF-8 au niveau protocole (fix mojibake)
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Affiche l'erreur complète — fuite d'information intentionnelle
            die('<pre>Erreur de connexion BDD : ' . $e->getMessage() . '</pre>');
        }
    }
    return $pdo;
}
