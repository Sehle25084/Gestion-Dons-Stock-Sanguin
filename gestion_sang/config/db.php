<?php
// config/db.php

$host     = "localhost";
$user     = "root";
$password = "";

try {
    // Connexion à la base principale
    $pdo = new PDO(
        "mysql:host=$host;dbname=db_gestion_sang;charset=utf8mb4",
        $user,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Connexion au registre national
    $pdo_registre = new PDO(
        "mysql:host=$host;dbname=db_registre_national;charset=utf8mb4",
        $user,
        $password
    );
    $pdo_registre->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
