
<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// 1. Vérifier la connexion de l'utilisateur
$idConnecte = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id']
    ?? $_SESSION['utilisateur']['id']
    ?? null;

if (!$idConnecte) {
    header('Location: ../login.php');
    exit;
}

// 2. Réserver cette action à l'administrateur
$roleConnecte = $_SESSION['role']
    ?? $_SESSION['utilisateur']['role']
    ?? '';

if ($roleConnecte !== 'administrateur') {
    http_response_code(403);
    exit("Accès refusé : seuls les administrateurs peuvent modifier le statut.");
}

// 3. Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: liste.php');
    exit;
}

// 4. Vérifier le jeton CSRF
$csrfRecu = $_POST['csrf'] ?? '';
$csrfSession = $_SESSION['csrf'] ?? '';

if (
    !is_string($csrfRecu) ||
    !is_string($csrfSession) ||
    $csrfSession === '' ||
    !hash_equals($csrfSession, $csrfRecu)
) {
    http_response_code(403);
    exit("Requête invalide. Rechargez la liste des utilisateurs et réessayez.");
}

// 5. Vérifier l'identifiant
$idCible = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$idCible || $idCible < 1) {
    header('Location: liste.php');
    exit;
}

// 6. Empêcher de modifier son propre statut
if ((int) $idCible === (int) $idConnecte) {
    exit("Action refusée : vous ne pouvez pas désactiver votre propre compte.");
}

try {
    // 7. Charger le compte cible
    $stmt = $pdo->prepare(
        "SELECT id, role, actif
         FROM utilisateurs
         WHERE id = ?"
    );
    $stmt->execute([$idCible]);
    $cible = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cible) {
        header('Location: liste.php');
        exit;
    }

    // 8. Calculer le nouveau statut
    $nouvelActif = ((int) $cible['actif'] === 1) ? 0 : 1;

    // 9. Ne pas désactiver le dernier administrateur actif
    if (
        $nouvelActif === 0 &&
        $cible['role'] === 'administrateur' &&
        (int) $cible['actif'] === 1
    ) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM utilisateurs
             WHERE role = 'administrateur'
               AND actif = 1
               AND id <> ?"
        );
        $stmt->execute([$idCible]);

        if ((int) $stmt->fetchColumn() === 0) {
            exit(
                "Action refusée : impossible de désactiver le dernier administrateur actif."
            );
        }
    }

    // 10. Mettre à jour le statut
    $stmt = $pdo->prepare(
        "UPDATE utilisateurs
         SET actif = ?
         WHERE id = ?"
    );
    $stmt->execute([$nouvelActif, $idCible]);

    // 11. Retourner à la liste
    header('Location: liste.php?message=statut');
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    exit("Une erreur est survenue lors de la mise à jour du statut.");
}
?>
