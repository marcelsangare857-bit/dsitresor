

<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// Vérifier la connexion de l'utilisateur
if (
    !isset($_SESSION['id_utilisateur']) &&
    !isset($_SESSION['utilisateur_id']) &&
    !isset($_SESSION['id'])
) {
    header('Location: ../login.php');
    exit;
}

// Protection contre les injections HTML
function h($valeur)
{
    return htmlspecialchars(
        (string)($valeur ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Récupérer le texte de recherche
$recherche = trim($_GET['recherche'] ?? '');

// Construire la requête SQL
$sql = "SELECT
            e.id,
            e.code_inventaire,
            e.designation,
            e.categorie,
            e.marque,
            e.numero_serie,
            e.adresse_ip,
            e.id_site,
            e.date_acquisition,
            e.etat,
            s.nom AS nom_site
        FROM equipements e
        LEFT JOIN sites s ON e.id_site = s.id";

$params = [];

if ($recherche !== '') {
    $sql .= " WHERE
                e.code_inventaire LIKE :q1
                OR e.designation LIKE :q2
                OR e.categorie LIKE :q3
                OR e.marque LIKE :q4
                OR e.numero_serie LIKE :q5
                OR e.adresse_ip LIKE :q6
                OR s.nom LIKE :q7";

    $mot = '%' . $recherche . '%';

    $params = [
        'q1' => $mot,
        'q2' => $mot,
        'q3' => $mot,
        'q4' => $mot,
        'q5' => $mot,
        'q6' => $mot,
        'q7' => $mot
    ];
}

$sql .= " ORDER BY e.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($equipements);

// Messages de confirmation
$message = $_GET['message'] ?? '';

$messages = [
    'ajoute' => 'Équipement ajouté avec succès.',
    'modifie' => 'Équipement modifié avec succès.'
];

// Traduire les états de la base de données
$libellesEtat = [
    'fonctionnel' => 'Fonctionnel',
    'en_panne' => 'En panne',
    'en_maintenance' => 'En maintenance',
    'reforme' => 'Réformé'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Gestion des équipements - DSI Trésor Mali</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 25px;
            background: #f1f4f8;
            color: #222;
        }

        .conteneur {
            max-width: 1450px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        h1 {
            color: #174a75;
            margin-top: 0;
        }

        .sous-titre {
            color: #666;
        }

        .barre-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 25px 0;
        }

        .form-recherche {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        input[type="text"] {
            padding: 10px;
            width: 260px;
            max-width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .bouton {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 4px;
            background: #1769aa;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .bouton-vert {
            background: #218838;
        }

        .bouton-gris {
            background: #666;
        }

        .bouton:hover {
            opacity: 0.9;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            white-space: nowrap;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 11px;
            text-align: left;
        }

        th {
            background: #174a75;
            color: white;
        }

        tbody tr:nth-child(even) {
            background: #f7f9fb;
        }

        .etat {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .fonctionnel {
            background: #d4edda;
            color: #155724;
        }

        .en_panne {
            background: #f8d7da;
            color: #721c24;
        }

        .en_maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .reforme {
            background: #e2e3e5;
            color: #383d41;
        }

        .lien-modifier {
            color: #1769aa;
            text-decoration: none;
            font-weight: bold;
        }

        .lien-modifier:hover {
            text-decoration: underline;
        }

        .message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 4px;
            background: #d4edda;
            color: #155724;
        }

        .compteur {
            margin-top: 20px;
            font-size: 16px;
        }

        .aucun {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>

<body>

<div class="conteneur">

    <h1>Gestion des équipements informatiques</h1>

    <p class="sous-titre">
        Direction des Systèmes d'Information — Trésor Mali
    </p>

    <?php if (isset($messages[$message])): ?>
        <div class="message">
            <?= h($messages[$message]) ?>
        </div>
    <?php endif; ?>

    <div class="barre-actions">

        <form method="GET" action="liste.php" class="form-recherche">
            <input
                type="text"
                name="recherche"
                placeholder="Rechercher un équipement..."
                value="<?= h($recherche) ?>"
            >

            <button type="submit" class="bouton">
                Rechercher
            </button>

            <?php if ($recherche !== ''): ?>
                <a href="liste.php" class="bouton bouton-gris">
                    Réinitialiser
                </a>
            <?php endif; ?>
        </form>

        <a href="ajouter.php" class="bouton bouton-vert">
            + Ajouter un équipement
        </a>

        <a href="../index.php" class="bouton bouton-gris">
            Tableau de bord
        </a>

    </div>

    <div class="table-responsive">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>N° inventaire</th>
                    <th>Équipement</th>
                    <th>Catégorie</th>
                    <th>Marque</th>
                    <th>N° série</th>
                    <th>Adresse IP</th>
                    <th>Site</th>
                    <th>Date acquisition</th>
                    <th>État</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($equipements)): ?>

                <tr>
                    <td colspan="11" class="aucun">
                        Aucun équipement trouvé.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($equipements as $e): ?>

                    <?php
                    $etat = $e['etat'] ?? 'fonctionnel';
                    $libelle = $libellesEtat[$etat] ?? $etat;
                    ?>

                    <tr>
                        <td><?= h($e['id']) ?></td>

                        <td><?= h($e['code_inventaire']) ?></td>

                        <td><?= h($e['designation']) ?></td>

                        <td><?= h($e['categorie']) ?></td>

                        <td><?= h($e['marque']) ?></td>

                        <td><?= h($e['numero_serie']) ?></td>

                        <td><?= h($e['adresse_ip']) ?></td>

                        <td>
                            <?= h($e['nom_site'] ?? 'Non affecté') ?>
                        </td>

                        <td><?= h($e['date_acquisition']) ?></td>

                        <td>
                            <span class="etat <?= h($etat) ?>">
                                <?= h($libelle) ?>
                            </span>
                        </td>

                        <td>
                            <a
                                class="lien-modifier"
                                href="modifier.php?id=<?= (int)$e['id'] ?>"
                            >
                                Modifier
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

    <p class="compteur">
        Nombre d'équipements affichés :
        <strong><?= $total ?></strong>
    </p>

</div>

</body>
</html>
