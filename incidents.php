
<?php
session_start();

require_once __DIR__ . '/config/connexion.php';

// Vérifier la connexion de l'utilisateur
if (empty($_SESSION['id_utilisateur'])) {
    header('Location: login.php');
    exit;
}

function h($valeur): string {
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$erreur = '';

// Enregistrer un nouvel incident
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'ajouter') {
            $titre = trim($_POST['titre'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priorite = $_POST['priorite'] ?? 'normale';
            $idEquipement = !empty($_POST['id_equipement'])
                ? (int)$_POST['id_equipement'] : null;
            $idSite = !empty($_POST['id_site'])
                ? (int)$_POST['id_site'] : null;
            $technicien = trim($_POST['technicien'] ?? '');

            $prioritesAutorisees = ['faible', 'normale', 'haute', 'critique'];

            if ($titre === '') {
                throw new Exception("Le titre de l'incident est obligatoire.");
            }

            if (!in_array($priorite, $prioritesAutorisees, true)) {
                throw new Exception("La priorité choisie est invalide.");
            }

            $numero = 'INC-' . date('Ymd-His');

            $sql = "INSERT INTO incidents
                    (numero_incident, titre, description, priorite,
                     statut, id_equipement, id_site, technicien,
                     date_declaration)
                    VALUES
                    (:numero, :titre, :description, :priorite,
                     'nouveau', :equipement, :site, :technicien,
                     NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'numero' => $numero,
                'titre' => $titre,
                'description' => $description,
                'priorite' => $priorite,
                'equipement' => $idEquipement,
                'site' => $idSite,
                'technicien' => $technicien
            ]);

            $message = "Incident enregistré : " . $numero;
        }

        // Modifier le statut d'un incident
        if ($action === 'statut') {
            $numero = trim($_POST['numero_incident'] ?? '');
            $statut = $_POST['statut'] ?? '';

            $statutsAutorises = [
                'nouveau', 'en_cours', 'en_attente', 'resolu', 'ferme'
            ];

            if (!in_array($statut, $statutsAutorises, true)) {
                throw new Exception("Le statut choisi est invalide.");
            }

            $sql = "UPDATE incidents
                    SET statut = :statut,
                        date_resolution = CASE
                            WHEN :statut_date = 'resolu'
                            THEN NOW()
                            ELSE date_resolution
                        END
                    WHERE numero_incident = :numero";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'statut' => $statut,
                'statut_date' => $statut,
                'numero' => $numero
            ]);

            $message = "Statut de l'incident mis à jour.";
        }

    } catch (Throwable $e) {
        $erreur = $e instanceof PDOException
            ? "Erreur lors de l'enregistrement. Vérifiez les colonnes de la table incidents."
            : $e->getMessage();

        error_log($e->getMessage());
    }
}

// Récupérer les équipements
$equipements = $pdo->query(
    "SELECT id, designation FROM equipements ORDER BY designation"
)->fetchAll();

// Récupérer les sites
$sites = $pdo->query(
    "SELECT id, nom FROM sites ORDER BY nom"
)->fetchAll();

// Récupérer les incidents et les informations associées
$sql = "SELECT i.*,
               e.designation AS equipement,
               s.nom AS site
        FROM incidents i
        LEFT JOIN equipements e ON i.id_equipement = e.id
        LEFT JOIN sites s ON i.id_site = s.id
        ORDER BY i.date_declaration DESC";

