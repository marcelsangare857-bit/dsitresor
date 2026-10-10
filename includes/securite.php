
<?php
// Fichier : includes/securite.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie que l'utilisateur est connecté.
 */
function exigerConnexion(): void
{
    $id = $_SESSION['id_utilisateur']
        ?? $_SESSION['utilisateur_id']
        ?? $_SESSION['id']
        ?? null;

    if (!$id) {
        header('Location: /dsi_tresor/login.php');
        exit;
    }
}

/**
 * Retourne le rôle de l'utilisateur connecté.
 */
function roleConnecte(): string
{
    return (string) (
        $_SESSION['role']
        ?? $_SESSION['utilisateur_role']
        ?? ''
    );
}

/**
 * Vérifie si l'utilisateur possède un rôle autorisé.
 */
function exigerRole(array $rolesAutorises): void
{
    exigerConnexion();

    if (!in_array(roleConnecte(), $rolesAutorises, true)) {
        http_response_code(403);
        exit(
            'Accès refusé : vous n’avez pas les permissions nécessaires.'
        );
    }
}

/**
 * Protège l'affichage des données HTML.
 */
function securiteHTML($valeur): string
{
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Crée un jeton CSRF pour les formulaires.
 */
function jetonCSRF(): string
{
    if (empty($_SESSION['jeton_csrf'])) {
        $_SESSION['jeton_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['jeton_csrf'];
}

/**
 * Vérifie le jeton CSRF reçu par un formulaire POST.
 */
function verifierCSRF(): void
{
    $recu = $_POST['jeton_csrf'] ?? '';
    $attendu = $_SESSION['jeton_csrf'] ?? '';

    if (
        !is_string($recu) ||
        !is_string($attendu) ||
        $attendu === '' ||
        !hash_equals($attendu, $recu)
    ) {
        http_response_code(403);
        exit('Formulaire refusé. Rechargez la page et réessayez.');
    }
}
