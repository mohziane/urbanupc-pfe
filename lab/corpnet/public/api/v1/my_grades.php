<?php
// =============================================================
//  API v1 — Relevé de notes étudiant
//  Endpoint : GET /api/v1/my_grades.php?api_key=XXX&student_id=YYY
//
//  VULNERABILITY: BOLA (Broken Object Level Authorization)
//   - L'api_key est validée : elle doit appartenir à un compte actif.
//   - MAIS : on ne vérifie jamais que api_key.owner_id === student_id.
//   - L'étudiant A peut donc lire les données de l'étudiant B
//     en passant sa propre clé valide + l'id de B.
//   - Pattern d'attaque :
//     GET /api/v1/my_grades.php?api_key=<clé_A>&student_id=<id_B>
// =============================================================
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 1.0');

$pdo = getDB();

$apiKey    = trim($_GET['api_key']    ?? '');
$studentId = (int)($_GET['student_id'] ?? 0);

if (!$apiKey || !$studentId) {
    jsonResponse([
        'error'   => 'Paramètres manquants.',
        'usage'   => 'GET /api/v1/my_grades.php?api_key=<key>&student_id=<id>',
        'example' => '/api/v1/my_grades.php?api_key=abc123&student_id=5',
    ], 400);
}

// ── Étape 1 : valider la clé API ───────────────────────────────────────────
// La clé doit exister et appartenir à un compte actif.
$stmt = $pdo->prepare(
    "SELECT id, username, first_name, last_name FROM users WHERE api_key = ? AND active = 1 LIMIT 1"
);
$stmt->execute([$apiKey]);
$keyOwner = $stmt->fetch();

if (!$keyOwner) {
    jsonResponse(['error' => 'Clé API invalide ou compte inactif.'], 401);
}

// ── Étape 2 : récupérer l'étudiant demandé ─────────────────────────────────
// VULNERABILITY: BOLA — $keyOwner['id'] n'est PAS comparé à $studentId.
// N'importe quelle clé valide permet d'interroger n'importe quel student_id.
$stmt2 = $pdo->prepare(
    "SELECT id, username, first_name, last_name, email, department FROM users
     WHERE id = ? AND active = 1"
);
$stmt2->execute([$studentId]);
$student = $stmt2->fetch();

if (!$student) {
    jsonResponse(['error' => 'Étudiant introuvable.'], 404);
}

// ── Audit ──────────────────────────────────────────────────────────────────
// On log l'identité du propriétaire de la clé (requérant) et l'id demandé
$pdo->prepare(
    "INSERT INTO audit_logs (user_id, username, action, resource, ip_address, user_agent, status, details)
     VALUES (?, ?, 'API_GET_GRADES', '/api/v1/my_grades.php', ?, ?, 'success', ?)"
)->execute([
    $keyOwner['id'],
    $keyOwner['username'],
    $_SERVER['REMOTE_ADDR']     ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    "student_id=$studentId key_owner_id={$keyOwner['id']}",
]);

// ── Génération des notes (déterministes par username pour cohérence) ────────
srand(crc32($student['username'] . '2025'));
$courseCatalog = [
    ['course' => "Sécurité des Systèmes d'Information",    'coef' => 6],
    ['course' => 'Réseaux et Protocoles Avancés',          'coef' => 4],
    ['course' => 'Développement Web Full Stack',           'coef' => 5],
    ['course' => 'Cryptographie Appliquée',                'coef' => 4],
    ['course' => 'Gestion de Projet Informatique',         'coef' => 3],
    ['course' => 'Bases de Données Avancées',              'coef' => 4],
    ['course' => "Architecture des Systèmes d'Information",'coef' => 3],
];
$grades = [];
$weightedSum = 0;
$totalCoef   = 0;
foreach ($courseCatalog as $c) {
    $grade = round(rand(80, 200) / 10, 1);
    $grades[] = ['course' => $c['course'], 'grade' => $grade, 'coef' => $c['coef']];
    $weightedSum += $grade * $c['coef'];
    $totalCoef   += $c['coef'];
}
$average = round($weightedSum / $totalCoef, 2);

jsonResponse([
    'api_version' => '1.0',
    'requested_by' => [
        'user_id'  => $keyOwner['id'],
        'username' => $keyOwner['username'],
    ],
    'student' => [
        'id'         => $student['id'],
        'username'   => $student['username'],
        'name'       => $student['first_name'] . ' ' . $student['last_name'],
        'email'      => $student['email'],
        'department' => $student['department'],
    ],
    'transcript' => [
        'semester'      => 'S2 — 2025/2026',
        'grades'        => $grades,
        'weighted_avg'  => $average,
        'mention'       => $average >= 16 ? 'Très bien' : ($average >= 14 ? 'Bien' : ($average >= 12 ? 'Assez bien' : 'Passable')),
        'ects_validated' => $average >= 10 ? array_sum(array_column($courseCatalog, 'coef')) : 0,
    ],
]);