$incidents = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des incidents - DSI Trésor Mali</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .conteneur {
            max-width: 1200px;
            margin: auto;
        }

        h1 { color: #123b63; }

        .carte {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000010;
        }

        .grille {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            font-size: 14px;
            background: white;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .large { grid-column: 1 / -1; }

        button, .bouton {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #123b63;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }

        button:hover, .bouton:hover { background: #20578a; }

        .retour { background: #475569; }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            background: #dcfce7;
            color: #166534;
            border-radius: 5px;
        }

        .erreur {
            padding: 12px;
            margin-bottom: 15px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 5px;
        }

        .table-responsive { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        th { background: #123b63; color: white; }

        td select { min-width: 125px; }

        .priorite-critique { color: #b91c1c; font-weight: bold; }
        .priorite-haute { color: #c2410c; font-weight: bold; }

        @media (max-width: 650px) {
            body { padding: 12px; }
            .grille { grid-template-columns: 1fr; }
            .large { grid-column: auto; }
        }
    </style>
</head>

<body>
<div class="conteneur">

    <h1>Gestion des incidents informatiques</h1>
    <p>DSI — Trésor Mali</p>

    <p>
        <a class="bouton retour" href="index.php">
            Retour au tableau de bord
        </a>
    </p>

    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= h($erreur) ?></div>
    <?php endif; ?>

    <div class="carte">
        <h2>Déclarer un nouvel incident</h2>

        <form method="post" action="incidents.php">
            <input type="hidden" name="action" value="ajouter">

            <div class="grille">
                <div class="large">
                    <label for="titre">Titre de l'incident *</label>
                    <input type="text" id="titre" name="titre"
                           placeholder="Ex. Panne de l'imprimante"
                           maxlength="200" required>
                </div>

                <div>
                    <label for="priorite">Priorité</label>
                    <select id="priorite" name="priorite">
                        <option value="faible">Faible</option>
                        <option value="normale" selected>Normale</option>
                        <option value="haute">Haute</option>
                        <option value="critique">Critique</option>
                    </select>
                </div>

                <div>
                    <label for="id_site">Site concerné</label>
                    <select id="id_site" name="id_site">
                        <option value="">Choisir un site</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?= (int)$site['id'] ?>">
                                <?= h($site['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="id_equipement">Équipement concerné</label>
                    <select id="id_equipement" name="id_equipement">
                        <option value="">Choisir un équipement</option>
                        <?php foreach ($equipements as $eq): ?>
                            <option value="<?= (int)$eq['id'] ?>">
                                <?= h($eq['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="technicien">Technicien responsable</label>
                    <input type="text" id="technicien" name="technicien"
                           placeholder="Nom du technicien">
                </div>

                <div class="large">
                    <label for="description">Description de l'incident</label>
                    <textarea id="description" name="description"
                              placeholder="Décrire le problème constaté..."></textarea>
                </div>

                <div class="large">
                    <button type="submit">Enregistrer l'incident</button>
                </div>
            </div>
        </form>
    </div>

    <div class="carte">
        <h2>Liste des incidents</h2>
        <p>Nombre d'incidents : <strong><?= count($incidents) ?></strong></p>

        <div class="table-responsive">
            <table>
                <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Titre</th>
                    <th>Site</th>
                    <th>Équipement</th>
                    <th>Priorité</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$incidents): ?>
                    <tr>
                        <td colspan="8">Aucun incident enregistré.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incidents as $incident): ?>
                        <tr>
                            <td><?= h($incident['numero_incident']) ?></td>
                            <td><?= h($incident['titre']) ?></td>
                            <td><?= h($incident['site'] ?? 'Non précisé') ?></td>
                            <td><?= h($incident['equipement'] ?? 'Non précisé') ?></td>
                            <td class="priorite-<?= h($incident['priorite']) ?>">
                                <?= h(ucfirst($incident['priorite'])) ?>
                            </td>
                            <td><?= h($incident['date_declaration']) ?></td>
                            <td>
                                <form method="post" action="incidents.php">
                                    <input type="hidden" name="action" value="statut">
                                    <input type="hidden" name="numero_incident"
                                           value="<?= h($incident['numero_incident']) ?>">

                                    <select name="statut">
                                        <?php
                                        $statuts = [
                                            'nouveau' => 'Nouveau',
                                            'en_cours' => 'En cours',
                                            'en_attente' => 'En attente',
                                            'resolu' => 'Résolu',
                                            'ferme' => 'Fermé'
                                        ];
                                        foreach ($statuts as $valeur => $libelle):
                                        ?>
                                            <option value="<?= h($valeur) ?>"
                                                <?= $incident['statut'] === $valeur
                                                    ? 'selected' : '' ?>>
                                                <?= h($libelle) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>
                            <td>
                                    <button type="submit">Mettre à jour</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>