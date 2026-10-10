

<?php
require_once __DIR__ . '/config/connexion.php';

$email = 'admin@tresor.ml';
$nouveauMotDePasse = '123';

$hash = password_hash(
    $nouveauMotDePasse,
    PASSWORD_DEFAULT
);

$sql = "UPDATE utilisateurs
        SET mot_de_passe = ?,
            actif = 1
        WHERE email = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$hash, $email]);

if ($stmt->rowCount() > 0) {
    echo "Mot de passe réinitialisé. Compte activé.";
} else {
    echo "Aucune modification. Vérifiez si le compte existe déjà et si le mot de passe était déjà identique.";
}
?>