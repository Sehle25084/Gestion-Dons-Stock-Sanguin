<?php
// ════════════════════════════════════════════════════════
// CONFIGURATION BASE DE DONNÉES — E-Sang
// Fichier requis par toutes les pages du projet
// ════════════════════════════════════════════════════════

// ── Paramètres de connexion ──
$db_host = '127.0.0.1';
$db_port = '3306';
$db_user = 'root';
$db_pass = '';  // Mot de passe par défaut WAMP

// ── Base principale : gestion du sang ──
$db_name = 'db_gestion_sang_pi2';

// ── Base registre national (vérification identité) ──
$db_registre = 'db_registre_national';

// ── Options PDO ──
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

// ── Connexion à la base principale ──
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        $options
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base principale ($db_name) : " . $e->getMessage());
}

// ── Connexion au registre national ──
try {
    $pdo_registre = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_registre;charset=utf8mb4",
        $db_user,
        $db_pass,
        $options
    );
} catch (PDOException $e) {
    die("Erreur de connexion au registre national ($db_registre) : " . $e->getMessage());
}
