
<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id_utilisateur'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/connexion.php';

// Fonction de protection contre les injections HTML
function h($valeur) {
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

$equipements = 0;
$incidents = 0;
$techniciens = 0;
$sites = 0;
$derniersIncidents = [];
$erreur = '';

try {
    // Nombre total d'équipements
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM equipements"
    );
    $equipements = (int) $stmt->fetchColumn();

    // Incidents non résolus
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM incidents
         WHERE LOWER(statut) NOT IN ('resolu', 'résolu', 'ferme', 'fermé')"
    );
    $incidents = (int) $stmt->fetchColumn();

    // Techniciens actifs
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM utilisateurs
         WHERE role = 'technicien' AND actif = 1"
    );
    $techniciens = (int) $stmt->fetchColumn();

    // Sites actifs
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM sites
         WHERE statut = 'actif' OR actif = 1"
    );
    $sites = (int) $stmt->fetchColumn();

    // Derniers incidents
    $stmt = $pdo->query(
        "SELECT * FROM incidents
         ORDER BY id_incident DESC
         LIMIT 5"
    );
    $derniersIncidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Afficher un message sans révéler les détails de la base
    $erreur = "Certaines statistiques ne sont pas disponibles. Vérifiez les tables et les colonnes de la base de données.";
}

// Nom de l'utilisateur connecté
$nomUtilisateur = $_SESSION['nom_utilisateur'] ?? 'Utilisateur';

// Rôle éventuel
$roleUtilisateur = $_SESSION['role'] ?? 'Utilisateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSI Trésor Mali - Tableau de bord</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #123c69;
            color: white;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        header h1 {
            margin: 0;
            font-size: 23px;
        }

        header p {
            margin: 6px 0 0;
            font-size: 13px;
        }

        .deconnexion {
            background: #dc2626;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
        }

        .conteneur {
            max-width: 1200px;
            margin: 25px auto;
            padding: 0 18px;
        }

        .bienvenue {
            margin-bottom: 25px;
        }

        .statistiques {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(210px, 1fr)
            );
            gap: 18px;
        }

        .carte {
            background: white;
            padding: 22px;
            border-radius: 9px;
            box-shadow: 0 2px 8px #00000010;
        }

        .carte h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #475569;
        }

        .nombre {
            font-size: 32px;
            font-weight: bold;
            color: #123c69;
        }

        .modules {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(190px, 1fr)
            );
            gap: 15px;
            margin-top: 28px;
        }

        .module {
            display: block;
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #123c69;
            font-weight: bold;
            box-shadow: 0 2px 8px #00000010;
            border-left: 4px solid #2563eb;
        }

        .module:hover {
            background: #eaf2ff;
        }

        .section-titre {
            margin-top: 35px;
        }

        .table-conteneur {
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            margin-top: 15px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #eaf0f8;
        }

        .message-erreur {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }
    </style>
</head>

<body>

<header>
    <div>
        <h1>DSI Trésor Mali</h1>
        <p>Gestion du parc informatique et des incidents</p>
    </div>

    <div>
        <span>
            <?= h($nomUtilisateur) ?>
            (<?= h($roleUtilisateur) ?>)
        </span>
        &nbsp;
        <a class="deconnexion" href="deconnexion.php">
            Déconnexion
        </a>
    </div>
</header>

<main class="conteneur">

    <section class="bienvenue">
        <h2>Tableau de bord</h2>
        <p>Bienvenue dans le système de gestion informatique de la DSI Trésor.</p>
    </section>

    <?php if ($erreur !== ''): ?>
        <div class="message-erreur">
            <?= h($erreur) ?>
        </div>
    <?php endif; ?>

    <section class="statistiques">

        <div class="carte">
            <h3>Équipements informatiques</h3>
            <div class="nombre"><?= $equipements ?></div>
        </div>

        <div class="carte">
            <h3>Incidents à traiter</h3>
            <div class="nombre"><?= $incidents ?></div>
        </div>

        <div class="carte">
            <h3>Techniciens actifs</h3>
            <div class="nombre"><?= $techniciens ?></div>
        </div>

        <div class="carte">
            <h3>Sites actifs</h3>
            <div class="nombre"><?= $sites ?></div>
        </div>

    </section>

    <h2 class="section-titre">Modules de l'application</h2>

    <section class="modules">
        <a class="module" href="equipements.php">
            Parc informatique
        </a>

        <a class="module" href="incidents.php">
            Gestion des incidents
        </a>

        <a class="module" href="interventions.php">
            Interventions techniques
        </a>

        <a class="module" href="utilisateurs.php">
            Utilisateurs
        </a>

        <a class="module" href="sites.php">
            Gestion des sites
        </a>

        <a class="module" href="rapports.php">
            Rapports
        </a>
    </section>

    <h2 class="section-titre">Derniers incidents</h2>

    <div class="table-conteneur">
        <table>
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Description</th>
                    <th>Statut</th>
                </tr>
            </thead>

            <tbody>
            <?php if (count($derniersIncidents) > 0): ?>

                <?php foreach ($derniersIncidents as $incident): ?>
                    <tr>
                        <td>
                            <?= h($incident['id_incident'] ?? '') ?>
                        </td>
                        <td>
                            <?= h($incident['description'] ?? $incident['titre'] ?? '') ?>
                        </td>
                        <td>
                            <?= h($incident['statut'] ?? '') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="3">Aucun incident à afficher.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<footer>
    DSI Trésor Mali — Gestion du système d'information
</footer>

</body>
</html>
