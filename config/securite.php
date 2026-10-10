
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier la connexion
function verifierConnexion(): void
{
    if (empty($_SESSION['id_utilisateur'])) {
        header('Location: login.php');
        exit;
    }
}

// Vérifier le rôle
function verifierRole(array $rolesAutorises): void
{
    verifierConnexion();

    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, $rolesAutorises, true)) {
        http_response_code(403);
        exit('Accès interdit : vous n’avez pas les autorisations nécessaires.');
    }
}

// Enregistrer une action dans le journal
function journaliser(PDO $pdo, string $action, string $description = ''): void
{
    $idUtilisateur = $_SESSION['id_utilisateur'] ?? null;
    $adresseIP = $_SERVER['REMOTE_ADDR'] ?? null;

    $sql = "INSERT INTO journal_securite
            (id_utilisateur, action, description, adresse_ip)
            VALUES (?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idUtilisateur,
        $action,
        $description,
        $adresseIP
    ]);
}
