
<?php

$hote = 'localhost';
$port = '3306';
$nomBase = 'dsi_tresor';
$utilisateur = 'root';
$motDePasse = '';

try {
    $pdo = new PDO(
        "mysql:host=$hote;port=$port;dbname=$nomBase;charset=utf8mb4",
        $utilisateur,
        $motDePasse
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    die("Erreur de connexion à MySQL : " . $e->getMessage());
}