
<?php
session_start();

require_once __DIR__ . '/config/connexion.php';

// Vérifier la connexion
$idConnecte = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id']
    ?? $_SESSION['utilisateur']['id']
    ?? null;

if (!$idConnecte) {
    header('Location: login.php');
    exit;
}

function e($valeur)
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

// Vérifier les dates du filtre
$dateDebut = $_GET['debut'] ?? '';
$dateFin = $_GET['fin'] ?? '';
$erreur = '';

if (
    ($dateDebut !== '' &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) ||
    ($dateFin !== '' &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin))
) {
    $erreur = "Le format des dates est invalide.";
}

if (
    $erreur === '' &&
    $dateDebut !== '' &&
    $dateFin !== '' &&
    $dateDebut > $dateFin
) {
    $erreur = "La date de début doit précéder la date de fin.";
}

// Valeurs par défaut
$totalEquipements = 0;
$totalIncidents = 0;
$totalUtilisateurs = 0;
$totalSites = 0;
$incidentsParStatut = [];
$incidentsParMois = [];

try {
    // Statistiques générales
    $totalEquipements = (int) $pdo
        ->query("SELECT COUNT(*) FROM equipements")
        ->fetchColumn();

    $totalUtilisateurs = (int) $pdo
        ->query("SELECT COUNT(*) FROM utilisateurs")
        ->fetchColumn();

    $totalSites = (int) $pdo
        ->query("SELECT COUNT(*) FROM sites")
        ->fetchColumn();

    // Préparer le filtre de période pour les incidents
    $conditions = [];
    $parametres = [];

    if ($dateDebut !== '') {
        $conditions[] = "date_creation >= ?";
        $parametres[] = $dateDebut . ' 00:00:00';
    }

    if ($dateFin !== '') {
        $conditions[] = "date_creation < DATE_ADD(?, INTERVAL 1 DAY)";
        $parametres[] = $dateFin;
    }

    $where = count($conditions) > 0
        ? ' WHERE ' . implode(' AND ', $conditions)
        : '';

    if ($erreur === '') {
        // Nombre d'incidents pendant la période
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM incidents" . $where
        );
        $stmt->execute($parametres);
        $totalIncidents = (int) $stmt->fetchColumn();

        // Répartition par statut
        $stmt = $pdo->prepare(
            "SELECT statut, COUNT(*) AS nombre
             FROM incidents" . $where . "
             GROUP BY statut
             ORDER BY statut"
        );
        $stmt->execute($parametres);
        $incidentsParStatut = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Évolution mensuelle des incidents
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(date_creation, '%Y-%m') AS mois,
                    COUNT(*) AS nombre
             FROM incidents" . $where . "
             GROUP BY DATE_FORMAT(date_creation, '%Y-%m')
             ORDER BY mois DESC
             LIMIT 12"
        );
        $stmt->execute($parametres);
        $incidentsParMois = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Afficher les mois dans l'ordre chronologique
        $incidentsParMois = array_reverse($incidentsParMois);
    }

} catch (PDOException $ex) {
    $erreur = "Impossible de charger les statistiques. Vérifiez la structure des tables de la base d données.";
}

$libellesStatut = [
    'nouveau' => 'Nouveaux',
    'affecte' => 'Affectés',
    'en_cours' => 'En cours',
    'resolu' => 'Résolus',
    'ferme' => 'Fermés',
    'ouvert' => 'Ouverts'
];

$nomUtilisateur = trim(
    ($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? '')
);

if ($nomUtilisateur === '') {
    $nomUtilisateur = $_SESSION['email'] ?? 'Utilisateur';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et statistiques - DSI Trésor Mali</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #123b66;
            color: white;
            padding: 22px 30px;
        }

        header h1 { margin: 0 0 8px; }
        header p { margin: 0; color: #dbeafe; }

        main {
            max-width: 1200px;
            margin: 25px auto;
            padding: 24px;
        }

        .barre-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 5px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-retour { background: #475569; }
        .btn-imprimer { background: #0f766e; }

        .filtres, .section {
            background: white;
            padding: 22px;
            border-radius: 9px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .filtres form {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 15px;
        }

        .champ {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        label { font-weight: bold; }

        input[type="date"] {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        .cartes {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .carte {
            background: white;
            padding: 22px;
            border-radius: 9px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            border-left: 4px solid #2563eb;
        }

        .carte h3 {
            margin: 0 0 14px;
            font-size: 15px;
            color: #475569;
        }

        .nombre {
            font-size: 32px;
            font-weight: bold;
            color: #123b66;
        }

        .table-conteneur { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 350px;
        }

        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #eaf0f7;
            color: #123b66;
        }

        .barre-fond {
            height: 12px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            min-width: 100px;
        }

        .barre {
            height: 100%;
            background: #2563eb;
        }

        .erreur {
            padding: 12px;
            margin-bottom: 20px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 5px;
        }

        .vide { color: #64748b; }

        footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 800px) {
            .cartes { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            main { padding: 14px; }
        }

        @media (max-width: 450px) {
            .cartes { grid-template-columns: 1fr; }
        }

        @media print {
            body { background: white; }
            header { background: white; color: black; padding: 10px 0; }
            header p { color: black; }
            main { margin: 0; padding: 0; }
            .barre-actions, .filtres { display: none; }
            .carte, .section { box-shadow: none; border: 1px solid #ddd; }
            .cartes { grid-template-columns: repeat(2, 1fr); }
            footer { padding: 10px 0; }
        }
    </style>
</head>

<body>

<header>
    <h1>DSI Trésor Mali</h1>
    <p>Rapports et statistiques du système informatique</p>
</header>

<main>

    <div class="barre-actions">
        <a href="index.php" class="btn btn-retour">
            ← Tableau de bord
        </a>

        <button type="button" class="btn btn-imprimer"
                onclick="window.print()">
            Imprimer le rapport
        </button>
    </div>

    <h2>Tableau des rapports</h2>
    <p>Bienvenue, <?= e($nomUtilisateur) ?>.</p>

    <section class="filtres">
        <h3>Filtrer les incidents par période</h3>

        <form method="get" action="rapports.php">
            <div class="champ">
                <label for="debut">Date de début</label>
                <input type="date" id="debut" name="debut"
                       value="<?= e($dateDebut) ?>">
            </div>

            <div class="champ">
                <label for="fin">Date de fin</label>
                <input type="date" id="fin" name="fin"
                       value="<?= e($dateFin) ?>">
            </div>

            <button type="submit" class="btn">
                Appliquer le filtre
            </button>

            <a href="rapports.php" class="btn btn-retour">
                Réinitialiser
            </a>
        </form>
    </section>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <?php if ($erreur === ''): ?>

        <div class="cartes">
            <div class="carte">
                <h3>Équipements informatiques</h3>
                <div class="nombre"><?= $totalEquipements ?></div>
            </div>

            <div class="carte">
                <h3>Incidents de la période</h3>
                <div class="nombre"><?= $totalIncidents ?></div>
            </div>

            <div class="carte">
                <h3>Utilisateurs enregistrés</h3>
                <div class="nombre"><?= $totalUtilisateurs ?></div>
            </div>

            <div class="carte">
                <h3>Sites enregistrés</h3>
                <div class="nombre"><?= $totalSites ?></div>
            </div>
        </div>

        <section class="section">
            <h3>Répartition des incidents par statut</h3>

            <?php if (empty($incidentsParStatut)): ?>
                <p class="vide">Aucun incident pour cette période.</p>
            <?php else: ?>

                <div class="table-conteneur">
                    <table>
                        <thead>
                            <tr>
                                <th>Statut</th>
                                <th>Nombre</th>
                                <th>Proportion</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($incidentsParStatut as $ligne): ?>
                            <?php
                            $nombre = (int) $ligne['nombre'];
                            $pourcentage = $totalIncidents > 0
                                ? round(($nombre / $totalIncidents) * 100, 1)
                                : 0;
                            $statut = $ligne['statut'];
                            ?>

                            <tr>
                                <td>
                                    <?= e($libellesStatut[$statut] ?? $statut) ?>
                                </td>
                                <td><?= $nombre ?></td>
                                <td>
                                    <?= $pourcentage ?> %
                                    <div class="barre-fond">
                                        <div class="barre"
                                             style="width: <?= $pourcentage ?>%">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="section">
            <h3>Évolution mensuelle des incidents</h3>

            <?php if (empty($incidentsParMois)): ?>
                <p class="vide">Aucune donnée mensuelle disponible.</p>
            <?php else: ?>
                <div class="table-conteneur">
                    <table>
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Nombre d'incidents</th>
                                <th>Représentation</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php
                        $maximum = max(
                            array_map(
                                fn($ligne) => (int) $ligne['nombre'],
                                $incidentsParMois
                            )
                        );
                        $maximum = max(1, $maximum);
                        ?>

                        <?php foreach ($incidentsParMois as $ligne): ?>
                            <?php
                            $nombre = (int) $ligne['nombre'];
                            $largeur = ($nombre / $maximum) * 100;
                            ?>

                            <tr>
                                <td><?= e($ligne['mois']) ?></td>
                                <td><?= $nombre ?></td>
                                <td>
                                    <div class="barre-fond">
                                        <div class="barre"
                                             style="width: <?= $largeur ?>%">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <p class="vide">
            Rapport généré le <?= date('d/m/Y à H:i') ?>.
            Les totaux des équipements, utilisateurs et sites portent
            sur l'ensemble de la base ; le filtre de dates concerne les incidents.
        </p>

    <?php endif; ?>

</main>

<footer>
    DSI Trésor Mali &copy; <?= date('Y') ?>
</footer>

</body>
</html>

